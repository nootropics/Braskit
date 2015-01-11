<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\Cache;

use Braskit\Util\FileUtils;

/**
 * Cache stored in PHP files.
 */
class FileCache implements CacheInterface {
    /**
     * Cache directory.
     *
     * @var string
     */
    protected $cacheDir = '';

    /**
     * @var FileUtils
     */
    protected $file;

    /**
     * Constructor.
     *
     * @param string $cacheDir Directory to cache to.
     * @param FileUtils $fileUtils
     */
    public function __construct($cacheDir, FileUtils $fileUtils) {
        $this->cacheDir = $cacheDir;
        $this->file = $fileUtils;
    }

    /**
     * Unreliably checks if an object is stored in cache. Does not account for
     * race conditions, does not check expiry.
     */
    public function has($key) {
        $filename = $this->getFileName($key);

        return file_exists($key);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key) {
        $filename = $this->getFileName($key);

        $cache = @include($filename);

        // We couldn't load the cache, or it expired
        if (!is_array($cache) || $cache['expired']) {
            // delete the file if it exists
            $this->file->remove($filename);

            return null;
        }

        return $cache['content'];
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null) {
        if ($value === null) {
            throw new \InvalidArgumentException('Cached value cannot be NULL');
        }

        // Content of the cache file
        $content = '<?php return array(';

        if ($ttl) {
            $expired = sprintf('time() > %d', time() + $ttl);
        } else {
            // the cache never expires
            $expired = 'false';
        }

        $content .= sprintf("'expired' => $expired, ");

        $data = var_export($value, true);
        $content .= sprintf("'content' => %s", $data);

        $content .= ');';

        $this->file->write($this->getFileName($key), $content);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key) {
        $filename = $this->getFileName($key);

        $this->file->remove($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function purge() {
        // get list of cache files
        $files = glob("$this->cacheDir/cache-*.php");

        // that didn't work for some reason
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            $this->file->remove($file);
        }
    }

    /**
     * Get the filename of a cache object.
     *
     * @param string $key Cache key.
     * @return string     File name.
     */
    protected function getFileName($key) {
        return $this->cacheDir.'/cache-'.md5($key).'.php';
    }
}
