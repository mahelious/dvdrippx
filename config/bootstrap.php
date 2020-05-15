<?php

// quality of life improvements
define('DS', DIRECTORY_SEPARATOR);
define('PROJECT_DIR', realpath(__DIR__ . DS . '..') . DS);

require_once(PROJECT_DIR . 'lib' . DS . 'Exceptions.php');

// initialize project settings; use anonymous function to contol variable scope
(function () { foreach (@parse_ini_file('settings.ini') as $k => $v) : defined($k) || define($k, $v); endforeach; })();

function verify_path(string $path)
{
    if (!is_dir($path) && !mkdir($path, 0775, true)) {
        throw new CliException("Could not initialize directory '$path'", 2);
    }
    if (!is_writable($path)) {
        throw new CliException("'$path' is not writable", 2);
    }
    return $path;
}
