<?php

namespace ThundrLabs\ThundrCli;

use ThundrLabs\ThundrCli\Commands\ServerCloudFlareCommand;
use ThundrLabs\ThundrCli\Commands\ServerCreateCommand;
use ThundrLabs\ThundrCli\Commands\ServerDeleteCommand;
use ThundrLabs\ThundrCli\Commands\ServerEditCommand;
use ThundrLabs\ThundrCli\Commands\ServerListCommand;
use ThundrLabs\ThundrCli\Commands\ServerMonitorCommand;
use ThundrLabs\ThundrCli\Commands\ServerProvisionCommand;
use ThundrLabs\ThundrCli\Commands\ServerSshConnectCommand;
use ThundrLabs\ThundrCli\Commands\SiteArtisanCommand;
use ThundrLabs\ThundrCli\Commands\SiteCreateCommand;
use ThundrLabs\ThundrCli\Commands\SiteCronCommand;
use ThundrLabs\ThundrCli\Commands\SiteDeployCommand;
use ThundrLabs\ThundrCli\Commands\SiteEnvCommand;
use ThundrLabs\ThundrCli\Commands\SiteInitCommand;
use ThundrLabs\ThundrCli\Commands\SiteLogsCommand;
use ThundrLabs\ThundrCli\Commands\SiteRollbackCommand;
use ThundrLabs\ThundrCli\Commands\SiteSqliteCommand;
use ThundrLabs\ThundrCli\Commands\SiteSshCommand;
use ThundrLabs\ThundrCli\Commands\SiteSslCommand;
use ThundrLabs\ThundrCli\Commands\SiteStatusCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Thundr CLI', '1.0.3');

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
