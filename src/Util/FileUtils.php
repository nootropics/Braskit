<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\Util;

/**
 * Common filesystem utilities.
 */
class FileUtils {
    protected $tempDir = null;

    /**
     * Atomic file writing.
     *
     * @throws FileException if the file couldn't be written.
     *
     * @param string $dest The filename.
     * @param string|resource $contents The contents to write.
     * @param boolean $createDirs Create the directories if true.
     */
    public function write($dest, $contents, $createDirs = true) {
        if ($createDirs) {
            $dirname = dirname($dest);

            // lazy, but avoids a potential race condition
            @mkdir($dirname, 0777, true);
        }

        // create the temporary file
        $tmpfile = tempnam($this->getTempDir(), 'bs_');

        // write to the temporary file - $bytes will be false if this fails
        $bytes = @file_put_contents($tmpfile, $contents);

        if ($bytes === false) {
            $error = error_get_last();

            @unlink($tmpfile);

            throw new FileException("Couldn't write temporary file", $error);
        }

        // attempt to move the temporary file to the destination
        if (!@rename($tmpfile, $dest)) {
            // if renaming failed, copy and delete instead
            if (!@copy($tmpfile, $dest)) {
                // both copying and renaming failed. get the copy() error
                $error = error_get_last();

                @unlink($tmpfile);
                @unlink($dest); // can the destination exist if copy() fails???

                throw new FileException("Couldn't write file", $error);
            }

            @unlink($tmpfile);
        }

        // php creates files using 0600 permissions for some reason
        @chmod($dest, 0666 & ~umask());
    }

    /**
     * Removes a file.
     *
     * @param string $filename File to remove.
     * @param boolean $silent Don't throw an exception if true. Recommended, as
     *                        other processes may have removed the desired file
     *                        already, thus leading to a race condition.
     *
     * @throws FileException if $silent is false and the operation failed
     */
    public function remove($filename, $silent = true) {
        $success = @unlink($filename);

        if (!$silent && !$success) {
            $error = error_get_last();

            throw new FileException("Couldn't remove file", $error);
        }
    }

    /**
     * Retrieve the temporary directory.
     *
     * @return string
     */
    public function getTempDir() {
        if ($this->tempDir === null) {
            $this->tempDir = sys_get_temp_dir();
        }
    }

    /**
     * Sets the temporary directory. The directory must exist.
     *
     * @param string $dir The directory.
     */
    public function setTempDir($dir) {
        $this->tempDir = $dir;
    }
}
