<?php
defined('TINYIB') or exit;

function viewpage_get($url, $boardname, $page = 0) {
	$user = do_login($url);

	$board = new Board($boardname);

	$offset = $page * 10;

	$threads = $board->getIndexThreads($offset);

	// get number of pages for the page nav
	$maxpage = get_page_count($board->countThreads()) - 1;

	if ($page && !count($threads)) {
		// no threads on this page, redirect to page 0
		redirect($board->path('', true));
		return;
	}

	echo render('page.html', array(
		'admin' => true,
		'board' => $board,
		'maxpage' => $maxpage,
		'pagenum' => $page,
		'threads' => $threads,
	));
}
