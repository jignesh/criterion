<?php

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT  . '/data');
define('TEST_DIR', DATA_DIR . '/tests');
define('KEY_DIR', DATA_DIR . '/keys');

include dirname(__DIR__) . '/vendor/autoload.php';

$app = new Silex\Application();

$app->register(new MongoMinify\Silex\ServiceProvider(), array(
    'mongo.server' => 'mongodb://127.0.0.1:27017/criterion',
));

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => dirname(__DIR__) . '/src/Criterion/UI/View',
));

$app['debug'] = true;
$app->get('/', 'Criterion\UI\Controller\ProjectsController::all');
$app->post('/project/create', 'Criterion\UI\Controller\ProjectsController::create');
$app->match('/project/{id}', 'Criterion\UI\Controller\ProjectsController::view')->method('POST|GET');
$app->get('/project/run/{id}', 'Criterion\UI\Controller\ProjectsController::run');
$app->get('/project/delete/{id}', 'Criterion\UI\Controller\ProjectsController::delete');
$app->get('/project/status/{id}', 'Criterion\UI\Controller\ProjectsController::status');
$app->get('/test/{id}', 'Criterion\UI\Controller\TestController::view');
$app->get('/test/status/{id}', 'Criterion\UI\Controller\TestController::status');
$app->get('/test/delete/{id}', 'Criterion\UI\Controller\TestController::delete');
$app->post('/hook/github', 'Criterion\UI\Controller\HookController::github');

$app->run();
