<?php

namespace Keboola\GoogleAnalyticsExtractor\Logger;

use \Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

class Logger extends MonologLogger
{
    public function __construct($name = '')
    {
        parent::__construct(
            $name,
            [
                self::getCriticalHandler(),
                self::getErrorHandler(),
                self::getInfoHandler(),
            ]
        );
    }

    public static function getErrorHandler()
    {
        $errorHandler = new StreamHandler('php://stderr');
        $errorHandler->setBubble(false);
        $errorHandler->setLevel(MonologLogger::WARNING);
        $errorHandler->setFormatter(new LineFormatter("%message%\n"));
        return $errorHandler;
    }
    public static function getInfoHandler()
    {
        $logHandler = new StreamHandler('php://stdout');
        $logHandler->setBubble(false);
        $logHandler->setLevel(MonologLogger::INFO);
        $logHandler->setFormatter(new LineFormatter("%message%\n"));
        return $logHandler;
    }
    public static function getCriticalHandler()
    {
        $handler = new StreamHandler('php://stderr');
        $handler->setBubble(false);
        $handler->setLevel(MonologLogger::CRITICAL);
        $handler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message% %context% %extra%\n"));
        return $handler;
    }
}
