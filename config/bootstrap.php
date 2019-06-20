<?php

// quality of life improvements
define('DS', DIRECTORY_SEPARATOR);
define('PROJECT_DIR', realpath(__DIR__ . DS . '..') . DS);

require_once(PROJECT_DIR . 'lib' . DS . 'Exceptions.php');

// initialize project settings
$settings = @parse_ini_file('settings.ini');
foreach ($settings as $label => $value) {
    defined($label) || define($label, $value);
}

function verify_path(string $path)
{
    if (!is_dir($path) && !mkdir($path, 0775, true)) {
        throw new CliException('Could not initialize directory "' . $path . '"', 2);
    }
    if (!is_writable($path)) {
        throw new CliException('"' . $path . '" is not writable', 2);
    }
    return $path;
}
