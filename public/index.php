<?php
declare(strict_types=1);

use app\controller\ApiController;
use Latte\Engine;
use app\controller\PasteController;
use app\middleware\ApiMiddleware;

require_once(__DIR__.'/../vendor/autoload.php');

$app = Flight::app();
$config = require(__DIR__ . '/../app/config/.config.php');
$app->path(__DIR__.'/../');

$app->register('latte', Engine::class, array(), function($latte){
	$latte_cache_path = __DIR__.'/../data/latte_cache/';
	if(file_exists($latte_cache_path) === false) {
		mkdir($latte_cache_path);
	}
	$latte->setTempDirectory(__DIR__ . '/../app/cache');
	return $latte;
});

$router = $app->router();

$PasteController = new PasteController($app, $config);
// css and js
$router->get('/js', [ $PasteController, 'js' ]);
$router->get('/css', [ $PasteController, 'css' ]);

// main UI
$router->get('/', [ $PasteController, 'load' ]);
$router->get('/@uid', [ $PasteController, 'load' ]);
$router->get('/@uid/get-comment-form/@line_number', [ $PasteController, 'getCommentForm' ]);
$router->post('/save-paste', [ $PasteController, 'savePaste' ]);
$router->post('/@uid/save-comment/@line_number', [ $PasteController, 'saveComment' ]);

// Cli
//$f3->route('GET /search [cli]', PasteController::class.'->search');

// API
$ApiController = new ApiController($app, $config);
$router->group('/api/paste', function($router) use ($ApiController) {
	$router->post('/create', [ $ApiController, 'savePaste' ]);
	$router->post('/@uid/comment/@line_number/create', [ $ApiController, 'saveComment' ]);
}, [ new ApiMiddleware($app, $config) ]);

$app->start();