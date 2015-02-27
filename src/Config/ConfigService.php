<?php
/*
 * Copyright (C) 2013, 2014 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\Config;

use Braskit\Cache\CacheInterface;
use Braskit\Database;
use Braskit\Error;

/**
 * Configuration service.
 *
 * This implementation allows for lazy-loading of dictionaries.
 */
final class ConfigService implements ConfigServiceInterface {
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Database
     */
    private $db;

    /**
     * Array of dictionary loaders.
     *
     * @var array
     */
    private $dictionaryLoaders = [];

    /**
     * Constructor.
     *
     * @var CacheInterface $cache
     * @var Database $db
     */
    public function __construct(CacheInterface $cache, Database $db) {
        $this->cache = $cache;
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function addPool($poolName, $dictName) {
        if (isset($this->pools[$poolName])) {
            // the pool has already been defined
            $msg = "Pool '$poolName' cannot be re-registered";
            throw new \InvalidArgumentException($msg);
        }

        $argc = substr_count($poolName, '%');

        $this->pools[$poolName] = [
            'arguments' => $argc,
            'dictionary' => $dictName,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPool($poolName, array $poolArgs = []) {
        if (!isset($this->pools[$poolName])) {
            throw new \InvalidArgumentException("Invalid pool");
        }

        $ac = count($poolArgs); // number of given args
        $pc = $this->pools[$poolName]['arguments']; // number of required args

        if ($ac !== $pc) {
            $msg = "The pool requires $pc arguments, but only $ac were given";
            throw new \InvalidArgumentException($msg);
        }

        return new Pool($poolName, $poolArgs, $this, $this->cache, $this->db);
    }

    /**
     * {@inheritdoc}
     */
    public function getDictionary($dictName) {
        foreach ($this->dictionaryLoaders as $loader) {
            if ($loader->hasDictionary($dictName)) {
                return $loader->getDictionary($dictName);
            }
        }

        throw new Error("No dictionary '$dictName' exists");
    }

    /**
     * {@inheritdoc}
     */
    public function getDictionaryByPoolName($poolName) {
        if (!isset($this->pools[$poolName])) {
            throw new \InvalidArgumentException("Invalid pool");
        }

        return $this->getDictionary($this->pools[$poolName]['dictionary']);
    }

    /**
     * Registers a dictionary loader.
     *
     * @param DictionaryLoaderInterface $loader
     */
    public function addDictionaryLoader(DictionaryLoaderInterface $loader) {
        array_unshift($this->dictionaryLoaders, $loader);
    }
}
