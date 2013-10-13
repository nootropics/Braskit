<?php
defined('TINYIB') or exit;

function report_get($url, $boardname) {
	$board = new Board($boardname);
	$config = $board->config;

	if (!$config->enable_reports)
		throw new Exception('You cannot report posts on this board.');

	$posts = get_ids($board);

	if (!$posts) {
		redirect($board->path(''));

		return;
	}

	echo render('report.html', array(
		'board' => $board,
		'posts' => $posts,
	));
}

function report_post($url, $boardname) {
	do_csrf($url);

	$board = new Board($boardname);
	$config = $board->config;

	if (!$config->enable_reports)
		throw new Exception('You cannot report posts on this board.');

	// We don't want banned users reporting.
	Ban::check($_SERVER['REMOTE_ADDR'], time());

	// prevent flooding the reports
	if ($config->seconds_between_reports) {
		$threshold = time() - $config->seconds_between_reports;

		if (checkReportFlood($_SERVER['REMOTE_ADDR'], $threshold)) {
			throw new Exception('You are reporting too fast!');
		}
	}

	$posts = get_ids($board);
	$reason = param('reason');

	$board->report($posts, $_SERVER['REMOTE_ADDR'], $reason);

	// TODO: Confirmation message
	redirect($board->path(''));
}

// helper function
function get_ids($board) {
	$posts = array();
	$ids = param('id', PARAM_DEFAULT | PARAM_ARRAY);

	if (!is_array($ids))
		$ids = array($ids);

	$ids = array_unique(array_values($ids));

	foreach ($ids as $id) {
		if (ctype_digit($id)) {
			$post = $board->getPost($id);

			if ($post !== false)
				$posts[] = $post;
		}
	}

	return $posts;
}
