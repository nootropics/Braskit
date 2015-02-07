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

    /**
     * @var PostService
     */
    protected $service;

    /**
     * @param $attributes array|null
     */
    public function __construct(PostService $service = null) {
        $this->service = $service;
    }

    /**
     * @return \DateTime
     */
    public function getDate() {
        return new \DateTime($this->timestamp);
    }

    /**
     * Retrieve all files associated with the post.
     *
     * @return FileMetaData[]
     */
    public function getFiles() {
        $this->requireService();

        $sth = $this->service->getFileSth();

        $sth->bindValue(':board', $this->board, \PDO::PARAM_STR);
        $sth->bindValue(':id', $this->id, \PDO::PARAM_INT);

        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_CLASS, 'Braskit\FileMetaData');
    }

    /**
     * Retrieve all reports associated with the post.
     *
     * @return Report[]
     */
    public function getReports() {
        $this->requireService();

        $sth = $this->service->getReportSth();

        $sth->bindValue(':board', $this->board, \PDO::PARAM_STR);
        $sth->bindValue(':id', $this->id, \PDO::PARAM_INT);

        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_CLASS, 'Braskit\Report');
    }

    /**
     * @throws \RuntimeException if no service is defined for the board.
     */
    private function requireService() {
        if (!$this->service) {
            // lol wording
            throw new \RuntimeException('The board object has no service');
        }
    }
}
