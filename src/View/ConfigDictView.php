<?php
/*
* Copyright (C) 2013-2015 Frank Usrs
*
* See LICENSE for terms and conditions of use.
*/

namespace Braskit\View;

use Braskit\App;
use Braskit\View;

class ConfigDictView extends View {
    public function get(App $app, $name) {
        $user = $app['auth']->authenticate();

        $dict = $app['config']->getDictionary($name);

        $this->setVar('dictname', $name);
        $this->setVar('dict', $dict);

        return $this->render('config_dict.html');
    }
}
