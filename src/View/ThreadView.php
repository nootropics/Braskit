<?php
/*
 * Copyright (C) 2013, 2014 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\View;

use Braskit\Board;
use Braskit\View;

class ThreadView extends View {
    public function get($app, $boardname, $id) {
        $user = $app['auth']->authenticate(false);

        $board = new Board($boardname);

        $posts = $board->postsInThread($id, (bool)$user);

        if (!$posts) {
            // thread doesn't exist
            return $this->diverge("/{$board}/index.html");
        }

        return $this->response->setContent($board->render('thread.html', array(
            'admin' => (bool)$user,
            'board' => $board,
            'posts' => $posts,
            'thread' => $id,
        )));
    }
}
