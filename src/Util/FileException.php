<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\Util;

/**
 * Represents a failed file operation.
 */
class FileException extends \RuntimeException {
    /**
     * Constructor.
     *
     * @param string $message Error message.
     * @param array $error Output from error_get_last().
     */
    public function __construct($message, array $error) {
        $this->message = sprintf('%s: %s', $message, $error['message']);

        $this->code = $error['type'];
        $this->file = $error['file'];
        $this->line = $error['line'];
    }
}
