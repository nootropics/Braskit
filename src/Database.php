<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit;

use PDO;

class Database {
    protected $dbh;
    protected $prefix;

    public function __construct(PDO $dbh, $prefix) {
        $this->dbh = $dbh;
        $this->prefix = $prefix;
    }


    //
    // General shit
    //

    protected function boardExists($board) {
        $sth = $this->dbh->prepare("SELECT 1 FROM {$this->prefix}boards WHERE name = ?");
        $sth->execute(array($board));

        return (bool)$sth->fetchColumn();
    }

    public function initDatabase() {
        global $app;

        $schema = file_get_contents($app['path.root'].'/config/schema.sql');
        $schema = str_replace('/*_*/', $this->prefix, $schema);

        // postgres complains otherwise - this lets us execute multiple queries
        $this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $this->dbh->query($schema);

        $this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }


    //
    // Post functions
    //

    public function postByID($board, $id) {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}posts_view WHERE board = :board AND id = :id");

        $sth->bindParam(':board', $board);
        $sth->bindParam(':id', $id);

        $sth->execute();

        $sth->setFetchMode(PDO::FETCH_CLASS, 'Braskit\\Post');

        return $sth->fetch();
    }

    public function threadExistsByID($board, $id) {
        $sth = $this->dbh->prepare("SELECT 1 FROM {$this->prefix}posts WHERE board = :board AND id = :id AND parent = 0");

        $sth->bindParam(':board', $board);
        $sth->bindParam(':id', $id);

        $sth->execute();

        return (bool)$sth->fetchColumn();
    }

    public function insertPost($post) {
        $sth = $this->dbh->prepare("INSERT INTO {$this->prefix}posts (parent, board, timestamp, ip, name, tripcode, email, subject, comment, password) VALUES (:parent, :board, to_timestamp(:timestamp), :ip, :name, :tripcode, :email, :subject, :comment, :password) RETURNING id");

        $sth->bindParam(':parent', $post->parent, PDO::PARAM_INT);
        $sth->bindParam(':board', $post->board, PDO::PARAM_STR);
        $sth->bindParam(':timestamp', $post->timestamp, PDO::PARAM_INT);
        $sth->bindParam(':ip', $post->ip, PDO::PARAM_STR);
        $sth->bindParam(':name', $post->name, PDO::PARAM_STR);
        $sth->bindParam(':tripcode', $post->tripcode, PDO::PARAM_STR);
        $sth->bindParam(':email', $post->email, PDO::PARAM_STR);
        $sth->bindParam(':subject', $post->subject, PDO::PARAM_STR);
        $sth->bindParam(':comment', $post->comment, PDO::PARAM_STR);
        $sth->bindParam(':password', $post->password, PDO::PARAM_STR);

        $sth->execute();

        // assign the new ID to the post - all the other information is known
        $post->id = $sth->fetchColumn();
    }

    public function bumpThreadByID($board, $id) {
        $sth = $this->dbh->prepare("UPDATE {$this->prefix}posts SET lastbump = to_timestamp(:time) WHERE board = :board AND (id = :id OR parent = :id)");

        $sth->bindValue(':board', $board, PDO::PARAM_STR);
        $sth->bindValue(':id', $id, PDO::PARAM_INT);
        $sth->bindValue(':time', time(), PDO::PARAM_INT);

        $sth->execute();
    }

    public function postsInThreadByID($board, $id) {
        if (!$id)
            return false;

        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}posts_view WHERE board = :board AND (id = :id OR parent = :id) ORDER BY id ASC");

        $sth->bindParam(':board', $board, PDO::PARAM_STR);
        $sth->bindParam(':id', $id, PDO::PARAM_INT);

        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\Post');
    }

    public function postByMD5($board, $md5) {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}posts_view WHERE board = :board AND md5 = :md5 LIMIT 1");
        $sth->bindParam(':board', $board, PDO::PARAM_STR);
        $sth->bindParam(':md5', $md5, PDO::PARAM_STR);
        $sth->execute();

        $sth->setFetchMode(PDO::FETCH_CLASS, 'Braskit\\Post');

        return $sth->fetch();
    }

    public function deletePostByID($board, $id, $password = null) {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}delete_post(:board, :id, :password)");

        $sth->bindParam(':board', $board, PDO::PARAM_STR);
        $sth->bindParam(':id', $id, PDO::PARAM_INT);
        $sth->bindParam(':password', $password, PDO::PARAM_STR);

        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\Post');
    }

    public function trimPostsByThreadCount($board, $max_threads) {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}trim_board(:board, :offset)");

        $sth->bindParam(':board', $board, PDO::PARAM_STR);
        $sth->bindParam(':offset', $max_threads, PDO::PARAM_INT);

        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\Post');
    }


    //
    // File functions
    //

    public function insertFile(File $file, Post $post) {
        $sth = $this->dbh->prepare("INSERT INTO {$this->prefix}files (postid, board, file, md5, origname, shortname, filesize, prettysize, width, height, thumb, t_width, t_height) VALUES (:postid, :board, :file, :md5, :origname, :shortname, :filesize, :prettysize, :width, :height, :thumb, :t_width, :t_height)");

        $sth->bindParam(':postid', $post->id, PDO::PARAM_INT);
        $sth->bindParam(':board', $post->board, PDO::PARAM_STR);
        $sth->bindParam(':file', $file->filename);
        $sth->bindParam(':md5', $file->md5);
        $sth->bindParam(':origname', $file->origname);
        $sth->bindParam(':shortname', $file->shortname);
        $sth->bindParam(':filesize', $file->size);
        $sth->bindParam(':prettysize', $file->prettysize);
        $sth->bindParam(':width', $file->width);
        $sth->bindParam(':height', $file->height);
        $sth->bindParam(':thumb', $file->t_filename);
        $sth->bindParam(':t_width', $file->t_width);
        $sth->bindParam(':t_height', $file->t_height);

        $sth->execute();
    }


    //
    // Cross-board functions
    //

    public function getLatestPosts($limit) {
        if ($limit < 1)
            $limit = 1;

        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}posts_view ORDER BY id DESC LIMIT :limit");
        $sth->bindParam(':limit', $limit, PDO::PARAM_INT);
        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\Post');
    }


    //
    // Ban functions
    //

    public function banByID($id) {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}bans_view WHERE id = ?");
        $sth->execute(array($id));

        return $sth->fetch();
    }

    public function bansByIP($ip) {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}bans_view WHERE ip >>= ? ORDER BY timestamp DESC");
        $sth->execute(array($ip));

        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\Ban');
    }

    public function activeBansByIP($ip, $time) {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}bans_view WHERE ip >>= :ip AND (expire IS NULL OR expire > to_timestamp(:time)) ORDER BY timestamp DESC");

        $sth->bindParam(':ip', $ip, PDO::PARAM_STR);
        $sth->bindParam(':time', $time, PDO::PARAM_INT);

        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\Ban');
    }

    public function allBans() {
        $sth = $this->dbh->query("SELECT * FROM {$this->prefix}bans_view ORDER BY timestamp DESC");
        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\Ban');
    }

    public function insertBan(Ban $ban) {
        $sth = $this->dbh->prepare("INSERT INTO {$this->prefix}bans (ip, timestamp, expire, reason) VALUES (network(:ip), to_timestamp(:time), to_timestamp(:expire), :reason)");

        $sth->bindParam(':ip', $ban->ip);
        $sth->bindParam(':time', $ban->timestamp);
        $sth->bindParam(':expire', $ban->expire);
        $sth->bindParam(':reason', $ban->reason);

        $sth->execute();

        return $this->dbh->lastInsertID($this->prefix.'bans_id_seq');
    }

    public function clearExpiredBans() {
        $sth = $this->dbh->prepare("DELETE FROM {$this->prefix}bans WHERE expire > 0 AND expire <= ?");
        $sth->execute(array(time()));
    }

    public function deleteBanByID($id) {
        $sth = $this->dbh->prepare("DELETE FROM {$this->prefix}bans WHERE id = :id");
        $sth->bindParam(':id', $id, PDO::PARAM_INT);
        $sth->execute();
    }

    //
    // Board functions
    //

    public function createBoard($board, $longname) {
        $sth = $this->dbh->prepare("INSERT INTO {$this->prefix}boards (name, longname, minlevel, lastid) VALUES (?, ?, 0, 0)");
        $sth->execute(array($board, $longname));
    }

    public function getBoard($board) {
        $sth = $this->dbh->prepare("SELECT longname, minlevel FROM {$this->prefix}boards WHERE name = ?");
        $sth->execute(array($board));

        return $sth->fetch();
    }

    public function getAllBoards() {
        $sth = $this->dbh->query("SELECT name, longname, minlevel FROM {$this->prefix}boards ORDER BY name ASC");

        return $sth->fetchAll();
    }

    public function renameBoard($oldname, $newname) {
        // since we're using cascading and foreign keys, pgsql will handle the
        // other tables containing board names for us automagically.
        $sth = $this->dbh->prepare("UPDATE {$this->prefix}boards SET name = ? WHERE name = ?");
        $sth->execute(array($newname, $oldname));
    }

    public function updateBoard($board, $new_title, $new_level) {
        $sth = $this->dbh->prepare("UPDATE {$this->prefix}boards SET longname = ?, minlevel = ? WHERE name = ?");
        $sth->execute(array($new_title, $new_level, $board));
    }


    //
    // Flood
    //

    /**
     * @todo Add a better way of detecting duplicate text
     */
    public function checkDuplicateText($comment_hex, $max) {
        $sth = $this->dbh->prepare("SELECT 1 FROM {$this->prefix}posts WHERE comment = :comment AND timestamp > to_timestamp(:max)");
        $sth->bindParam(':comment', $comment, PDO::PARAM_STR);
        $sth->bindParam(':max', $max, PDO::PARAM_INT);
        $sth->execute(array($comment_hex, $max));

        return (bool)$sth->fetchColumn();
    }

    public function checkFlood($ip, $max) {
        $sth = $this->dbh->prepare("SELECT 1 FROM {$this->prefix}posts WHERE ip = :ip AND timestamp > to_timestamp(:max)");
        $sth->bindParam(':ip', $ip, PDO::PARAM_STR);
        $sth->bindParam(':max', $max, PDO::PARAM_INT);
        $sth->execute();

        return (bool)$sth->fetchColumn();
    }

    public function checkImageFlood($ip, $max) {
        $sth = $this->dbh->prepare("SELECT 1 FROM {$this->prefix}posts_view WHERE fileid IS NOT NULL AND ip = :ip AND timestamp > to_timestamp(:max)");
        $sth->bindParam(':ip', $ip, PDO::PARAM_STR);
        $sth->bindParam(':max', $max, PDO::PARAM_INT);
        $sth->execute();

        return (bool)$sth->fetchColumn();
    }


    //
    // Config
    //

    public function getPoolOptions($pool, array $args) {
        // convert args to json array string
        $args = json_encode($args);

        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}config WHERE pool = :pool AND to_json(args)::jsonb = :args::jsonb ORDER BY key");

        $sth->bindValue(':pool', $pool, PDO::PARAM_STR);
        $sth->bindValue(':args', $args, PDO::PARAM_STR);

        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\Config');
    }

    //
    // Reporting
    //

    public function countReports() {
        $sth = $this->dbh->query("SELECT COUNT(*) FROM {$this->prefix}reports");

        return $sth->fetchColumn();
    }

    public function getReports() {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}reports ORDER BY id");
        $sth->execute(array());

        return $sth->fetch();
    }

    public function getReportsByIP() {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}reports ORDER BY id WHERE ip << ?");
        $sth->execute(array($ip));

        return $sth->fetch();
    }

    public function insertReports($posts, $report) {
        $sth = $this->dbh->prepare("INSERT INTO {$this->prefix}reports (postid, board, ip, timestamp, reason) VALUES (:id, :board, :ip, to_timestamp(:time), :reason) RETURNING id");
        $sth->bindParam(':board', $report['board']);
        $sth->bindParam(':ip', $report['ip']);
        $sth->bindParam(':time', $report['time']);
        $sth->bindParam(':reason', $report['reason']);

        $report_ids = array();

        foreach ($posts as $post) {
            $sth->bindParam(':id', $post->id);
            $report_ids[] = $sth->execute();
        }

        return $report_ids;
    }

    public function checkReportFlood($ip, $max) {
        $sth = $this->dbh->prepare("SELECT COUNT(*) FROM {$this->prefix}reports WHERE ip <<= :ip AND timestamp > to_timestamp(:time)");
        $sth->bindParam(':ip', $ip);
        $sth->bindParam(':time', $max);
        $sth->execute();

        // the user is flooding if true
        return (bool)$sth->fetchColumn();
    }

    public function dismissReports($ids) {
        $this->dbh->beginTransaction();

        $sth = $this->dbh->prepare("DELETE FROM {$this->prefix}reports WHERE id = :id");

        foreach ($ids as $id) {
            $sth->bindParam(':id', $id, PDO::PARAM_INT);
            $sth->execute();
        }

        $this->dbh->commit();
    }


    //
    // Spam
    //

    public function getLatestSpamRules() {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}spam ORDER BY id DESC LIMIT 1");
        $sth->execute();

        return $sth->fetch();
    }


    //
    // Users
    //

    public function getUserList() {
        $sth = $this->dbh->query("SELECT username, level, lastlogin, email FROM {$this->prefix}users ORDER BY level DESC, username");

        return $sth->fetchAll(PDO::FETCH_CLASS, 'Braskit\\User');
    }

    public function getUser($username) {
        $sth = $this->dbh->prepare("SELECT * FROM {$this->prefix}users WHERE username = :username");
        $sth->bindParam(':username', $username, PDO::PARAM_STR);
        $sth->execute();

        $sth->setFetchMode(PDO::FETCH_CLASS, 'Braskit\\User');

        return $sth->fetch();
    }

    public function insertUser(User $user) {
        $sth = $this->dbh->prepare("INSERT INTO {$this->prefix}users (username, password, level, email, capcode) VALUES (:username, :password, :level, :email, :capcode)");
        $sth->bindParam(':username', $user->username, PDO::PARAM_STR);
        $sth->bindParam(':password', $user->password, PDO::PARAM_STR);
        $sth->bindParam(':level', $user->level, PDO::PARAM_INT);
        $sth->bindParam(':email', $user->email, PDO::PARAM_STR);
        $sth->bindParam(':capcode', $user->capcode, PDO::PARAM_STR);

        $sth->execute();
    }

    public function modifyUser(User $user) {
        $sth = $this->dbh->prepare("UPDATE {$this->prefix}users SET username = :username, password = :password, level = :level, email = :email, capcode = :capcode WHERE username = :id");
        $sth->bindValue(':username', $user->username, PDO::PARAM_STR);
        $sth->bindValue(':password', $user->password, PDO::PARAM_STR);
        $sth->bindValue(':level', $user->level, PDO::PARAM_INT);
        $sth->bindValue(':email', $user->email, PDO::PARAM_STR);
        $sth->bindValue(':capcode', $user->capcode, PDO::PARAM_STR);
        $sth->bindValue(':id', $user->getID(), PDO::PARAM_STR);

        $sth->execute();
    }

    public function deleteUser($username) {
        $sth = $this->dbh->prepare("DELETE FROM {$this->prefix}users WHERE username = ?");
        $sth->execute(array($username));
    }
}
