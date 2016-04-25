<?php

use Keboola\GoogleAnalyticsExtractor\Application;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Keboola\GoogleAnalyticsExtractor\Exception\UserException;
use Monolog\Logger;
use Symfony\Component\Yaml\Yaml;

require_once(dirname(__FILE__) . "/bootstrap.php");

$logger = new Logger(APP_NAME);

try {
    $arguments = getopt("d::", ["data::"]);
    if (!isset($arguments["data"])) {
        throw new UserException('Data folder not set.');
    }
    $config = Yaml::parse(file_get_contents($arguments["data"] . "/config.yml"));
    $config['parameters']['data_dir'] = $arguments['data'];
    $app = new Application($config);
    $app->run();
} catch(UserException $e) {
    $logger->log('error', $e->getMessage(), (array) $e->getData());
    exit(1);
} catch(ApplicationException $e) {
    $logger->log('error', $e->getMessage(), (array) $e->getData());
    exit($e->getCode() > 1 ? $e->getCode(): 2);
} catch(\Exception $e) {
    $logger->log('error', $e->getMessage(), [
        'errFile' => $e->getFile(),
        'errLine' => $e->getLine(),
        'trace' => $e->getTrace()
    ]);
    exit(2);
}

$logger->log('info', "Extractor finished successfully.");
exit(0);
