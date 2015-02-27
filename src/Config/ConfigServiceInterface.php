<?php
/*
 * Copyright (C) 2013, 2014 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\Config;

/**
 * Interface for configuration service. Error handling is left up to the
 * implementation.
 *
 * This interface is enterprise quality.
 *
 * @todo hasDictionary/hasPool/etc.
 */
interface ConfigServiceInterface {
    /**
     * Registers a pool identifier and associates it with a dictionary.
     *
     * @param string $poolName Name of pool
     * @param string $dictName Name of associated dictionary
     */
    public function addPool($poolName, $dictName);

    /**
     * Retrieves a pool.
     *
     * @param string $poolName
     * @param array $poolArgs
     *
     * @return PoolInterface
     */
    public function getPool($poolName, array $poolArgs = []);

    /**
     * Retrieves a dictionary by its name.
     *
     * @param string $dictName Name of dictionary.
     *
     * @throws Error if the dictionary does not exist.
     *
     * @return Dictionary
     */
    public function getDictionary($dictName);

    /**
     * Retrives a dictionary by the name of the pool.
     *
     * @param string $poolName
     *
     * @return Dictionary
     */
    public function getDictionaryByPoolName($poolName);
}
