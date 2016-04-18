<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 22/09/15
 * Time: 15:22
 */

define('APP_NAME', 'ex-google-analytics');
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

date_default_timezone_set('Europe/Prague');

ini_set('display_errors', true);
error_reporting(E_ALL);

set_error_handler(
    function ($errno, $errstr, $errfile, $errline, array $errcontext)
    {
        // error was suppressed with the @-operator
        if (0 === error_reporting()) {
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
);

require_once ROOT_PATH . '/vendor/autoload.php';
