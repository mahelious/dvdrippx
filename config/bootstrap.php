<?php

// quality of life improvements
define('DS', DIRECTORY_SEPARATOR);
define('PROJECT_DIR', realpath(__DIR__ . DS . '..') . DS);

define('ERRCODE_BOOT_NOMETHOD',     2);
define('ERRCODE_BOOT_DIRNOMAKE',    101);
define('ERRCODE_BOOT_DIRNOREAD',    102);
define('ERRCODE_BOOT_DIRNOWRITE',   103);

require_once(PROJECT_DIR . 'lib' . DS . 'Exceptions.php');


// initialize project settings from ini
//  use anonymous function to contol variable scope
(function () {
    $settings = @parse_ini_file('settings.ini', true);

    // TODO : integrate LOGS constant
    define('RIPPX_DIR_LOGS', sprintf('%s%s', PROJECT_DIR, $settings['subdirectories']['logs']));
    define('RIPPX_DIR_PROCESSING', sprintf('%s%s', PROJECT_DIR, $settings['subdirectories']['processing']));
    define('RIPPX_DIR_OUTPUT_EXTRACT', sprintf('%s%s', PROJECT_DIR, $settings['subdirectories']['extract_output']));
    define('RIPPX_DIR_OUTPUT_ENCODE', sprintf('%s%s', PROJECT_DIR, $settings['subdirectories']['encode_output']));

    // set constants for extractor and encoder options
    foreach ($settings['extract_options'] as $k => $v) {
        defined($k) || define($k, $v);
    }

    foreach ($settings['encode_options'] as $k => $v) {
        defined($k) || define($k, $v);
    }
})();

function find_opt(array $options, array $args, bool $has_value=true) {
    foreach ($options as $option) {
        $option_key = array_search($option, $args);
        if ($option_key === false || $option_key < 0) {
            continue;
        }
        if (!$has_value) {
            return true;
        }
        return (count($args) - 1 >= $option_key + 1 ? $args[$option_key + 1] : true);
    }
    return false;
}

function verify_path(string $path) {
    if (!is_dir($path) && !mkdir($path, 0775, true)) {
        throw new CliException("Could not initialize directory '$path'", ERRCODE_BOOT_DIRNOMAKE);
    }
    if (!is_readable($path)) {
        throw new CliException("'$path' cannot be read from", ERRCODE_BOOT_DIRNOREAD);
    }
    if (!is_writable($path)) {
        throw new CliException("'$path' cannot be written to", ERRCODE_BOOT_DIRNOWRITE);
    }
    return $path;
}
