<?php
declare(strict_types=1);

use controller\Api_Controller;
use Latte\Engine;
use controller\Paste_Controller;

require_once(__DIR__.'/../vendor/autoload.php');

$f3 = Base::instance();
$f3->AUTOLOAD = __DIR__.'/../app/';
$f3->config = require(__DIR__ . '/../app/config/.config.php');

$f3->Latte = new Engine;
$latte_cache_path = __DIR__.'/../data/latte_cache/';
if(file_exists($latte_cache_path) === false) {
	mkdir($latte_cache_path);
}
$f3->Latte->setTempDirectory($latte_cache_path);

// css and js
$f3->route('GET /js', Paste_Controller::class.'->js');
$f3->route('GET /css', Paste_Controller::class.'->css');

// main UI
$f3->route('GET /', Paste_Controller::class.'->load');
$f3->route('GET /@uid', Paste_Controller::class.'->load');
$f3->route('GET /@uid/get-comment-form/@line_number', Paste_Controller::class.'->getCommentForm');
$f3->route('POST /save-paste', Paste_Controller::class.'->savePaste');
$f3->route('POST /@uid/save-comment/@line_number', Paste_Controller::class.'->saveComment');

// API
$f3->route('/POST /api/paste/create', Api_Controller::class.'->savePaste');
$f3->route('/POST /api/paste/@uid/comment/@line_number/create', Api_Controller::class.'->saveComment');

$f3->run();