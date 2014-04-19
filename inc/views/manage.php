<?php

class View_Manage extends View {
	protected function get($url) {
		global $config;

		$user = do_login($url);

		$boards = array();
		foreach (getAllBoards() as $board)
			$boards[$board['name']] = new Board($board['name']);

		// gets the latest posts from all boards
		$posts = getLatestPosts($config->latest_posts_count, true);

		// give each post a board object
		foreach ($posts as &$post)
			$post->board = $boards[$post->board];

		return $this->render('manage.html', array(
			'admin' => true,
			'posts' => $posts,
			'user' => $user,
		));
	}
}