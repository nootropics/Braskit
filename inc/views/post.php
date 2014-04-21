<?php

class View_Post extends View {
	protected function post($url, $boardname) {
		global $db, $dbh, $request;

		// get the ip
		$ip = $request->ip;

		// get the time
		$time = $request->time;

		// get the referrer
		$referrer = $request->referrer;

		// set default param flags; don't accept GET values
		$flags = PARAM_DEFAULT & ~PARAM_GET;

		// POST values
		$parent = param('parent', $flags);
		$name = param('field1', $flags);
		$email = param('field2', $flags);
		$subject = param('field3', $flags);
		$comment = param('field4', $flags);

		$nofile = (bool)param('nofile', $flags);
		$sage = (bool)param('sage', $flags);

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
		if ($parent && !$db->threadExistsByID($board, $parent))
			throw new Exception('The specified thread does not exist.');

		// check if we're logged in
		$user = do_login();

		if (!$user) {
			// check for bans
			Ban::check($ip);

			// check spam
			if ($board->config->check_spam) {
				$values = array(&$name, &$email, &$subject, &$comment);
				$board->checkSpam($ip, $values);
			}

			$raw = false;
		}

		// This callable gets run to handle board-specific post formatting crap
		$format_cb = array($board, 'linkifyCitations');

		if (!$raw) {
			// format the comment
			$parser = new Parser_Wakabamark($comment, array($format_cb));
			$formatted_comment = $parser->parsed;
		} else {
			$formatted_comment = Parser::normaliseInput($comment);
		}

		if (!strlen($formatted_comment)) {
			$comment = $board->config->default_comment;
			$formatted_comment = $board->config->default_comment;
		}

		if (!$user && $board->config->forced_anon) {
			// nothing to do here
			$name = $board->config->default_name;
			$email = '';
			$tripcode = '';

			if (!$board->config->allow_sage)
				$sage = false;
			elseif ($sage)
				$email = 'mailto:sage';
		} else {
			// make name/tripcode
			list($name, $tripcode) = make_name_tripcode($name);

			if ($name === false)
				$name = $board->config->default_name;
			else
				$name = Parser::escape($name);

			// remove tripcodes unless they're allowed
			if (!$user && !$board->config->allow_tripcodes)
				$tripcode = '';

			// add capcode if applicable
			if ($capcode && $user && strlen($user->capcode))
				$tripcode .= ' '.$user->capcode;

			if ($board->config->allow_email && length($email)) {
				// set email address
				$email = 'mailto:'.Parser::escape($email);

				// check for sage
				if ($board->config->allow_sage)
					$sage = stripos($email, 'sage') !== false;
			} elseif (!$board->config->allow_sage) {
				$sage = false;
			} elseif ($sage) {
				$email = 'mailto:sage';
			}
		}

		// default subject
		if (!length($subject))
			$subject = $board->config->default_subject;
		else
			$subject = Parser::escape($subject);

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
				if (!$file->exists && !$nofile)
					throw new Exception('No file selected.');
			} elseif (!$file->exists) {
				// an image must be uploaded
				throw new Exception('An image is required to start a thread.');
			}
		} elseif (!$file->exists && !length($comment)) {
			// make sure replies have either a comment or file
			throw new Exception('Please enter a message and/or upload an image to make a reply.');
		}

		// check flood
		$board->checkFlood($time, $ip, $formatted_comment, $file->exists);

		// Set up database values
		$post = new Post($parent);

		$post->board = (string)$board;
		$post->parent = $parent;
		$post->name = $name;
		$post->tripcode = $tripcode;
		$post->email = $email;
		$post->subject = $subject;
		$post->comment = $formatted_comment;
		$post->password = $password;
		$post->timestamp = $time;
		$post->ip = $ip;

		// Don't commit anything to the database until we say so.
		$dbh->beginTransaction();

		// Insert the post ($post gets the new ID added to it)
		$board->insert($post);

		// Insert the file
		$file->insert($post);

		// commit changes to database
		$dbh->commit();

		// at this point, we know that the post has been saved to the database,
		// so the files won't be orphaned when we move them.
		$file->move();

		if ($parent) {
			// rebuild thread cache
			$board->rebuildThread($post->parent);

			// bump the thread if we're not saging
			if (!$sage)
				$board->bump($post->parent);

			$dest = sprintf('res/%d.html#%d', $parent, $post->id);
		} else {
			// clear old threads
			$board->trim();

			// build thread cache
			$board->rebuildThread($post->id);

			$dest = sprintf('res/%d.html#%d', $post->id, $post->id);
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
}
