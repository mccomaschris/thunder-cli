<?php

namespace Mccomaschris\ThundrCli;

use Mccomaschris\ThundrCli\Commands\ConfigDeleteServerCommand;
use Mccomaschris\ThundrCli\Commands\ConfigEditServerCommand;
use Mccomaschris\ThundrCli\Commands\ConfigInitCommand;
use Mccomaschris\ThundrCli\Commands\ConfigListServersCommand;
use Mccomaschris\ThundrCli\Commands\SiteArtisanCommand;
use Mccomaschris\ThundrCli\Commands\SiteCreateCommand;
use Mccomaschris\ThundrCli\Commands\SiteCronCommand;
use Mccomaschris\ThundrCli\Commands\SiteDeployCommand;
use Mccomaschris\ThundrCli\Commands\SiteEnvCommand;
use Mccomaschris\ThundrCli\Commands\SiteInitCommand;
use Mccomaschris\ThundrCli\Commands\SiteLogsCommand;
use Mccomaschris\ThundrCli\Commands\SiteRollbackCommand;
use Mccomaschris\ThundrCli\Commands\SiteSshCommand;
use Mccomaschris\ThundrCli\Commands\SiteSslCommand;
use Mccomaschris\ThundrCli\Commands\SiteStatusCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Thundr CLI', '1.0.0');

        $this->add(new ConfigInitCommand);
        $this->add(new ConfigEditServerCommand);
        $this->add(new ConfigDeleteServerCommand);
        $this->add(new ConfigListServersCommand);
        $this->add(new SiteArtisanCommand);
        $this->add(new SiteCreateCommand);
        $this->add(new SiteCronCommand);
        $this->add(new SiteDeployCommand);
        $this->add(new SiteEnvCommand);
        $this->add(new SiteInitCommand);
        $this->add(new SiteLogsCommand);
        $this->add(new SiteRollbackCommand);
        $this->add(new SiteSshCommand);
        $this->add(new SiteSslCommand);
        $this->add(new SiteStatusCommand);
        // etc...
    }
}
