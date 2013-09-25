<?php
defined('TINYIB') or exit;

function users_get($url, $username = false) {
	$user = do_login($url);

	$vars = array(
		'admin' => true,
		'editing' => false,
		'user' => $user,
	);

	if ($username === false) {
		$vars['users'] = getUserList();
	} else {
		$vars['editing'] = true;
		$vars['target'] = new UserEdit($username, $user->level);
	}

	echo render('users.html', $vars);
}

function users_post($url, $username = false) {
	$user = do_login($url);

	do_csrf();

	// Form parameters
	$new_username = trim(param('username'));
	$email = trim(param('email'));
	$password = trim(param('password'));
	$password2 = trim(param('password2'));
	$level = abs(trim(param('level')));

	if ($username !== false) {
		// Edit user
		$target = $user->edit($username);

		// TODO: User renaming

		// Set new password if it's not blank in the form
		if ($password !== '')
			$target->password($password);

		$target->email($email);
		$target->level($level);
	} else {
		// Add user
		$target = $user->create($new_username);

		// Check password
		if ($password === '' || $password !== $password2)
			throw new Exception('Invalid password');

		$target->email($email);
		$target->password($password);
		$target->level($level);
	}

	$target->commit();

	diverge('/users');
}