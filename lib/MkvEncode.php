<?php

// a selection of HandBrake v.1.2.2 presets
define('PRESET_GENERAL_VERYFAST_1080',  'Very Fast 1080p30');
define('PRESET_GENERAL_VERYFAST_720',   'Very Fast 720p30');
define('PRESET_GENERAL_VERYFAST_480',   'Very Fast 480p30');
define('PRESET_GENERAL_FAST_1080',      'Fast 1080p30');
define('PRESET_GENERAL_FAST_720',       'Fast 720p30');
define('PRESET_GENERAL_FAST_480',       'Fast 480p30');
define('PRESET_GENERAL_HQ_1080',        'HQ 1080p30 Surround');
define('PRESET_GENERAL_HQ_720',         'HQ 720p30 Surround');
define('PRESET_GENERAL_SUPERHQ_1080',   'Super HQ 1080p30 Surround');
define('PRESET_GENERAL_SUPERHQ_720',    'Super HQ 720p30 Surround');

class MkvEncode
{
    private $mkv_file;
    private $lock_file;
    private $file_locked;

    public function __construct($mkv_file)
    {
        $this->mkv_file = $mkv_file;
    }

    public function encode($encode_preset = PRESET_GENERAL_FAST_1080)
    {
        if (!is_readable($this->mkv_file)) {
            throw new CliException("{$this->mkv_file} could not be opened, skipping");
        }

        // move the file to the processing directory
        if (!$this->addProcessingLock()) {
            return null;
        }

        $filename_parts = explode(DS, $this->mkv_file);
        $filename = $filename_parts[count($filename_parts)-1];
        $dirname = $filename_parts[count($filename_parts)-2];
        $output_dir = RIPX_RIP_OUTPUT_DIR . DS . $dirname;
        if (!is_dir($output_dir) && !mkdir($output_dir)) {
            throw new CliException("Could not initialize output directory: $output_dir", 8);
        }
        $output_filename = $output_dir . DS . strstr($filename, '.mkv', true) . '.mp4';

        // use HandBrakeCLI to perform encoding
        $encode_cmd = sprintf('HandBrakeCLI -Z "%s" -i "%s" -o "%s"', $encode_preset, $this->mkv_file, $output_filename);
        @exec($encode_cmd, $convert_out, $convert_result);
        if ($convert_result !== 0) {
            echo "Failed to encode $output_filename, restoring...", PHP_EOL;
            $this->undoProcessingLock();
            echo "{$this->mkv_file} was restored", PHP_EOL;
            return null;
        }

        $this->cleanupProcessingLock();

        return $output_filename;
    }

    private function addProcessingLock()
    {
        if (!is_writable(RIPX_PROCESSING_DIR)) {
            throw new CliException('Check that ' . RIPX_PROCESSING_DIR . ' exists and can be written to.', 16);
        }
        $lock_file = RIPX_PROCESSING_DIR . DS . date('YmdHi') . '_' . basename($this->mkv_file);
        if (!rename($this->mkv_file, $lock_file)) {
            throw new CliException(sprintf("Could not move %s into %s", $this->mkv_file, RIPX_PROCESSING_DIR), 32);
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
                throw new CliException("$lock_file file could not be removed", 64);
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
                throw new CliException("Could not restore {$lock_file} to {$this->mkv_file}, halting", 4);
            }
        }
    }

    /**
     *
     * @param string $mkv_dir
     * @param int $recurse_depth
     * @return MkvEncode[]
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
}
