<?php
/*
 * Copyright (C) 2013, 2014 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit;

use Braskit\Error\AuthError;
use Braskit\User\NullUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Service that handles stuff related to authentication.
 *
 * Neither the plain text password, nor the bcrypt'd password are stored in the
 * session. Instead, a sha256 sum of the latter is used for performance reasons.
 * It *shouldn't* have any effect on security, but who knows.
 *
 * A login is active as long as the username and password stored in the session
 * match up with the details in the database.
 */
class AuthService {
    /**
     * @var string
     */
    const SESSKEY_USER = 'auth-user';

    /**
     * @var string
     */
    const SESSKEY_HASH = 'auth-password-hash';

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var UserService
     */
    protected $user;

    /**
     * Constructor.
     *
     * @param Request $request
     */
    public function __construct(Session $session, UserService $userService) {
        $this->session = $session;
        $this->user = $userService;
    }

    /**
     * Authenticates a user. The $restrict parameter can be set to false in
     * order to make authentication optional.
     *
     * @param boolean $restrict
     *
     * @throws AuthError if $restrict is true and authentication failed.
     *
     * @return User|null
     */
    public function authenticate($restrict = true) {
        if ($this->session->has(static::SESSKEY_USER)) {
            $username = $this->session->get(static::SESSKEY_USER);

            $user = $this->user->get($username, false);

            if ($user) {
                $sessHash = $this->session->get(static::SESSKEY_HASH);

                $dbHash = hash('sha256', $user->password);

                // the docs dont say if sessions are subject to race conditions,
                // so let's just assume they are
                if (is_string($sessHash) && hash_equals($sessHash, $dbHash)) {
                    $user->checkSuspension();

                    // we are authenticated
                    return $user;
                }
            }
        }

        if ($restrict) {
            throw new AuthError('Access denied');
        }

        return null;
    }

    /**
     * Checks if a user is logged in.
     *
     * @return boolean
     */
    public function isLoggedIn() {
        return (bool)$this->authenticate(false);
    }

    /**
     * Log in.
     *
     * @throws Error if the login was not successful
     *
     * @param string $username Username.
     * @param string $password Password.
     */
    public function login($username, $password) {
        // checkPassword might update the password in the db, thus edit() is
        // used in order to save the changes
        $user = $this->user->edit($username, new NullUser());

        $user->checkPassword($password);
        $user->checkSuspension();

        $user->commit();

        $hash = hash('sha256', $user->password);

        $this->session->set(static::SESSKEY_USER, $user->username);
        $this->session->set(static::SESSKEY_HASH, $hash);
    }

    /**
     * Log out if logged in, or do nothing.
     */
    public function logout() {
        $this->session->remove(static::SESSKEY_USER);
        $this->session->remove(static::SESSKEY_HASH);
    }
}
