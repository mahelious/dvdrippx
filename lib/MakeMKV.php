<?php

/**
 *
 * @see https://www.makemkv.com/developers/usage.txt
 */

// TODO - polluting the global constant space is gross, make these class constants

define('DEVICE_INFO_DRIVE', 'DRV:');

define('RETURN_CODE_SUCCESS',   0);
define('RETURN_CODE_ERR_MKVCON_NOKEY',     253);

/* makemkv-oss-1.17.3/makemkvgui/inc/lgpl/apdefs.h
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

/* makemkv-oss-1.17.3/makemkvgui/inc/lgpl/apdefs.h
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

define('ERRCODE_MAKEMKV_NOTFOUND',          20);
define('ERRCODE_MAKEMKV_NODEVICEREADY',     21);
define('ERRCODE_MAKEMKV_NOINFO',            22);
define('ERRCODE_MAKEMKV_FAILEDDISCEXTRACT', 23);
define('ERRCODE_MAKEMKV_FAILEDVERIFY',      24);
define('ERRCODE_MAKEMKV_NOKEY',             29);

class MakeMKV
{
    /**
     * extract
     *
     * Perform an extraction of video files from available device
     *
     * @param   string  $dir_out
     * @throws  CliException
     */
    public static function extract($dir_out)
    {
        self::testInstallation();

        $devices = self::getDevices();
        if (empty($devices)) {
            throw new CliException('No devices ready', ERRCODE_MAKEMKV_NODEVICEREADY);
        }

        $drive_id = $devices[0]['index'];
        $drive_name = $devices[0]['label'];
        $disc_dir = verify_path($dir_out . DS . $drive_name . '_' . date('YmdHi'));
        printf('Ready to extract disc:%s "%s to %s"' . PHP_EOL, $drive_id, $drive_name, $disc_dir);
        exec(sprintf('makemkvcon mkv disc:%d all %s', $drive_id, $disc_dir . DS), $sys_out, $sys_result);
        if ($sys_result !== RETURN_CODE_SUCCESS) {
            throw new CliException("Failed to extract disc: $drive_id \"$drive_name\" to $disc_dir ($sys_result)", ERRCODE_MAKEMKV_FAILEDDISCEXTRACT);
        }
        // last line reports success and title count
        // TODO : an echo? in a class-method? ewww...
        echo end($sys_out), PHP_EOL;
    }

    /**
     * testInstallation
     *
     * Verify that MakeMKV is installed and available from the command-line
     *
     * @return voic
     */
    private static function testInstallation()
    {
        exec('command -v makemkvcon', $sys_out, $sys_result);
        if ($sys_result !== RETURN_CODE_SUCCESS) {
            throw new CliException('makemkvcon could not be found', ERRCODE_MAKEMKV_NOTFOUND);
        }
    }

    /**
     * getDevices
     *
     * Get list of available devices from makemkvcon
     *
     * @return  array   $devices    Array containing the device descriptions
     *      $devices = [
     *          'index' =>  (string) drive index
     *          'label' =>  (string) disc name
     *      ]
     * @throws  CliException
     */
    private static function getDevices()
    {
        // get all available drives
        exec('makemkvcon -r --cache=1 info disc:9999', $sys_out, $sys_result);
        if ($sys_result === RETURN_CODE_ERR_MKVCON_NOKEY) {
            throw new CliException('The activation key is invalid, please verify you have the newest key in ~/.MakeMKV/settings.conf', ERRCODE_MAKEMKV_NOKEY);
        }
        if ($sys_result !== RETURN_CODE_SUCCESS) {
            throw new CliException('Could not get list of devices', ERRCODE_MAKEMKV_NOINFO);
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
             *  DRV:index,visible,enabled,flags,drive name,disc name
             * jk;lol
             *  DRV:index,drive state,unknown,flags,drive name,disc name,drive source
             */
            list($index, $drive_state, , $fs_flag, $name, $label, $drive) = $device_info;

            if ((int)$drive_state === DRIVESTATE_INSERTED) {
                $active_devices[] = compact('index', 'label');
            }
        }

        return $active_devices;
    }
}
