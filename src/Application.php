<?php

namespace Mccomaschris\ThundrCli;

use Mccomaschris\ThundrCli\Commands\ConfigCommand;
use Symfony\Component\Console\Application as BaseApplication;
use Mccomaschris\ThundrCli\Commands\ListCommand;
use Mccomaschris\ThundrCli\Commands\InitCommand;
use Mccomaschris\ThundrCli\Commands\SiteCreateCommand;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Thundr CLI', '1.0.0');

        $this->add(new ConfigCommand());
        $this->add(new ListCommand());
        $this->add(new InitCommand());
        $this->add(new SiteCreateCommand());
        // etc...
    }
}
