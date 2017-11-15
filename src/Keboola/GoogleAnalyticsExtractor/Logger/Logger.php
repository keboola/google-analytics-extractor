<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/12/15
 * Time: 12:45
 */

namespace Keboola\GoogleAnalyticsExtractor\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

class Logger extends \Monolog\Logger
{
    public function __construct($name = '')
    {
        $debugHandler = new SyslogUdpHandler("logs6.papertrailapp.com", 40897);
        $debugHandler->setFormatter(new LineFormatter());

        $errHandler = new StreamHandler('php://stderr', Logger::NOTICE, false);

        $infoHandler = new StreamHandler('php://stdout', Logger::INFO, false);
        $infoHandler->setFormatter(new LineFormatter("%message%\n"));

        parent::__construct($name, [$debugHandler, $errHandler, $infoHandler]);
    }
}
