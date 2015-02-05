<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit;

/**
 * Class for creating an index of threads.
 */
class ThreadIndex {
    /**
     * @var \PDO
     */
    protected $dbh;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $board;

    /**
     * @var int
     */
    protected $page = 0;

    /**
     * @var int|null
     */
    protected $maxThreads = 10;

    /**
     * @var int|null
     */
    protected $maxReplies = 5;

    /**
     * @var boolean
     */
    protected $initialised = false;

    /**
     * @var Post[][]
     */
    protected $threads;

    /**
     * @var int
     */
    protected $pageCount;

    /**
     * Constructor.
     *
     * @param \PDO $dbh
     * @param string $prefix Table prefix
     */
    public function __construct(\PDO $dbh, $prefix = '') {
        $this->dbh = $dbh;
        $this->prefix = $prefix;
    }

    /**
     * Retrieves the list of threads.
     *
     * @return Post[][]
     */
    public function getThreads() {
        if (!$this->initialised) {
            $this->initialise();
        }

        return $this->threads;
    }

    /**
     * Retrieves the number pages in the index, starting from 1.
     *
     * @return int
     */
    public function getPageCount() {
        if (!$this->initialised) {
            $this->initialise();
        }

        return $this->pageCount;
    }

    /**
     * Sets the board.
     *
     * @param Board $board
     */
    public function setBoard(Board $board) {
        $this->initialised = false;

        $this->board = (string)$board;
    }

    /**
     * Sets the page of the index.
     *
     * @param int $page
     */
    public function setPage($page) {
        $this->initialised = false;

        $this->page = abs($page);
    }

    /**
     * Sets the maximum number of replies to fetch for each thread.
     *
     * @param int $maxReplies
     */
    public function setMaxReplies($maxReplies) {
        $this->initialised = false;

        $this->maxReplies = floor(abs($maxReplies)) ?: null;
    }

    /**
     * Sets the maximum number of threads to fetch.
     *
     * @param int $maxThreads
     */
    public function setMaxThreads($maxThreads) {
        $this->initialised = false;

        $this->maxThreads = floor(abs($maxThreads)) ?: null;
    }

    /**
     * Initialises the index and page count. This is done under the same
     * transaction in order to prevent race conditions.
     */
    protected function initialise() {
        // start a new transaction - this is necessary for the isolation stuff
        $this->dbh->beginTransaction();

        // protect against race conditions - this ensures that the state of the
        // database will be the exact same until the end of the transaction
        $this->dbh->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        try {
            // initialise all the stuff
            $this->initialiseThreadIndex();
            $this->initialisePageCount();
        } catch (\Exception $e) {
            // something went wrong, end the transaction
            $this->dbh->rollBack();

            throw $e;
        }

        // end the transaction
        $this->dbh->rollBack();

        $this->initialised = true;
    }

    /**
     * Initialises the thread index.
     */
    protected function initialiseThreadIndex() {
        // query for selecting threads
        $threadSth = $this->getThreadSth();
        $threadSth->setFetchMode(\PDO::FETCH_CLASS, 'Braskit\Post');

        // query for selecting replies
        $replySth = $this->getReplySth();
        $replySth->setFetchMode(\PDO::FETCH_CLASS, 'Braskit\Post');

        $threads = [];

        while ($op = $threadSth->fetch()) {
            $thread = [$op];

            $replySth->bindValue(':board', $op->board, \PDO::PARAM_STR);
            $replySth->bindValue(':parent', $op->id, \PDO::PARAM_INT);

            $replySth->execute();

            while ($reply = $replySth->fetch()) {
                $thread[] = $reply;
            }

            $threads[] = $thread;
        }

        $this->threads = $threads;
    }

    /**
     * Initialises the page count.
     */
    protected function initialisePageCount() {
        if (!$this->maxThreads) {
            // only one page if pagination isn't active
            return 1;
        }

        $sth = $this->dbh->prepare("
            SELECT COUNT(*) FROM {$this->prefix}posts
                WHERE
                    CASE WHEN :board::text IS NOT NULL THEN
                        board = :board AND parent = 0
                    ELSE
                        parent = 0
                    END
        ");

        $sth->bindValue(':board', $this->board, \PDO::PARAM_STR);

        $sth->execute();

        $count = $sth->fetchColumn();

        $this->pageCount = ceil($count / $this->maxThreads) ?: 1;
    }

    /**
     * Retrieves a statement handle for fetching threads.
     *
     * The statement has been executed.
     *
     * @return \PDOStatement
     */
    protected function getThreadSth() {
        $sth = $this->dbh->prepare("
            SELECT *,
                CASE WHEN :r_limit::integer > 0 THEN
                    (SELECT CASE WHEN q.count > 0 THEN q.count ELSE 0 END FROM (
                        SELECT COUNT(*) - :r_limit AS count
                            FROM {$this->prefix}posts
                            WHERE board = p.board AND parent = p.id
                    ) AS q)
                ELSE
                    NULL
                END AS omitted
                FROM {$this->prefix}posts_view AS p
                WHERE
                    CASE WHEN :board::text IS NOT NULL THEN
                        board = :board AND parent = 0
                    ELSE
                        parent = 0
                    END
                ORDER BY lastbump DESC, id DESC
                OFFSET :offset
                LIMIT :limit
        ");

        $offset = $this->maxThreads * $this->page;

        $sth->bindValue(':board', $this->board, \PDO::PARAM_STR);
        $sth->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $sth->bindValue(':limit', $this->maxThreads, \PDO::PARAM_INT);
        $sth->bindValue(':r_limit', $this->maxReplies, \PDO::PARAM_INT);

        $sth->execute();

        return $sth;
    }

    /**
     * Retrieves a database statement handle for retrieving reply posts.
     *
     * The handle takes the following bindings:
     *
     *   - :board - string - The board.
     *   - :parent - int - The parent post.
     *   - :limit - int - The number of replies to fetch (already set)
     *
     * @return \PDOStatement
     */
    protected function getReplySth() {
        $sth = $this->dbh->prepare("
            SELECT * FROM (
                SELECT * FROM
                    {$this->prefix}posts_view
                    WHERE board = :board AND parent = :parent
                    ORDER BY id DESC
                    LIMIT :limit
            ) AS q
                ORDER BY id ASC
        ");

        $sth->bindValue(':limit', $this->maxReplies, \PDO::PARAM_INT);

        return $sth;
    }
}
