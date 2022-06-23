<?php


return [
    'notification' => [
        'name'          => 'Mail',
        'description'   => 'Sends notifications using e-mail',
        'enabled'       => env('MAIL_NOTIFICATION_ENABLED', true),
        'html_part_enabled' => env('MAIL_NOTIFICATION_HTML_PART_ENABLED',false),       //wither the html part should be used at all
        'text_part_enabled' => env('MAIL_NOTIFICATION_TEXT_PART_ENABLED',true),        //wither the text part should be used at all
        'prefer_html_body'  => env('MAIL_NOTIFICATION_PREFER_HTML_BODY',false),       //if enabled, HTML body with TEXT addition
    ],

    'templates' => [
        'subject'       => env('MAIL_TEMPLATES_SUBJECT', '[' . date('Y-m-d') . '] Notification of (possible) abuse ticket(s)'),

        'html_mail'     => env('MAIL_TEMPLATES_HTML_MAIL', '
<html>
<img>
<p>
Dear Customer,
</p><p>
You have received this report because the {{ $ticket_count }} IP address(es) and/or domains linked
to this report are under your control. We ask you to examine this report and take the
necessary action(s).
</p><p>
After resolving the matter we like to receive feedback from you which measures you
have taken to resolve and prevent new reports. You can leave your feedback by clicking
the URL next to each report. The portal URL also contains additional information how
to solve the problem this reports entails.
</p><p>
@foreach ($boxes as $box)
@if ($box["ticket_notification_type"] == "ip")
[Ticket {{ $box["ticket_number"] }}] Reporting {{ $box["ticket_type_name"] }} ({{ $box["ticket_class_name"] }}) for IP addres: {{ $box["ticket_ip"] }} <br/>
{{ $box["ticket_type_description"] }} <br/>
More information at: {{ $box["ip_contact_ash_link"] }} <br/>
@else
[Ticket {{ $box["ticket_number"] }}] Reporting {{ $box["ticket_type_name"] }} ({{ $box["ticket_class_name"] }}) for domain name: {{ $box["ticket_domain"] }} <br/>
{{ $box["ticket_type_description"] }} <br/>
More information at: {{ $box["domain_contact_ash_link"] }} <br/>
@endif
<br/>
@endforeach
</p><p>
Some reports are not considered abuse but more informational to the fact you are running
a service that might be susceptible to abuse in the feature. While we believe preventing
abuse is better then resolving it, we ask you to take measures to prevent actual abuse of
your service. However if you feel this service is running as intended you can choose to
ignore these informational notifications by tagging the ignore option at the portal URL.
Warning: Ignoring abuse reports could lead to actions listed in our Abuse Policy or
Acceptable Use Policy!
</p><p>
All the information we received and are allowed to share on these report(s) are listed
in in the portal. In most cases we received this report externally and do not have any
more information nor can we put you in contact with the original reporter.
</p><p>
With regards,<br/>
<br/>
ISP Abuse Department
</p>
<img width="160px" src="{{ $logo_src }}"/>
</body>
</html>'),
        'plain_mail'    => env('MAIL_TEMPLATES_PLAIN_MAIL', '
Dear Customer,

You have received this report because the {{ $ticket_count }} IP address(es) and/or domains linked
to this report are under your control. We ask you to examine this report and take the
necessary action(s).

After resolving the matter we like to receive feedback from you which measures you
have taken to resolve and prevent new reports. You can leave your feedback by clicking
the URL next to each report. The portal URL also contains additional information how
to solve the problem this reports entails.

@foreach ($boxes as $box)
@if ($box["ticket_notification_type"] == "ip")
[Ticket {{ $box["ticket_number"] }}] Reporting {{ $box["ticket_type_name"] }} ({{ $box["ticket_class_name"] }}) for IP addres: {{ $box["ticket_ip"] }}
{{ $box["ticket_type_description"] }}
More information at: {{ $box["ip_contact_ash_link"] }}
@else
[Ticket {{ $box["ticket_number"] }}] Reporting {{ $box["ticket_type_name"] }} ({{ $box["ticket_class_name"] }}) for domain name: {{ $box["ticket_domain"] }}
{{ $box["ticket_type_description"] }}
More information at: {{ $box["domain_contact_ash_link"] }}
@endif

@endforeach

Some reports are not considered abuse but more informational to the fact you are running
a service that might be susceptible to abuse in the feature. While we believe preventing
abuse is better then resolving it, we ask you to take measures to prevent actual abuse of
your service. However if you feel this service is running as intended you can choose to
ignore these informational notifications by tagging the ignore option at the portal URL.
Warning: Ignoring abuse reports could lead to actions listed in our Abuse Policy or
Acceptable Use Policy!

All the information we received and are allowed to share on these report(s) are listed
in in the portal. In most cases we received this report externally and do not have any
more information nor can we put you in contact with the original reporter.

With regards,

ISP Abuse Department
                                           '),

    ],

];
