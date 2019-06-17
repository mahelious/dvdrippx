#!/usr/bin/php
<?php

require_once('../config/bootstrap.php');
require_once('../lib/MkvExtract.php');

echo '*** Starting RIPP-X Extractor', PHP_EOL;

$exit_code = 0;

$start_time = time();

try {
	$output_dir = verify_path(RIPX_MKV_OUTPUT_DIR);
    $extract = new MkvExtract();
    $extract->to($output_dir);
    // verify results
	$dirscan = scandir($output_dir);
	if ($dirscan === false) {
         throw new CliException("$output_dir could not be verified.", 16);
	} else {
		echo count(array_diff($dirscan, ['.', '..'])), ' files written to ', $output_dir, PHP_EOL;
	}
} catch(CliException $e) {
    echo 'Error: ', $e->getMessage(), PHP_EOL;
	$exit_code = $e->getCode();
} catch (Exception $e) {
    echo 'Error: ', $e->getMessage(), '(', $e->getCode(), ')', PHP_EOL;
    $exit_code = 1;
}

echo '*** RIPP-X Extractor finished in ', time() - $start_time, ' seconds', PHP_EOL, PHP_EOL;

exit($exit_code);
