<?php
/*
 * Copyright (C) 2013 plainboards.org
 * Based on TinyIB, copyright (C) 2009(?)-2012 Trevor Slocum.
 *
 * See LICENSE for terms and conditions of use.
 */

$start_time = microtime(true);

// this is a valid entry point
define('TINYIB', null);

// Default exception handler for web shit
define('TINYIB_EXCEPTION_HANDLER', 'make_error_page');

// start sessions, load config, escape magic quotes, etc
require('inc/global_init.php');

// force utf-8
header('Content-Type: text/html; charset=UTF-8', true);

// start buffering
ob_start('ob_callback');

$loader = new $request_handler(get_routes(), 'pages');
$loader->run();

// print buffer
ob_end_flush();


//
// functions specific to board.php
//

function make_error_page($e) {
	$referrer = @$_SERVER['HTTP_REFERER'];

	$message = $e->getMessage();

	// escape HTML if applicable
	if ($e->getCode() !== HTMLException::HTML_MESSAGE)
		$message = cleanString($message);

	try {
		// Error messages using Twig
		echo render('error.html', array(
			'message' => $message,
			'referrer' => $referrer,
		));
	} catch (Exception $e) {
		header('Content-Type: text/plain; charset=UTF-8');
		echo "[PlainIB] Fatal exception/template error.\n\n";
		echo $e->getMessage();
	}

	exit();
}

function ob_callback($buffer) {
	global $start_time;

	// We don't want to modify non-html responses
	if (!in_array('Content-Type: text/html; charset=UTF-8', headers_list()))
		return $buffer;

	// the part of the buffer to insert before
	$ins = strrpos($buffer, "</body>");
	if ($ins === false)
		return $buffer;

	// first part of the new buffer
	$newbuf = substr($buffer, 0, $ins);

	$total_time = microtime(true) - $start_time;
	$query_time = round(100 / $total_time * Database::$time);

	// Append debug text
	$newbuf .= sprintf('<p class="footer">Page generated in %0.4f seconds,'.
	' of which %d%% was spent running %d database queries.</p>',
		$total_time, $query_time, Database::$queries);

	// the rest of the buffer
	$newbuf .= substr($buffer, $ins);

	return $newbuf;
}
