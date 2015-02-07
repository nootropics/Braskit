<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit;

class PostService {
    /**
     * @var \PDO
     */
    protected $dbh;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var \PDOStatement
     */
    protected $fileSth;

    /**
     * @var \PDOStatement
     */
    protected $reportSth;

    /**
     * Constructor.
     *
     * @param \PDO $dbh Database connection handle
     * @param string $prefix Database table prefix
     */
    public function __construct(\PDO $dbh, $prefix = '') {
        $this->dbh = $dbh;
        $this->prefix = $prefix;
    }

    /**
     * Get statement handle for retrieving files associated with a post.
     *
     * The handle takes two bindings:
     *
     *   - :board - string - The board
     *   - :id - int - The ID of the post
     *
     * @return \PDOStatement
     */
    public function getFileSth() {
        if (!$this->fileSth) {
            $sth = $this->dbh->prepare("
                SELECT * FROM {$this->prefix}files
                    WHERE board = :board AND postid = :id
            ");

            $sth->setFetchMode(\PDO::FETCH_CLASS, 'Braskit\FileMetaData');

            $this->fileSth = $sth;
        }

        return $this->fileSth;
    }

    public function getReportSth() {
        if (!$this->reportSth) {
            $sth = $this->dbh->prepare("
                SELECT * FROM {$this->prefix}reports
                    WHERE board = :board AND postid = :id
            ");

            $sth->setFetchMode(\PDO::FETCH_CLASS, 'Braskit\Report');

            $this->reportSth = $sth;
        }

        return $this->reportSth;
    }
}
