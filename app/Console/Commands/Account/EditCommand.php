<?php

namespace AbuseIO\Console\Commands\Account;

use AbuseIO\Console\Commands\AbstractEditCommand;
use AbuseIO\Models\Account;
use Prophecy\Argument;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;
use Validator;

class EditCommand extends AbstractEditCommand
{
    public function getOptionsList()
    {
        return new InputDefinition([
            new inputArgument('id', null, 'Account id to edit'),
            new InputOption('name', null, InputOption::VALUE_OPTIONAL, 'account name'),
            new InputOption('brand_id', null, InputOption::VALUE_OPTIONAL,  'brand id'),
            new InputOption('disabled', null, InputOption::VALUE_OPTIONAL, 'true|false, Set the account to be enabled'),
        ]);
    }

    public function getAsNoun()
    {
        return 'account';
    }

    protected function getModelFromRequest()
    {
        $account = Account::find($this->argument('id'));

        $this->updateFieldWithOption($account, 'name');
        $this->updateFieldWithOption($account, 'brand_id');
        $this->updateFieldWithOption($account, 'disabled');

        return $account;
    }

    protected function getValidator($model)
    {
        return Validator::make($model->toArray(), Account::updateRules($model));
    }
}
