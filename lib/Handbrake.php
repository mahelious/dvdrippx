<?php

define('ERRCODE_HANDBRAKE_NOOPEN',      31);
define('ERRCODE_HANDBRAKE_NOINFOCMD',   32);
define('ERRCODE_HANDBRAKE_LOCKSET',     35);
define('ERRCODE_HANDBRAKE_LOCKUNSET',   36);
define('ERRCODE_HANDBRAKE_LOCKUNDO',    37);

class Handbrake
{
    private $mkv_file;
    private $preset;
    private $lock_file;
    private $file_locked;
    private $file_size;
    private $output_file;
    private $output_size;

    const PRESET_GENERAL_VERYFAST_1080  = 'Very Fast 1080p30';
    const PRESET_GENERAL_VERYFAST_720   = 'Very Fast 720p30';
    const PRESET_GENERAL_VERYFAST_480   = 'Very Fast 480p30';
    const PRESET_GENERAL_FAST_1080      = 'Fast 1080p30';
    const PRESET_GENERAL_FAST_720       = 'Fast 720p30';
    const PRESET_GENERAL_FAST_480       = 'Fast 480p30';
    const PRESET_GENERAL_HQ_1080        = 'HQ 1080p30 Surround';
    const PRESET_GENERAL_HQ_720         = 'HQ 720p30 Surround';
    const PRESET_GENERAL_HQ_480         = 'HQ 480p30 Surround';
    const PRESET_GENERAL_SUPERHQ_1080   = 'Super HQ 1080p30 Surround';
    const PRESET_GENERAL_SUPERHQ_720    = 'Super HQ 720p30 Surround';
    const PRESET_GENERAL_SUPERHQ_480    = 'Super HQ 480p30 Surround';
    const PRESET_DEVICE_ROKU_2160       = 'Roku 2160p60 4K HEVC Surround';
    const PRESET_DEVICE_CHROMECAST_2160 = 'Chromecast 2160p60 4K HEVC Surround';

    public function __construct($mkv_file)
    {
        $this->mkv_file = $mkv_file;
        $this->preset = self::PRESET_GENERAL_HQ_1080;
        $this->file_size = filesize($mkv_file);
        $this->output_file = null;
        $this->output_size = null;
    }

    /**
     * getSourceFileSize
     *
     * Return the source file size in megabytes
     *
     * @return  float
     */
    public function getSourceFileSize()
    {
        return (int)$this->file_size / 1024 ** 2;
    }

    /**
     * getOutputFileSize
     *
     * Return the output file size in megabytes
     *
     * @return  float
     */
    public function getOutputFileSize()
    {
        return (int)$this->output_size / 1024 ** 2;
    }

    /**
     * selectPreset
     *
     * Select a best fit Handbrake preset based on the source resolution height
     *
     * @return  string
     */
    public function selectPreset()
    {
        // validate that mkvinfo is available
        @exec("command -v mkvinfo", $filepath, $exit_code);
        if (0 !== $exit_code) {
            throw new CliException("Could not find mkvinfo in PATH", ERRCODE_HANDBRAKE_NOINFOCMD);
        }

        // get the video resolution height in pixels
        @exec("mkvinfo $this->mkv_file | grep -oP '(?<=Pixel height: )[0-9]+' | head -n 1", $cmd_out, $cmd_result);
        $pixel_height = (int)$cmd_out;

        // select a preset fit to the source
        if ($pixel_height < 528) {
            return self::PRESET_GENERAL_HQ_480;
        }
        if ($pixel_height < 792) {
            return self::PRESET_GENERAL_HQ_720;
        }
        if ($pixel_height < 1188) {
            return self::PRESET_GENERAL_HQ_1080;
        }
        return self::PRESET_DEVICE_ROKU_2160;
    }

    /**
     * setPreset
     *
     * Determine the preset to use for encoding
     *
     * @param   string  One of the defined Handbrake presets, if falsy a preset will be selected automatically
     */
    public function setPreset($preset = null)
    {
        $this->preset = in_array($preset, self::listAvailablePresets()) ? $preset : $this->selectPreset();
    }

    public function encode()
    {
        if (!is_readable($this->mkv_file)) {
            throw new CliException("{$this->mkv_file} could not be opened, skipping", ERRCODE_HANDBRAKE_NOOPEN);
        }

        // move the file to the processing directory
        if (!$this->addProcessingLock()) {
            return null;
        }

        $output_file = self::getMp4Output($this->lock_file);

        // use HandBrakeCLI to perform encoding
        $encode_cmd = sprintf('HandBrakeCLI -Z "%s" -i "%s" -o "%s" 2>/dev/null', $this->preset, $this->mkv_file, $output_file);
        @exec($encode_cmd, $convert_out, $convert_result);
        if ($convert_result !== 0) {
            echo "Failed to encode $output_file, restoring...", PHP_EOL;
            $this->undoProcessingLock();
            echo "{$this->mkv_file} was restored", PHP_EOL;
            return null;
        }

        $this->cleanupProcessingLock();

        $this->output_size = filesize($output_file);

        return $output_file;
    }

    private function addProcessingLock()
    {
        $lock_file = RIPPX_DIR_PROCESSING . DS . date('YmdHi') . '_' . basename($this->mkv_file);
        if (!rename($this->mkv_file, $lock_file)) {
            throw new CliException(sprintf("Could not move %s into %s", $this->mkv_file, RIPPX_DIR_PROCESSING), ERRCODE_HANDBRAKE_LOCKSET);
        }
        $this->lock_file = $this->mkv_file;
        $this->mkv_file = $lock_file;
        $this->file_locked = true;
        return true;
    }

    private function cleanupProcessingLock()
    {
        if ($this->file_locked) {
            $lock_file = $this->mkv_file;
            $this->mkv_file = $this->lock_file;
            $this->lock_file = null;
            $this->file_locked = false;
            if (!unlink($lock_file)) {
                throw new CliException("$lock_file file could not be removed", ERRCODE_HANDBRAKE_LOCKUNSET);
            }
        }
    }

    private function undoProcessingLock()
    {
        if ($this->file_locked) {
            $lock_file = $this->mkv_file;
            $this->mkv_file = $this->lock_file;
            $this->lock_file = null;
            $this->file_locked = false;
            if (!rename($lock_file, $this->mkv_file)) {
                throw new CliException("Could not restore {$lock_file} to {$this->mkv_file}, halting", ERRCODE_HANDBRAKE_LOCKUNDO);
            }
        }
    }

    /**
     *
     * @param string $mkv_dir
     * @param int $recurse_depth
     * @return Handbrake[]
     */
    public static function getMkvFiles($mkv_dir, $recurse_depth = 0)
    {
        $mkv_files = [];
        // get the contents of the directory, ignoring the current or parent
        $dir_contents = array_diff(scandir($mkv_dir), ['.', '..']);
        // cleanup empty directories from the temp dir
        if (count($dir_contents) < 1) {
            if ($recurse_depth > 0) {
                @rmdir($mkv_dir);
            }
            return [];
        }
        foreach ($dir_contents as $filename) {
            $file = $mkv_dir . DS . $filename;
            if (!is_readable($file)) {
                continue;
            } else
            if (is_dir($file)) {
                $mkv_files = array_merge($mkv_files, self::getMkvFiles($file, $recurse_depth+1));
            } else
            if (is_file($file) && strpos($file, '.mkv') > 0) {
                // check that the file is not accessed by another process
                @exec("lsof -f -- '$file' 2>&1", $sys_output, $is_file_available);
                // $is_file_available is zero if the file is in use by another process
                if ($is_file_available !== 0) {
                    $mkv_files[] = new self($file);
                }
            }
        }
        return $mkv_files;
    }

    public static function getProcessingFiles()
    {
        return array_diff(scandir(RIPPX_DIR_PROCESSING), ['.', '..']);
    }

    /**
     * listAvailablePresets
     *
     * Get a list of the defined presets that Handbrake will support
     *
     * @return  array(string)
     */
    public static function listAvailablePresets()
    {
        return [
            self::PRESET_GENERAL_VERYFAST_480,
            self::PRESET_GENERAL_VERYFAST_720,
            self::PRESET_GENERAL_VERYFAST_1080,
            self::PRESET_GENERAL_FAST_480,
            self::PRESET_GENERAL_FAST_720,
            self::PRESET_GENERAL_FAST_1080,
            self::PRESET_GENERAL_HQ_480,
            self::PRESET_GENERAL_HQ_720,
            self::PRESET_GENERAL_HQ_1080,
            self::PRESET_GENERAL_SUPERHQ_480,
            self::PRESET_GENERAL_SUPERHQ_720,
            self::PRESET_GENERAL_SUPERHQ_1080,
            self::PRESET_DEVICE_ROKU_2160,
            self::PRESET_DEVICE_CHROMECAST_2160,
        ];
    }

    private static function getOutput($input_file)
    {
        $filename_parts = explode(DS, $input_file);
        return verify_path(RIPPX_DIR_OUTPUT_ENCODE . DS . $filename_parts[count($filename_parts)-2]);
    }

    private static function getMp4Output($input_mkv_file)
    {
        $output_dir = self::getOutput($input_mkv_file);
        $output_file = strstr(basename($input_mkv_file), '.mkv', true) . '.mp4';
        return $output_dir . DS . $output_file;
    }
}
