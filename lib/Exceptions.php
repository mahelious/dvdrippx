<?php

/**
 * Custom project Exceptions
 */

/**
 * Exception for *nix processes
 */
class CliException extends Exception
{
    public function __construct($message = '', $code = 1, $previous = null)
    {
        parent::__construct($message, $code, $previous);
        // Exception::code defaults to 0 in PHP, however 0 indicates success in *nix; default to 1 to indicate some runtime error
        if (empty($this->code)) {
            $this->code = 1;
        }
    }
}
