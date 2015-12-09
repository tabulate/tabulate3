<?php

define('TABULATE_VERSION', '3.0.0');
define('TABULATE_SLUG', 'tabulate');
define('DEBUG', true);

// Make sure Composer has been set up (for installation from Git, mostly).
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo '<p>Please run <tt>composer install</tt> prior to using Tabulate.</p>';
    return;
}
require __DIR__ . '/vendor/autoload.php';

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', 'HomeController::index');
    $r->addRoute('GET', '/upgrade', 'UpgradeController::prompt');
    $r->addRoute('POST', '/upgrade', 'UpgradeController::run');

    $r->addRoute('GET', '/erd', 'ErdController::index');
    $r->addRoute('GET', '/erd.png', 'ErdController::render');

    $r->addRoute('GET', '/table/{table:[a-z0-9_-]*}[.{format}]', 'TableController::index');
    $r->addRoute('GET', '/record/{table:[a-z0-9_-]*}', 'RecordController::index');
    $r->addRoute('GET', '/record/{table:[a-z0-9_-]*}/{id:[0-9].*}[.{format}]', 'RecordController::index');
});

// Fetch method and URI from somewhere
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$baseUrl = substr($_SERVER['SCRIPT_NAME'], 0, -(strlen(basename(__FILE__))));
$route = substr($uri, strlen(Tabulate\Config::baseUrl()));
$routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $route);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo '404 Not Found';
        exit(1);
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = explode('::', $routeInfo[1]);
        $vars = $routeInfo[2];
        $controllerName = '\\Tabulate\\Controllers\\' . $handler[0];
        $controller = new $controllerName();
        $action = $handler[1];
        $controller->$action($vars);
        exit(0);
        break;
    default:
        http_response_code(500);
        echo "Something odd has happened.";
        exit(1);
        break;
}