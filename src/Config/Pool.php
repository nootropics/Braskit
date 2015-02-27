<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\Config;

use Braskit\Cache\CacheInterface;
use Braskit\Database;

/**
 * Default config pool implementation.
 *
 * This implementation is dictionary-agnostic when reading values--only when
 * modifying options in the pool does it load the dictionary.
 */
final class Pool implements PoolInterface {
    /**
     * Pool name/identifier
     *
     * @var string
     */
    private $name;

    /**
     * Pool arguments
     *
     * @var array
     */
    private $args;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Database
     */
    private $db;

    /**
     * Whether the cache has been initialised or not.
     *
     * @var boolean
     */
    private $initialised = false;

    /**
     * Cached key/value pairs for this pool.
     *
     * Structure:
     *
     * [
     *   'some_key' => [
     *     'value' => 'some value 1234567',
     *     'modified' => true,
     *   ],
     *   'some_other_value' => [
     *     'value' => 6.12345,
     *     'modified' => false,
     *   ],
     * ]
     *
     * @var array
     */
    private $cachedPool;

    /**
     * Key for retrieving/storing the pool's options in cache.
     *
     * @var string
     */
    private $cacheKey;

    /**
     * Dictionary. Lazy-loaded, use $this->getDict() instead.
     *
     * @var Dictionary|null
     */
    private $dict;

    /**
     * Constructor.
     *
     * @param string $name   The name of this pool.
     * @param array $args    The arguments for this pool instance.
     * @param ConfigServiceInterface $service
     * @param CacheInterface
     * @param Database
     */
    public function __construct(
        $name,
        array $args,
        ConfigServiceInterface $service,
        CacheInterface $cache,
        Database $db
    ) {
        $this->name = $name;
        $this->args = $args;
        $this->service = $service;
        $this->cache = $cache;
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key) {
        if (!$this->initialised) {
            $this->initialiseCache();
        }

        return isset($this->cachedPool[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key) {
        if (!$this->initialised) {
            $this->initialiseCache();
        }

        if (!isset($this->cachedPool[$key])) {
            throw new \InvalidArgumentException("No such key: '$key'");
        }

        return $this->cachedPool[$key]['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value) {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * {@inheritdoc}
     */
    public function reset($key) {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * {@inheritdoc}
     */
    public function isModified($key) {
        if (!$this->initialised) {
            $this->initialiseCache();
        }

        if (!isset($this->cachedPool[$key])) {
            throw new \InvalidArgumentException("No such key: '$key'");
        }

        return $this->cachedPool[$key]['modified'];
    }

    /**
     * {@inheritdoc}
     */
    public function commit() {
        throw new \LogicException('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator() {
        return new PoolIterator($this->getDict(), $this);
    }

    /**
     * Sets up the cache so we can look up stuff fast.
     */
    private function initialiseCache() {
        $cacheKey = $this->getCacheKey();

        // retrieve cache
        $this->cachedPool = $this->cache->get($cacheKey);

        if (!is_array($this->cachedPool)) {
            // no cache - we need to build it
            $this->cachedPool = [];

            $dict = $this->getDict();

            // get the options in the db
            $db = $this->db->getPoolOptions($this->name, $this->args);

            foreach ($db as $option) {
                // set to value from db
                $this->cachedPool[$option->key]['value'] = $option->value;

                // assume that if an option is stored in the database, its value
                // deviates from that of the dictionary's. this behaviour ought
                // to be changed, as the database cannot enforce that stored
                // values be non-default.
                $this->cachedPool[$option->key]['modified'] = true;
            }

            // add remaining options using the dictionary
            foreach ($dict->getKeys() as $key) {
                if (!isset($this->cachedPool[$key])) {
                    // set to default value
                    $this->cachedPool[$key]['value'] = $dict->getDefault($key);

                    // if it's from the dictionary, it's unmodified
                    $this->cachedPool[$key]['modified'] = false;
                }
            }

            // save the generated cache
            $this->cache->set($cacheKey, $this->cachedPool);
        }

        // don't run this again
        $this->initialised = true;
    }

    /**
     * Creates a caching key for the pool.
     *
     * @return string The key
     */
    private function getCacheKey() {
        if (!isset($this->cacheKey)) {
            // combine pool name and args into one array
            $args = array_merge([$this->name], $this->args);

            // urlencode encodes pipes, leaving the pipe character available as
            // a safe separator
            $key = sha1(implode('|', array_map('urlencode', $args)));

            $this->cacheKey = 'config_'.$key;
        }

        return $this->cacheKey;
    }

    /**
     * Retrieves the dictionary for the pool.
     *
     * @return Dictionary
     */
    private function getDict() {
        if (!isset($this->dict)) {
            $this->dict = $this->service->getDictionaryByPoolName($this->name);
        }

        return $this->dict;
    }
}
