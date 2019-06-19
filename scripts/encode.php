#!/usr/bin/php
<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php');
require_once(PROJECT_DIR . 'lib' . DS . 'MkvEncode.php');

$exit_code = 0;

// TODO - accept Handbrake preset from argc

try {
    echo '*** Starting RIPP-X Encoder at ', date('Y-m-d H:i:s'), PHP_EOL;
    $start_time = time();

    $processed_files = [];

    $mkv_files = MkvEncode::getMkvFiles(RIPX_MKV_OUTPUT_DIR);
    if (count($mkv_files) < 1) {
        echo '*** RIPP-X Encoder finished in ', time() - $start_time, ' seconds, no files found to process.', PHP_EOL;
        exit(0);
    }

    foreach ($mkv_files as $mkv_file) {
        if (count($processed_files) >= RIPX_ENCODE_FILES_MAX) {
            break;
        }

        $output_file = $mkv_file->encode(PRESET_GENERAL_HQ_1080);
        if (!empty($output_file)) {
            echo "$filename encoded successfully, saved to $output_file", PHP_EOL;
            $processed_files[] = $output_file;
        }
    }
} catch (CliException $e) {
    echo 'Error: ', $e->getMessage(), PHP_EOL;
    $exit_code = $e->getCode();
} catch (Exception $e) {
    echo 'Error: ', $e->getMessage(), '(', $e->getCode(), ')', PHP_EOL;
    $exit_code = 1;
}

echo '*** RIPP-X Encoder finished in ', time() - $start_time, ' seconds, ',
    ($processed_files_count = count($processed_files)) > 0 ? "processed $processed_files_count files." : 'no files found to process',
    PHP_EOL;

exit($exit_code);
