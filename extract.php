#!/usr/bin/php
<?php

/**
 *
 * @see https://www.makemkv.com/developers/usage.txt
 */

define('DS', DIRECTORY_SEPARATOR);
define('DEVICE_INFO_DRIVE', 'DRV:');

/* makemkv-oss-1.14.3/makemkvgui/inc/lgpl/apdefs.h
static const unsigned int AP_DriveStateNoDrive=256;
static const unsigned int AP_DriveStateUnmounting=257;
static const unsigned int AP_DriveStateEmptyClosed=0;
static const unsigned int AP_DriveStateEmptyOpen=1;
static const unsigned int AP_DriveStateInserted=2;
static const unsigned int AP_DriveStateLoading=3;
 */
define('DRIVESTATE_NODRIVE',    256);
define('DRIVESTATE_UNMOUNTING', 257);
define('DRIVESTATE_EMPTYCLOSED',0);
define('DRIVESTATE_EMPTYOPEN',  1);
define('DRIVESTATE_INSERTED',   2);
define('DRIVESTATE_LOADING',    3);

/* makemkv-oss-1.14.3/makemkvgui/inc/lgpl/apdefs.h
 * note: this list appears to be incomplete
static const unsigned int AP_DskFsFlagDvdFilesPresent=1;
static const unsigned int AP_DskFsFlagHdvdFilesPresent=2;
static const unsigned int AP_DskFsFlagBlurayFilesPresent=4;
static const unsigned int AP_DskFsFlagAacsFilesPresent=8;
static const unsigned int AP_DskFsFlagBdsvmFilesPresent=16;
 */
define('DISC_FS_FLAG_DVD',       1);
define('DISC_FS_FLAG_HDVD',      2);
define('DISC_FS_FLAG_BLURAY',    4);
define('DISC_FS_FLAG_AACS',      8);
define('DISC_FS_FLAG_BDRE',     12);
define('DISC_FS_FLAG_BDSVM',    16);

echo '*** Starting RIPP-X Extractor', PHP_EOL;

$exit_code = 0;

$start_time = time();

try {
	$settings = @parse_ini_file('settings.ini');
	
	if (!empty($settings['RIPX_MKV_OUTPUT_DIR'])) {
		$output_dir = get_path($settings['RIPX_MKV_OUTPUT_DIR']);
	} else {
		$output_dir = get_path(realpath($_SERVER['HOME']) . DS . 'Videos' . DS . 'mkv');
	}
		
    // verify that MakeMKV is available for the command-line
    exec('command -v makemkvcon', $sys_out, $sys_result);
    if ($sys_result !== 0) {
        throw new Exception('MakeMKV could not be found', 999);
    }

    $devices = get_devices();
	$device = $devices[0] ?? null;
    if (empty($device)) {
        throw new Exception('No devices ready', 2);
    }

	echo "Ready to extract disc:{$device['index']} \"{$device['label']}\"", PHP_EOL;
	$disc_dir = get_path($output_dir . DS . $device['label'] . '__' . date('YmdHi'));
	exec(sprintf('makemkvcon mkv disc:%d all %s', $device['index'], $disc_dir . DS), $sys_out, $sys_result);
	if ($sys_result !== 0) {
		$msg = "Failed to extract disc: {$device['index']} \"{$device['label']}\" to $disc_dir";
		throw new Exception($msg, $sys_result);
	}
	// last line reports success and title count
	echo end($sys_out), PHP_EOL;
	
	$dirscan = scandir($output_dir);
	if ($dirscan === false) {
		echo "An exception occurred inspecting $output_dir", PHP_EOL;
	} else {
		echo count(array_diff($dirscan, ['.', '..'])), " files written to $output_dir", PHP_EOL;
	}
} catch (Exception $e) {
    echo 'Error: ', $e->getMessage(), PHP_EOL;
	$exit_code = $e->getCode();
}

echo '*** RIPP-X Extractor finished in ', time() - $start_time, ' seconds', PHP_EOL, PHP_EOL;

exit($exit_code);

// *********************************************************************************************************************

function get_path($path)
{
    if (!is_dir($path) && !mkdir($path, 0775, true)) {
        throw new Exception("Could not initialize directory '$path'", 100);
    }
    if (!is_writable($path)) {
        throw new Exception("'$path' is not writable", 101);
    }
    return $path;
}

function get_devices()
{
    // get all available drives
    exec('makemkvcon -r --cache=1 info disc:9999', $sys_out, $sys_result);
	if ($sys_result === 253) {
		throw new Exception('The activation key is invalid, please verify you have the newest key in ~/.MakeMKV/settings.conf', 253);
	}
    if ($sys_result !== 0) {
        throw new Exception('Could not get list of devices', 200);
    }

    $active_devices = [];
    foreach ($sys_out as $line) {
        // skip makemkv messaging and cruft
        if (strpos($line, DEVICE_INFO_DRIVE) !== 0) {
            continue;
        }
        // strip superfluous quotes from drive info
        $device_info = explode(',', substr(str_replace(["'", '"'], '', $line), strlen(DEVICE_INFO_DRIVE)));
        /*
         * per https://www.makemkv.com/developers/usage.txt
         * Drive scan message format
         * DRV:index,visible,enabled,flags,drive name,disc name
         * jk;lol
         * DRV:index,state,nooneknows,flag,drive name,disc name,drive source
         */
        list($index, $drive_state, , $fs_flag, $name, $label, $drive) = $device_info;

        if ((int)$drive_state === DRIVESTATE_INSERTED) {
            $active_devices[] = compact('index', 'label');
        }
    }

    return $active_devices;
}

?>