<?php

namespace Mccomaschris\ThundrCli;

use Mccomaschris\ThundrCli\Commands\ServerCloudFlareCommand;
use Mccomaschris\ThundrCli\Commands\ServerCreateCommand;
use Mccomaschris\ThundrCli\Commands\ServerDeleteCommand;
use Mccomaschris\ThundrCli\Commands\ServerEditCommand;
use Mccomaschris\ThundrCli\Commands\ServerListCommand;
use Mccomaschris\ThundrCli\Commands\ServerMonitorCommand;
use Mccomaschris\ThundrCli\Commands\ServerProvisionCommand;
use Mccomaschris\ThundrCli\Commands\ServerSshConnectCommand;
use Mccomaschris\ThundrCli\Commands\SiteArtisanCommand;
use Mccomaschris\ThundrCli\Commands\SiteCreateCommand;
use Mccomaschris\ThundrCli\Commands\SiteCronCommand;
use Mccomaschris\ThundrCli\Commands\SiteDeployCommand;
use Mccomaschris\ThundrCli\Commands\SiteEnvCommand;
use Mccomaschris\ThundrCli\Commands\SiteInitCommand;
use Mccomaschris\ThundrCli\Commands\SiteLogsCommand;
use Mccomaschris\ThundrCli\Commands\SiteRollbackCommand;
use Mccomaschris\ThundrCli\Commands\SiteSqliteCommand;
use Mccomaschris\ThundrCli\Commands\SiteSshCommand;
use Mccomaschris\ThundrCli\Commands\SiteSslCommand;
use Mccomaschris\ThundrCli\Commands\SiteStatusCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Thundr CLI', '1.0.0');

        $this->add(new ServerCloudFlareCommand);
        $this->add(new ServerCreateCommand);
        $this->add(new ServerEditCommand);
        $this->add(new ServerDeleteCommand);
        $this->add(new ServerListCommand);
        $this->add(new ServerMonitorCommand);
        $this->add(new ServerProvisionCommand);
        $this->add(new ServerSshConnectCommand);
        $this->add(new SiteArtisanCommand);
        $this->add(new SiteCreateCommand);
        $this->add(new SiteCronCommand);
        $this->add(new SiteDeployCommand);
        $this->add(new SiteEnvCommand);
        $this->add(new SiteInitCommand);
        $this->add(new SiteLogsCommand);
        $this->add(new SiteRollbackCommand);
        $this->add(new SiteSshCommand);
        $this->add(new SiteSqliteCommand);
        $this->add(new SiteSslCommand);
        $this->add(new SiteStatusCommand);
    }
}
