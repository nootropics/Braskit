<?php
defined('TINYIB') or exit;

function post_post($url, $boardname) {
	global $dbh;

	// get the ip
	$ip = $_SERVER['REMOTE_ADDR'];

	// get the time
	$time = $_SERVER['REQUEST_TIME'];
	$date = make_date($time);

	// get the referrer
	$referrer = isset($_SERVER['HTTP_REFERER'])
		? $_SERVER['HTTP_REFERER']
		: false;

	// set default param flags; don't accept GET values
	$flags = PARAM_DEFAULT & ~PARAM_GET;

	// POST values
	$parent = param('parent', $flags);
	$name = param('field1', $flags);
	$email = param('field2', $flags);
	$subject = param('field3', $flags);
	$comment = param('field4', $flags);
	$nofile = (bool)param('nofile', $flags);

	// We get the password from cookies
	$password = param('password', PARAM_COOKIE | PARAM_STRING);

	// Moderator options
	$raw = (bool)param('raw', $flags);
	$capcode = (bool)param('capcode', $flags);

	// Checks
	if (!ctype_digit($parent)
	|| length($parent) > 10
	|| length($password) > 100)
		throw new Exception('Abnormal post.');

	if (length($name) > 100
	|| length($email) > 100
	|| length($subject) > 100
	|| length($comment) > 10000)
		throw new Exception('Too many characters in text field.');

	// create new board object
	$board = new Board($boardname);

	// check if thread exists
	if ($parent && !threadExistsByID($board, $parent))
		throw new Exception('The specified thread does not exist.');

	// check if we're logged in
	$user = do_login();

	// This callable gets run to handle board-specific post formatting crap
	$format_cb = array($board, 'formatPostRef');

	if (!$user) {
		// check for bans
		checkBanned();

		// check spam
		if ($board->config->check_spam) {
			$values = array(&$name, &$email, &$subject, &$comment);
			$board->checkSpam($ip, $values);
		}

		$formatted_comment = format_post($comment, $board->config->default_comment, $format_cb);
	} else {
		$formatted_comment = format_post($comment, $board->config->default_comment, $format_cb, $raw);
	}

	// make name/tripcode
	list($name, $tripcode) = make_name_tripcode($name);

	if ($name === false)
		$name = $board->config->default_name;
	else
		$name = cleanString($name);

	// default subject
	if (!length($subject))
		$subject = $board->config->default_subject;
	else
		$subject = cleanString($subject);

	// set password if none is defined
	if ($password === '') {
		$password = random_string();
		$expire = $time + 86400 * 365;
		setcookie('password', $password, $expire, '/');
	}

	// Do file uploads
	// TODO: check if uploads are allowed
	$file = $board->handleUpload('file');

	if (!$parent) {
		if ($board->config->allow_thread_textonly) {
			// the nofile box must be checked to post without a file
			if (!$file && !$nofile)
				throw new Exception('No file selected.');
		} elseif (!$file) {
			// an image must be uploaded
			throw new Exception('An image is required to start a thread.');
		}
	} elseif (!$file && !length($comment)) {
		// make sure replies have either a comment or file
		throw new Exception('Please enter a message and/or upload an image to make a reply.');
	}

	// check flood
	$comment_hex = make_comment_hex($comment);
	$board->checkFlood($time, $ip, $comment_hex, (bool)$file);

	// Set up database values
	$post = newPost($parent);

	$post['name'] = $name;
	$post['tripcode'] = $tripcode;
	$post['email'] = $email;
	$post['subject'] = $subject;
	$post['comment'] = $formatted_comment;
	$post['password'] = $password;
	$post['date'] = $date;
	$post['time'] = $time;
	$post['ip'] = $ip;

	if ($file) {
		$post['file'] = $file['file'];
		$post['size'] = $file['size'];
		$post['prettysize'] = make_size($file['size']);
		$post['md5'] = $file['md5'];
		$post['origname'] = $file['origname'];
		$post['width'] = $file['width'];
		$post['height'] = $file['height'];
		$post['thumb'] = $file['thumb'];
		$post['t_width'] = $file['t_width'];
		$post['t_height'] = $file['t_height'];
	}

	// Don't commit anything to the database until we say so.
	$dbh->beginTransaction();

	// Insert the post
	$id = $board->insert($post);

	// Add flood entry
	$file_hex = isset($file['md5']) ? $file['md5'] : '';
	add_flood_entry($ip, $time, $comment_hex, $parent, $file_hex);

	// commit changes to database
	$dbh->commit();

	if ($file) {
		// Move full image
		move_uploaded_file($file['tmp'], $file['location']);

		if ($file['t_tmp'] === true) {
			// copy full image
			copy($file['location'], $file['t_location']);
		} elseif ($file['t_tmp'] !== false) {
			// move thumbnail
			rename($file['t_tmp'], $file['t_location']);
		}
	}

	if ($parent) {
		// rebuild thread cache
		$board->rebuildThread($post['parent']);

		// bump the thread if we're not saging
		if (!stristr($email, 'sage'))
			$board->bump($post['parent']);

		$dest = sprintf('res/%d.html#%d', $parent, $id);
	} else {
		// clear old threads
		$board->trim();

		// build thread cache
		$board->rebuildThread($id);

		$dest = sprintf('res/%d.html#%d', $id, $id);
	}

	$board->rebuildIndexes();

	if ($board->config->auto_noko) {
		// redirect to thread
		redirect($board->path($dest));
	} else {
		// redirect to board index
		redirect($board->path(""));
	}
}
