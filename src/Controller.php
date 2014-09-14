<?php
/*
 * Copyright (C) 2013, 2014 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit;

abstract class Controller {
    protected $app;

    public function __construct(App $app) {
        $this->app = $app;

        $self = $this;

        $app['router'] = function () use ($self) {
            return $self->getRouter();
        };
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    abstract public function run();

    /**
     * Exception handler.
     *
     * @param Exception $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    abstract public function exceptionHandler(\Exception $e);

    /**
     * Get an instance of Router.
     *
     * @return Router
     */
    abstract protected function getRouter();

    /**
     * Do stuff that messes with PHP's global state.
     */
    protected function globalSetup() {
        // bad things could happen without this
        ignore_user_abort(true);

        date_default_timezone_set($this->app['timezone']);
    }
}

/* vim: set ts=4 sw=4 sts=4 et: */
