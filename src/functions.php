<?php
/*
 * Copyright (C) 2013-2015 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

/**
 * @todo Make all functions independent of global state.
 */

use Braskit\Error;

if (!function_exists('hash_equals')) {
    /**
     * Constant-time string comparison used to avoid timing attacks. PHP 5.6+
     * has this built-in, this is only for BC.
     *
     * @param string $hash1
     * @param string $hash2
     *
     * @return boolean
     */
    function hash_equals($hash1, $hash2) {
        $len = strlen($hash1);

        if ($len !== strlen($hash2)) {
            throw new \InvalidArgumentException(
                'The string lengths of the arguments must be equal'
            );
        }

        $cmp = 0;

        for ($i = $len; $i--;) {
            $cmp |= ord($hash1[$i]) ^ ord($hash2[$i]);
        }

        return $cmp === 0;
    }
}

// helper function - TODO
function get_ids($board) {
    global $app;

    $posts = array();
    $ids = $app['param']->get('id', 'string array');

    if (!is_array($ids))
        $ids = array($ids);

    $ids = array_unique(array_values($ids));

    foreach ($ids as $id) {
        if (ctype_digit($id)) {
            $post = $board->getPost($id);

            if ($post !== false)
                $posts[] = $post;
        }
    }

    return $posts;
}

/**
 * @deprecated
 */
function expand_path($filename, $params = false) {
    global $app; // TODO

    $internal = !$params && !is_array($params);

    if (!$internal) {
        if (!is_array($params)) {
            $params = [];
        }

        return $app['url']->createURL("/$filename", $params);
    }

    $dirname = preg_replace('!/[^/]*$!', '', $app['request']->getScriptName());

    return "$dirname/$filename";
}

/**
 * Minifies and combines the JavaScript files specified in the configuration
 * into one file and returns the path to it.
 *
 * @return string Path to combined JavaScript file
 */
function get_js() {
    global $app;

    static $static_cache;

    // load from static var cache
    if (isset($web_path))
        return $web_path;

    // try loading from persistent cache
    $data = $app['cache']->get('js_cache');

    if ($data !== null)
        return $data;

    // output path
    $path = 'static/js/cache-'.time().'.js';

    // start suppressing output - jsmin+ is dumb and echoes errors instead
    // of throwing exceptions
    ob_start();

    $fh = fopen($app['path.root']."/$path", 'w');

    if (!$fh) {
        ob_end_clean();
        throw new Exception("Cannot write to /static/js/.");
    }

    foreach ($app['js.includes'] as $filename) {
        if (strpos($filename, '/') !== 0 && !strpos($filename, '://'))
            $filename = $app['path.root'].'/static/js/'.$filename;

        $js = file_get_contents($filename);

        try {
            $temp = JSMinPlus::minify($js);
        } catch (Exception $e) {
            continue;
        }

        // concatenate to the output file
        fwrite($fh, "$temp;");
    }

    fclose($fh);
    ob_end_clean();

    $web_path = expand_path($path);

    $app['cache']->set('js_cache', $web_path);

    return $web_path;
}


//
// Flood stuff
//

function make_comment_hex($str) {
    // remove cross-board citations
    // the numbers don't matter
    $str = preg_replace('!>>>/[A-Za-z0-9]+/!', '', $str);

    if (function_exists('iconv')) {
        // remove diacritics and other noise
        // FIXME: this removes cyrillic entirely
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    }

    $str = strtolower($str);

    // strip all non-alphabet characters
    $str = preg_replace('/[^a-z]/', '', $str);

    if ($str === '')
        return '';

    return sha1($str);
}


//
// Unsorted
//

function random_string($length = 8,
$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    // Number of possible outcomes
    $outcomes = is_array($pool) ? count($pool) : strlen($pool);
    $outcomes--;

    $str = '';
    while ($length--)
        $str .= $pool[mt_rand(0, $outcomes)];

    return $str;
}

function length($str) {
    // Don't remove trailing spaces - wakabamark/markdown uses them for
    // block code formatting
    $str = rtrim($str);

    if (extension_loaded('mbstring'))
        return mb_strlen($str, 'UTF-8');

    return strlen($str);
}

/**
 * Formats a file size to make it human-readable.
 *
 * @param int $size The file size.
 * @param boolean $base2 Use binary counting/suffixes (default to false).
 *
 * @return string
 */
function bs_format_size($size, $base2 = false) {
    if (!$size) {
        return '0 B';
    }

    if ($base2) {
        $n = 1024;
        $s = array('B', 'KiB', 'MiB', 'GiB', 'TiB');
    } else {
        $n = 1000;
        $s = array('B', 'kB', 'MB', 'GB', 'TB');
    }

    for ($i = 0; $i <= 3; $i++) {
        if ($size >= pow($n, $i) && $size < pow($n, $i + 1)) {
            $unit = $s[$i];
            $number = round($size / pow($n, $i), 2);

            return sprintf('%s %s', $number, $unit);
        }
    }

    $unit = $s[4];
    $number = round($size / pow($n, 4), 2);

    return sprintf('%s %s', $number, $unit);
}

/**
 * Truncates a filename.
 *
 * If the specified filename is longer than $max_length, the basename (filename
 * without directory or extension) will be shortened, have the ellipsis text
 * appended and finally have the extension appended.
 *
 * @param string $filename The filename.
 * @param int $max_length
 * @param string $ellipsis The text which indicates that a filename has been
 *                         truncated.
 *
 * @todo Do something about file extensions (they aren't truncated).
 *
 * @return string
 */
function bs_shorten_filename($filename, $max_length = 25, $ellipsis = '(…)') {
    if ($max_length < 1) {
        throw new \RuntimeException('Max length must be above zero');
    }

    // pathinfo() is one of those scary functions that alter their behaviour
    // based on global state such as locale, etc. Let's just use regex instead.
    preg_match('#^(?:.*[/\\\\])?(.*?)((?:\\.[^.]+?)?)$#', $filename, $matches);

    list (/*$match*/, $basename, $extension) = $matches;

    if (length($basename) <= $max_length) {
        // short enough
        return $filename;
    }

    // cut basename while preserving UTF-8 if possible
    if (!ctype_digit($basename) && extension_loaded('mbstring')) {
        $short = mb_substr($basename, 0, $max_length, 'UTF-8');
    } else {
        $short = substr($basename, 0, $max_length);
    }

    return $short.$ellipsis.$extension;
}

/**
 * Prettify a value for HTML.
 *
 * @param mixed $value A value to prettify.
 * @param integer $depth Indentation, used for arrays.
 *
 * @todo Add proper support for objects. Recursion must be taken into account.
 *
 * @return string Prettified value.
 */
function bs_prettify($value, $depth = 0) {
    $depth += 1;

    $type = gettype($value);

    switch ($type) {
    case 'NULL':
        $type = 'null'; // fix for uppercase stupidity
        $text = 'null';
        break;

    case 'boolean':
        $text = $value ? 'true' : 'false';
        break;

    case 'integer':
    case 'double':
        $text = (string)$value;
        break;

    case 'string':
        $text = '"'.htmlspecialchars($value, ENT_QUOTES|ENT_HTML5, 'UTF-8').'"';
        break;

    case 'array':
        $text = '[';

        if ($value) {
            $text .= "<br>";
        }

        foreach ($value as $key => $subvalue) {
            // add indentation
            $text .= str_repeat('&nbsp;', $depth * 2);

            // add array key
            $text .= bs_prettify($key);

            // add key/value separator
            $text .= ' => ';

            // recursively call this function to format array items
            $text .= bs_prettify($subvalue, $depth);

            // add comma and newline
            $text .= ",<br>";
        }

        // indent and add ending bracket
        $text .= str_repeat('&nbsp;', ($depth - 1) * 2);
        $text .= ']';

        break;

    case 'object':
        if ($value instanceof \Closure) {
            // anonymous function
            $type = 'function';
            $text = 'function (';

            $r = new \ReflectionFunction($value);

            $first = true;

            foreach ($r->getParameters() as $param) {
                if (!$first) {
                    $text .= ', ';
                }

                if ($param->isArray()) {
                    $text .= 'array ';
                } elseif ($param->isCallable()) {
                    $text .= 'callable ';
                } else {
                    // Lame solution, but $param->getClass() tries loading the
                    // class, which is bad.
                    if (
                        preg_match(
                        '/\s+([\w\\\\]+)\s+\$/',
                            $param->__toString(),
                            $matches
                        ) && isset($matches[1])
                    ) {
                        $text .= $matches[1].' ';
                    }
                }

                if ($param->isPassedByReference()) {
                    $text .= '&amp;';
                }

                $text .= '$'.$param->getName();

                if ($param->isDefaultValueAvailable()) {
                    $text .= ' = ';
                    $text .= bs_prettify($param->getDefaultValue());
                }

                $first = false;
            }

            $text .= ')';
        } else {
            // object
            $text = '('.$type.')'.get_class($value);
        }

        break;

    case 'resource':
        $text = (string)$value;
        break;

    default:
        $type = 'unknown';
        $text = 'Unknown';
    }

    return '<kbd class="format-'.$type.'">'.$text.'</kbd>';
}

function make_name_tripcode($input, $tripkey = '!') {
    global $app;

    $tripcode = '';

    // Check if we can reencode strings
    static $has_encode;
    if (!isset($has_encode))
        $has_encode = extension_loaded('mbstring');

    // Split name into chunks
    $bits = explode('#', $input, 3);
    list($name, $trip, $secure) = array_pad($bits, 3, false);

    // Anonymous?
    if (!is_string($name) || !length($name))
        $name = false;

    // Do regular tripcodes
    if ($trip !== false && (strlen($trip) !== 0 || $secure === false)) {
        if ($has_encode)
            $trip = mb_convert_encoding($trip, 'UTF-8', 'SJIS');

        $salt = substr($trip.'H..', 1, 2);
        $salt = preg_replace('/[^\.-z]/', '.', $salt);
        $salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');

        $tripcode = $tripkey.substr(crypt($trip, $salt), -10);
    }

    // Do secure tripcodes
    if ($secure !== false) {
        $hash = sha1($secure.$app['secret']);
        $hash = substr(base64_encode($hash), 0, 10);
        $tripcode .= $tripkey.$tripkey.$hash;
    }

    return array($name, $tripcode);
}

function create_ban_message($post) {
    // comment goes at the top
    $msg = "\n\n";

    if ($post->md5)
        $msg .= 'MD5: '.$post->md5."\n";

    $msg .= 'Name: ';
    $msg .= html_entity_decode($post->name, ENT_QUOTES, 'UTF-8');
    $msg .= "\n";

    if ($post->tripcode)
        $reason .= ' '.strip_tags($post->tripcode);

    $comment = html_entity_decode($post->comment, ENT_QUOTES, 'UTF-8');
    $comment = strip_tags($comment);

    $msg .= "Comment:\n$comment";

    return $msg;
}
