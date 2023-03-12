<?php

/**
 *
 * @see https://www.makemkv.com/developers/usage.txt
 */

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

class MakeMKV
{
    private $source_device;

    public function __construct()
    {
        // verify that MakeMKV is available for the command-line
        exec('command -v makemkvcon', $sys_out, $sys_result);
        if ($sys_result !== 0) {
            throw new CliException('makemkvcon could not be found', 1);
        }

        $devices = self::getDevices();
        if (empty($devices)) {
            throw new CliException('No devices ready', 2);
        }
        $this->source_device = $devices[0];
    }

    public function extract($dir_out)
    {
        $drive_id = $this->source_device['index'];
        $drive_name = $this->source_device['label'];
	$disc_dir = verify_path($dir_out . DS . $drive_name . '_' . date('YmdHi'));
	printf('Ready to extract disc:%s "%s to %s"' . PHP_EOL, $drive_id, $drive_name, $disc_dir);
        exec(sprintf('makemkvcon mkv disc:%d all %s', $drive_id, $disc_dir . DS), $sys_out, $sys_result);
        if ($sys_result !== 0) {
            throw new CliException("Failed to extract disc: $drive_id \"$drive_name\" to $disc_dir", $sys_result);
        }
        // last line reports success and title count
        echo end($sys_out), PHP_EOL;
    }

    private static function getDevices()
    {
        // get all available drives
        exec('makemkvcon -r --cache=1 info disc:9999', $sys_out, $sys_result);
        if ($sys_result === 253) {
            throw new CliException('The activation key is invalid, please verify you have the newest key in ~/.MakeMKV/settings.conf', 999);
        }
        if ($sys_result !== 0) {
            throw new CliException('Could not get list of devices', 128);
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
}
