<?php
/*
 * Copyright (C) 2013, 2014 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

/*
 * Webmasters: do not edit this file. Instead, copy what you want to change to
 * config.php and edit it there instead.
 */

// protect against direct access
isset($app) or exit;


//
// Default configuration
//

$app['cache.debug'] = false;

$app['cache.type'] = function () {
    if (ini_get('apc.enabled') && extension_loaded('apc')) {
        return 'apc';
    }

    return 'php';
};

$app['js.debug'] = false;

$app['js.includes'] = array(
    'vendor/jquery-2.1.1.min.js',
    'vendor/jquery.cookie.js',
    'vendor/spin.js',

    'braskit.js',
);

$app['less.debug'] = false;

$app['less.default_style'] = 'futaba';

$app['less.stylesheets'] = array(
    'burichan' => 'Burichan',
    'futaba' => 'Futaba',
    'tomorrow' => 'Tomorrow',
    'yotsuba' => 'Yotsuba',
    'yotsuba-b' => 'Yotsuba B',
);

$app['session.name'] = function () use ($app) {
    return 'SID_'.$app['unique'];
};

$app['template.debug'] = false;

$app['thumb.method'] = 'gd';

$app['thumb.quality'] = 75;

$app['thumb.convert_path'] = 'convert';

$app['unique'] = 'bs';


//
// Default paths
//

/*
* Terminology:
*
* - root
*     This is the root directory of the package.
* - webroot
*     This is where files accessible through the web are stored.
* - entry
*     An entrypoint, e.g. board.php or ajax.php.
*/

$app['path.root'] = function () use ($app) {
    // should be set to the root of the package folder
    return realpath(__DIR__.'/..');
};

$app['path.webroot'] = function () use ($app) {
    return $app['path.root'];
};

$app['path.board'] = function () use ($app) {
    $webroot = $app['path.webroot'];
    return "$webroot/%s";
};

$app['path.boardtpl'] = function () use ($app) {
    $webroot = $app['path.webroot'];
    return "$webroot/%s/templates";
};

$app['path.boardpage'] = function () use ($app) {
    $webroot = $app['path.webroot'];
    return "$webroot/%s/%d.html";
};

$app['path.boardres'] = function () use ($app) {
    $webroot = $app['path.webroot'];
    return "$webroot/%s/res/%d.html";
};

$app['path.cache'] = function () use ($app) {
    $root = $app['path.root'];
    return "$root/cache/file";
};

$app['path.cache.tpl'] = function () use ($app) {
    $root = $app['path.root'];
    return "$root/cache/tpl";
};

$app['path.entry.board'] = function () use ($app) {
    $root = $app['path.root'];
    return "$root/board.php";
};

$app['path.tpldir'] = function () use ($app) {
    $root = $app['path.root'];
    return "$root/templates";
};

$app['path.tmp'] = function () {
    return sys_get_temp_dir();
};


//
// Default services
//

$app['auth'] = function () use ($app) {
    return new Braskit\AuthService($app['session'], $app['user']);
};

$app['ban'] = function () use ($app) {
    return new Braskit\BanService($app['db']);
};

$app['cache'] = function () use ($app) {
    if ($app['cache.debug']) {
        return new Braskit\Cache\Debug();
    }

    switch ($app['cache.type']) {
    case 'apc':
        return new Braskit\Cache\APC();
    case 'php':
        return new Braskit\Cache\PHP($app['path.cache']);
    default:
        return new Braskit\Cache\Debug();
    }
};

$app['counter'] = function () use ($app) {
    return (object)[
        'dbTime' => null,
        'dbQueries' => null,
    ];
};

$app['csrf'] = function () use ($app) {
    return new Braskit\CSRF($app['param'], $app['session']);
};

$app['db'] = function () use ($app) {
    return new Braskit\Database($app['dbh'], $app['db.prefix']);
};

$app['dbh'] = function () use ($app) {
    return new Braskit\Database\Connection(
        $app['db.name'],
        $app['db.host'],
        $app['db.username'],
        $app['db.password'],
        $app['counter']
    );
};

$app['event'] = function () {
    return new Symfony\Component\EventDispatcher\EventDispatcher();
};

$app['param'] = $app->factory(function () use ($app) {
    return new Braskit\Param($app['request']);
});

$app['request'] = function () use ($app) {
    // this will be removed from the service container eventually
    $request = Symfony\Component\HttpFoundation\Request::createFromGlobals();

    return $request;
};

$app['router_class'] = function () {
    return 'Braskit\Router\Main';
};

$app['session'] = function () use ($app) {
    $session = new Symfony\Component\HttpFoundation\Session\Session();
    $session->start();

    return $session;
};

$app['template'] = function () use ($app) {
    return $app['template.creator']($app['template.loader']);
};

$app['template.chain'] = $app->factory(function () {
    return new Twig_Loader_Chain();
});

$app['template.loader'] = function () use ($app) {
    return new Braskit\Template\TwigLoader($app['path.tpldir']);
};

$app['thumb'] = function () use ($app) {
    $method = $app['thumb.method'];

    switch ($method) {
    case 'convert':
        return new Braskit\Thumb\Convert($app['path.tmp'], array(
            'convert_path' => $app['thumb.convert_path'],
            'quality' => $app['thumb.quality'],
        ));
    case 'gd':
        return new Braskit\Thumb\GD($app['path.tmp'], array(
            'quality' => $app['thumb.quality'],
        ));
    #case 'imagemagick':
    #case 'imagick':
    #    return new Braskit\Thumb\Imagick($app['path.tmp']);
    case 'sips':
        return new Braskit\Thumb\Sips($app['path.tmp']);
    }

    throw new LogicException("Unknown thumbnail method '$method'.");
};

$app['url'] = function () use ($app) {
    return new Braskit\UrlHandler\QueryStringHandler($app['request']);
};

$app['user'] = function () use ($app) {
    return new Braskit\UserService($app['db']);
};


//
// Config service
//

$app['config'] = function () use ($app) {
    $service = $app['config.service_object'];

    // default pools
    $service->addPool('board.%', 'board');
    $service->addPool('global', 'global');

    $service->addDictionaryLoader($app['config.dict_loader']);

    return $service;
};

$app['config.dict_loader'] = function () use ($app) {
    $loader = new Braskit\Config\PimpleAwareDictionaryLoader($app);

    $loader->addDictionary('board', 'config.dict.board');
    $loader->addDictionary('global', 'config.dict.global');

    return $loader;
};

$app['config.dict.board'] = function () {
    return require __DIR__.'/board_config.php';
};

$app['config.dict.global'] = function () {
    return require __DIR__.'/site_config.php';
};

$app['config.service_object'] = function () use ($app) {
    return new Braskit\Config\ConfigService($app['cache'], $app['db']);
};


//
// Misc
//

$app['template.creator'] = $app->protect(function ($loader) use ($app) {
    $twig = new Twig_Environment($loader, array(
        'cache' => $app['template.debug'] ? false : $app['path.cache.tpl'],
        'debug' => $app['template.debug'],
    ));

    $twig->addExtension(new Braskit\Template\TwigExtension());

    // Load debugger
    if ($app['template.debug']) {
        $twig->addExtension(new Twig_Extension_Debug());
    }

    return $twig;
});
