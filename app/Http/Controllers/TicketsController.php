<?php

namespace AbuseIO\Http\Controllers;

use AbuseIO\Http\Requests\TicketFormRequest;
use AbuseIO\Jobs\Notification;
use AbuseIO\Jobs\TicketUpdate;
use AbuseIO\Models\Event;
use AbuseIO\Models\Ticket;
use AbuseIO\Traits\Api;
use AbuseIO\Transformers\TicketTransformer;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use Redirect;
use yajra\Datatables\Datatables;
use Zend\Json\Json;

/**
 * Class TicketsController.
 */
class TicketsController extends Controller
{
    use Api;

    /**
     * TicketsController constructor.
     */
    public function __construct(Manager $fractal, Request $request)
    {
        parent::__construct();

        // initialize the api
        $this->apiInit($fractal, $request);

        // is the logged in account allowed to execute an action on the Ticket
        $this->middleware('checkaccount:Ticket', ['except' => ['search', 'index', 'create', 'store', 'export']]);
    }

    /**
     * Process datatables ajax request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search()
    {
        $auth_account = $this->auth_user->account;

        $tickets = Ticket::select(
            'tickets.id',
            'tickets.ip',
            'tickets.domain',
            'tickets.type_id',
            'tickets.class_id',
            'tickets.status_id',
            'tickets.ip_contact_account_id',
            'tickets.ip_contact_reference',
            'tickets.ip_contact_name',
            'tickets.domain_contact_account_id',
            'tickets.domain_contact_reference',
            'tickets.domain_contact_name',
            DB::raw('count(distinct events.id) as event_count'),
            DB::raw('count(distinct notes.id) as notes_count')
        )
            ->leftJoin('events', 'events.ticket_id', '=', 'tickets.id')
            ->leftJoin(
                'notes',
                function ($join) {
                    // We need a LEFT JOIN .. ON .. AND ..).
                // This doesn't exist within Illuminate's JoinClause class
                // So we use some nesting foo here
                    $join->on('notes.ticket_id', '=', 'tickets.id')
                        ->nest(
                            function ($join) {
                                $join->on('notes.viewed', '=', DB::raw("'false'"));
                            }
                        );
                }
            )
            ->groupBy('tickets.id');

        if (!$auth_account->isSystemAccount()) {
            // We're using a grouped where clause here, otherwise the filtering option
            // will always show the same result (all tickets)
            $tickets = $tickets->where(
                function ($query) use ($auth_account) {
                    $query->where('tickets.ip_contact_account_id', '=', $auth_account->id)
                        ->orWhere('tickets.domain_contact_account_id', '=', $auth_account->id);
                }
            );
        }

        return Datatables::of($tickets)
            // Create the action buttons
            ->addColumn(
                'actions',
                function ($ticket) {
                    $actions = ' <a href="tickets/'.$ticket->id.
                        '" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-eye-open"></span> '.
                        trans('misc.button.show').'</a> ';

                    return $actions;
                }
            )
            ->editColumn(
                'type_id',
                function ($ticket) {
                    return trans('types.type.'.$ticket->type_id.'.name');
                }
            )
            ->editColumn(
                'class_id',
                function ($ticket) {
                    return trans('classifications.'.$ticket->class_id.'.name');
                }
            )
            ->editColumn(
                'status_id',
                function ($ticket) {
                    return trans('types.status.abusedesk.'.$ticket->status_id.'.name');
                }
            )
            ->make(true);
    }

    /**
     * api search
     * expects query criteria in the body of the request
     * eg :
     * {
     *   "criteria":
     *   [
     *     {
     *        "column": "ip",
     *        "operator": "like",
     *        "value": "%10%"
     *     },
     *     {
     *        "column": "id",
     *        "operator": ">",
     *        "value": 7
     *     }
     *   ],
     *   "orderby": "ip",
     *   "limit": "5"
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiSearch(Request $request)
    {
        $body = $request->getContent();
        $post_process = [];
        $mapped_columns = [
            'event_count',
        ];

        try {
            $query = Json::decode($body, Json::TYPE_OBJECT);
        }
        catch (\Exception $e)
        {
            return $this->errorInternalError("Faulty JSON request");
        }

        // construct model query
        $tickets = Ticket::query();
        if (isset($query->criteria)) {
            foreach ($query->criteria as $c) {
                // check if we have al the right properties in the criteria
                if (!(isset($c->column) && isset($c->value))) {
                    return $this->errorWrongArgs("Criteria field is missing");
                }

                // no operator, set it to 'equals'
                $c->operator = isset($c->operator) ? $c->operator : '=';

                // skip mapped columns, to process them later
                if (in_array($c->column, $mapped_columns)) {
                    array_push($post_process, $c);
                    continue;
                }

                $tickets = $tickets->where($c->column, $c->operator, $c->value);
            }
        }

        // execute the db query
        try {
            $result = $tickets->get();
        } catch (QueryException $e) {
            return $this->errorInternalError($e->getMessage());
        }

        // post process the collection, filter on the the dynamic fields (currently only integer fields)
        // todo: refactor/cleanup
        foreach ($post_process as $c) {
            $result = $result->filter(function ($object) use ($c) {
                $column = $c->column;
                $value = $object->$column;

                switch ($c->operator) {
                    case '>' :
                        $success = $value > $c->value;
                        break;
                    case '<' :
                        $success = $value < $c->value;
                        break;
                    case '=' :
                        $success = $value == $c->value;
                        break;
                    default :
                        // unknown / not implemented operator
                        $success = true;
                        break;
                }
                return $success;
            });
        }

        // order the results
        if (isset($query->orderby)) {
            $result = $result->sortBy(function ($object) use ($query) {
                $column = $query->orderby;
                return $object->$column;
            });
        }

        // limit the results
        if (isset($query->limit)) {
            $result = $result->take($query->limit);
        }

        return $this->respondWithCollection($result, new TicketTransformer());
    }

    /**
     * Display all tickets.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get translations for all statuses
        $statuses = Event::getStatuses();

        return view('tickets.index')
            ->with('types', Event::getTypes())
            ->with('classes', Event::getClassifications())
            ->with('statuses', $statuses['abusedesk'])
            ->with('contact_statuses', $statuses['contact'])
            ->with('auth_user', $this->auth_user);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiIndex()
    {
        $tickets = Ticket::all();

        return $this->respondWithCollection($tickets, new TicketTransformer());
    }

    /**
     * Export tickets to CSV format.
     *
     * @param string $format
     *
     * @return \Illuminate\Http\Response
     */
    public function export($format)
    {
        // TODO #AIO-?? ExportProvider - (mark) Move this into an ExportProvider or something?

        // only export all tickets when we are in the systemaccount
        $auth_account = $this->auth_user->account;
        if ($auth_account->isSystemAccount()) {
            $tickets = Ticket::all();
        } else {
            $tickets = Ticket::select('tickets.*')
              ->where('ip_contact_account_id', $auth_account->id)
              ->orWhere('domain_contact_account_id', $auth_account);
        }

        if ($format === 'csv') {
            $columns = [
                'id'            => 'Ticket ID',
                'ip'            => 'IP address',
                'class_id'      => 'Classification',
                'type_id'       => 'Type',
                'first_seen'    => 'First seen',
                'last_seen'     => 'Last seen',
                'event_count'   => 'Events',
                'status_id'     => 'Ticket Status',
            ];

            $output = '"'.implode('", "', $columns).'"'.PHP_EOL;

            foreach ($tickets as $ticket) {
                $row = [
                    $ticket->id,
                    $ticket->ip,
                    trans("classifications.{$ticket->class_id}.name"),
                    trans("types.type.{$ticket->type_id}.name"),
                    $ticket->firstEvent[0]->seen,
                    $ticket->lastEvent[0]->seen,
                    $ticket->events->count(),
                    trans("types.status.abusedesk.{$ticket->status_id}.name"),
                ];

                $output .= '"'.implode('", "', $row).'"'.PHP_EOL;
            }

            return response(substr($output, 0, -1), 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="Tickets.csv"');
        }

        return Redirect::route('admin.contacts.index')
            ->with('message', "The requested format {$format} is not available for exports");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param TicketFormRequest $ticketForm
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiStore(TicketFormRequest $ticketForm)
    {
        $ticket = Ticket::create($ticketForm->all());

        return $this->respondWithItem($ticket, new TicketTransformer());
    }

    /**
     * Display the specified ticket.
     *
     * @param Ticket $ticket
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Ticket $ticket)
    {
        return view('tickets.show')
            ->with('ticket', $ticket)
            ->with('ticket_class', config("types.status.abusedesk.{$ticket->status_id}.class"))
            ->with('contact_ticket_class', config("types.status.contact.{$ticket->contact_status_id}.class"))
            ->with('auth_user', $this->auth_user);
    }

    /**
     * Display the specified resource.
     *
     * @param Ticket $ticket
     *
     * @return \Illuminate\Http\Response
     */
    public function apiShow(Ticket $ticket)
    {
        return $this->respondWithItem($ticket, new TicketTransformer());
    }

    /**
     * Update the requested contact information.
     *
     * @param Ticket $ticket
     * @param string $only
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Ticket $ticket, $only = null)
    {
        TicketUpdate::contact($ticket, $only);

        return Redirect::route('admin.tickets.show', $ticket->id)
            ->with('message', 'Contact has been updated.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param TicketFormRequest $ticketForm
     * @param Ticket            $ticket
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function apiUpdate(TicketFormRequest $ticketForm, Ticket $ticket)
    {
        $ticket->update($ticketForm->all());

        return $this->respondWithItem($ticket, new TicketTransformer());
    }

    /**
     * Set the status of a tickets.
     *
     * @param Ticket $ticket
     * @param string $newstatus
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function status(Ticket $ticket, $newstatus)
    {
        TicketUpdate::status($ticket, $newstatus);

        return Redirect::route('admin.tickets.show', $ticket->id)
            ->with('message', 'Ticket status has been updated.');
    }

    /**
     * Send a notification for this ticket to the IP contact.
     *
     * @param Ticket $ticket
     * @param string $only
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function notify(Ticket $ticket, $only = null)
    {
        $notification = new Notification();
        $notification->walkList(
            $notification->buildList($ticket->id, false, true, $only)
        );

        return Redirect::route('admin.tickets.show', $ticket->id)
            ->with('message', 'Contact has been notified.');
    }

    /**
     * Send a notification for this ticket to the contacts.
     * api method.
     *
     * @param Ticket $ticket
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiNotify(Ticket $ticket)
    {
        $notification = new Notification();
        $notification->walkList(
            $notification->buildList($ticket->id, false, true, null)
        );

        // refresh ticket
        $ticket = Ticket::find($ticket->id);

        return $this->respondWithItem($ticket, new TicketTransformer());
    }
}
