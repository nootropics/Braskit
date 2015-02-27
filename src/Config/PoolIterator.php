<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\Config;

final class PoolIterator implements \Iterator {
    /**
     * @var array
     */
    private $keys;

    /**
     * Iterator position.
     *
     * @var int
     */
    private $pos = 0;

    /**
     * @var DictionaryInterface
     */
    private $dict;

    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * Constructor.
     *
     * @param DictionaryInterface $dict
     * @param PoolInterface $pool
     */
    public function __construct(DictionaryInterface $dict, PoolInterface $pool) {
        $this->keys = $dict->getKeys();
        $this->dict = $dict;
        $this->pool = $pool;
    }

    public function key() {
        return $this->keys[$this->pos];
    }

    public function current() {
        return new Option($this->keys[$this->pos], $this->dict, $this->pool);
    }

    public function rewind() {
        $this->pos = 0;
    }

    public function next() {
        $this->pos += 1;
    }

    public function valid() {
        return isset($this->keys[$this->pos]);
    }
}
