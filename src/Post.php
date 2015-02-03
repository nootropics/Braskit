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
    public $lastbump;
    public $ip = '127.0.0.2';
    public $name = '';
    public $tripcode = '';
    public $email = '';
    public $subject = '';
    public $comment = '';
    public $password = '';
    public $banned = false;

    protected $attributes = [];

    /**
     * @param $attributes array|null
     */
    public function __construct(array $attributes = null) {
        if ($attributes) {
            $this->setAttributes($attributes);
        }
    }

    /**
     * Retrieves an attribute.
     *
     * Attributes are just a way to set various things needed throughout the
     * lifetime of the post object without having an insanely long constructor
     * signature.
     *
     * @return mixed|null The attribute, or NULL if it wasn't set.
     */
    public function getAttribute($attr) {
        if (isset($this->attributes[$attr])) {
            return $this->attributes[$attr];
        }
    }

    /**
     * Sets an attribute.
     */
    public function setAttributes(array $attributes) {
        $this->attributes = $attributes + $this->attributes;
    }

    /**
     * @return \DateTime
     */
    public function getDate() {
        return new \DateTime($this->timestamp);
    }

    /**
     * Retrieve all reports
     *
     * @todo Replace query with a report service or something instead.
     */
    public function getReports() {
        $sth = $this->getAttribute('report_sth');

        if ($sth) {
            $sth->bindValue(':board', $this->board, \PDO::PARAM_STR);
            $sth->bindValue(':id', $this->id, \PDO::PARAM_INT);

            $sth->execute();

            return $sth->fetchAll(\PDO::FETCH_CLASS, 'Braskit\Report');
        }
    }
}
