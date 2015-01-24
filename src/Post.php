<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit;

class Post extends FileMetaData {
    public $globalid = 0;
    public $id = 0;
    public $parent = 0;
    public $board = '';
    public $timestamp;
    public $lastbump = 0;
    public $ip = '127.0.0.2';
    public $name = '';
    public $tripcode = '';
    public $email = '';
    public $subject = '';
    public $comment = '';
    public $password = '';
    public $banned = false;
    public $reports = null;

    /**
     * @return \DateTime
     */
    public function getDate() {
        return new \DateTime($this->timestamp);
    }
}
