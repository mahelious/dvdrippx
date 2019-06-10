<?php

# a selection of HandBrake v.1.2.2 presets
define('PRESET_GENERAL_VERYFAST_1080',	'Very Fast 1080p30');
define('PRESET_GENERAL_VERYFAST_720',	'Very Fast 720p30');
define('PRESET_GENERAL_VERYFAST_480',	'Very Fast 480p30');
define('PRESET_GENERAL_FAST_1080',		'Fast 1080p30');
define('PRESET_GENERAL_FAST_720',		'Fast 720p30');
define('PRESET_GENERAL_FAST_480',		'Fast 480p30');
define('PRESET_GENERAL_HQ_1080',		'HQ 1080p30 Surround');
define('PRESET_GENERAL_HQ_720',			'HQ 720p30 Surround');
define('PRESET_GENERAL_SUPERHQ_1080',	'Super HQ 1080p30 Surround');
define('PRESET_GENERAL_SUPERHQ_720',	'Super HQ 720p30 Surround');

define('PROCESS_FILES_MAX', 5); // max number of files that will be accepted for processing in a single run

define('DS', DIRECTORY_SEPARATOR);

class CliException extends Exception
{
	public function __construct($message = '', $code = 1, $previous = null)
	{
		parent::__construct($message, $code, $previous);
		// Exception.code defaults to 0 in PHP, however 0 indicates success in *nix; default to 1 to indicate some runtime error
		if (empty($this->code)) {
			$this->code = 1;
		}
	}
}

$exit_code = 0;

try {
	// initialize project settings
    $settings = @parse_ini_file('settings.ini');
	foreach (['RIPX_MKV_OUTPUT_DIR', 'RIPX_PROCESSING_DIR', 'RIPX_RIP_OUTPUT_DIR'] as $required_setting) {
		if (empty($settings[$required_setting])) {
			throw new CliException("$required_setting not found in settings.ini", 2);
		}
	}
	
	echo '*** Starting RIPP-X Encoder', PHP_EOL;
	$start_time = time();

	$processed_files = [];
	
	$files_for_processing = get_mkv_files($settings['RIPX_MKV_OUTPUT_DIR']);
	
	foreach ($files_for_processing as $file_to_process) {
		if (count($processed_files) >= PROCESS_FILES_MAX) {
			break;
		}
		
		// is_readable checks that a file exists and can be read; potential that file was snagged by concurrent process
		if (!is_readable($file_to_process)) {
			continue;
		}
		
		// move file into the processing directory
		$filename = basename($file_to_process);
		$processing_file = $settings['RIPX_PROCESSING_DIR'] . DS . $filename;
		if (!rename($file_to_process, $processing_file)) {
			echo "Could not move $file_to_process into $settings[RIPX_PROCESSING_DIR], skipping", PHP_EOL;
			continue;
		}
		
		// use HandBrakeCLI to perform encoding
		$output_filename = $settings['RIPX_RIP_OUTPUT_DIR'] . DS . strstr($filename, '.mkv', true) . '.mp4';
		@exec(sprintf('HandBrakeCLI -Z "%s" -i %s -o %s 2>&1', PRESET_GENERAL_HQ_1080, $processing_file, $output_filename), $convert_out, $convert_result);
		
		if ($convert_result !== 0) {
			echo "Failed to encode $filename, restoring...", PHP_EOL;
			if (!rename($processing_file, $file_to_process)) {
				throw new CliException("Could not restore $processing_file to $file_to_process, halting", 4);
			}
			echo "$processing_file restored to $file_to_process, proceeding", PHP_EOL;
			continue;
		}
		
		echo "$filename encoded successfully, saved to $output_filename", PHP_EOL;
		$processed_files[] = $output_filename;
		
		// cleanup the processing file
		if (!unlink($processing_file)) {
			echo "$processing_file could not be removed", PHP_EOL;
		}	
	}
	
	echo '*** RIPP-X Encoder finished in ', time() - $start_time, ' seconds, processed ', count($processed_files), ' files.', PHP_EOL;	 
} catch (Exception $e) {
    echo 'Error: ', $e->getMessage(), PHP_EOL;
	$exit_code = $e->getCode();
}
 
exit($exit_code);
 
function get_mkv_files($mkv_dir)
{
	$mkv_files = [];
	// get the contents of the directory, ignoring the current or parent
	$dir_contents = array_diff(scandir($mkv_dir), ['.', '..']);
	foreach ($dir_contents as $filename) {
		$file = $mkv_dir . DS . $filename;
		if (!is_readable($file)) {
			continue;
		} else
		if (is_dir($file)) {
			$mkv_files = array_merge($mkv_files, get_mkv_files($file));
		} else 
		if (is_file($file) && strpos($file, '.mkv') > 0) {
			// check that the file is not accessed by another process
			@exec("lsof -f -- '$file' 2>&1", $sys_output, $is_file_available);
			// $is_file_available is zero if the file is in use by another process
			if ($is_file_available !== 0) {
				$mkv_files[] = $file;
			}
		}
	}
	return $mkv_files;
}

?>