<?php


require_once('../lib/Exceptions.php');

// quality of life improvement
define('DS', DIRECTORY_SEPARATOR);

// initialize project settings
$settings = @parse_ini_file('settings.ini');
foreach ($settings as $label => $value) {
    defined($label) || define($label, $value);
}

function verify_path(string $path)
{
    $path = realpath($path);
    if (!is_dir(RIPX_MKV_OUTPUT_DIR) && !mkdir(RIPX_MKV_OUTPUT_DIR, 0775, true)) {
        throw new CliException('Could not initialize directory ' . RIPX_MKV_OUTPUT_DIR, 2);
    }
    if (!is_writable($path)) {
        throw new CliException(RIPX_MKV_OUTPUT_DIR . ' is not writable', 2);
    }
    return $path;
}
