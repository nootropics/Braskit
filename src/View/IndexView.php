<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\View;

use Braskit\Board;
use Braskit\ThreadIndex;
use Braskit\View;

class IndexView extends View {
    public function get($app, $boardname, $page = 0) {
        $user = $app['auth']->authenticate(false);

        $board = new Board($boardname);

        $index = new ThreadIndex($app['dbh'], $app['db.prefix']);

        $index->setBoard($board);
        $index->setPage($page);
        $index->setMaxThreads($board->config->get('threads_per_page'));
        $index->setMaxReplies($board->config->get('replies_shown'));

        $threads = $index->getThreads();

        if ($page && !count($threads)) {
            // no threads on this page, redirect to page 0
            return $this->redirect($board->path('', true));
        }

        return $this->response->setContent($board->render('board-index.html', [
            'admin' => (bool)$user,
            'board' => $board,
            'maxpage' => $index->getPageCount() - 1,
            'pagenum' => $page,
            'threads' => $threads,
        ]));
    }
}
