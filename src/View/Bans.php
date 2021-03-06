<?php
/*
 * Copyright (C) 2013, 2014 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\View;

use Braskit\Ban;
use Braskit\View;

class Bans extends View {
    public function get($app) {
        $user = $app['auth']->authenticate();

        // TODO: Pagination
        $bans = $app['ban']->getAll();

        $ip = $app['param']->get('ip');

        return $this->render('bans.html', array(
            'admin' => true,
            'bans' => $bans,
            'ip' => $ip,
        ));
    }

    public function post($app) {
        $user = $app['auth']->authenticate();

        $app['csrf']->check();

        $param = $app['param'];

        // adding a ban
        $expire = $param->get('expire');
        $reason = $param->get('reason');
        $ip = $param->get('ip');

        if ($ip) {
            $ban = Ban::create($ip);
            $ban->setReason($reason);
            $ban->setExpire($expire);

            $app['ban']->add($ban);
        }

        // lifting bans
        $lifts = $param->get('lift', 'string array');

        if ($lifts && !is_array($lifts)) {
            $lifts = array($lifts);
        }

        foreach ($lifts as $id) {
            $app['ban']->delete($id);
        }

        return $this->diverge('/bans');
    }
}
