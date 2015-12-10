<?php

define('TABULATE_VERSION', '3.0.0');

// Make sure Composer has been set up (for installation from Git, mostly).
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo '<p>Please run <tt>composer install</tt> prior to using Tabulate.</p>';
    return;
}
require __DIR__ . '/vendor/autoload.php';
\Eloquent\Asplode\Asplode::install();


session_start();


$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', 'HomeController::index');
    $r->addRoute('GET', '/upgrade', 'UpgradeController::prompt');
    $r->addRoute('POST', '/upgrade', 'UpgradeController::run');
    $r->addRoute('GET', '/login', 'UserController::loginForm');
    $r->addRoute('POST', '/login', 'UserController::login');
    $r->addRoute('GET', '/register', 'UserController::registerForm');
    $r->addRoute('POST', '/register', 'UserController::register');

    $r->addRoute('GET', '/erd', 'ErdController::index');
    $r->addRoute('GET', '/erd.png', 'ErdController::render');

    $r->addRoute('GET', '/table/{table:[a-z0-9_-]*}[.{format}]', 'TableController::index');

    $r->addRoute('GET', '/record/{table:[a-z0-9_-]*}', 'RecordController::index');
    $r->addRoute('GET', '/record/{table:[a-z0-9_-]*}/{id:[0-9].*}[.{format}]', 'RecordController::index');
    $r->addRoute('POST', '/record/{table:[a-z0-9_-]*}', 'RecordController::save');
    $r->addRoute('POST', '/record/{table:[a-z0-9_-]*}/{id:[0-9].*}[.{format}]', 'RecordController::save');
});

// Fetch method and URI from somewhere
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$baseUrl = substr($_SERVER['SCRIPT_NAME'], 0, -(strlen(basename(__FILE__))));
$route = substr($uri, strlen(Tabulate\Config::baseUrl()));
$routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $route);
try {
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            http_response_code(404);
            throw new \Exception("Not found: $route", 404);
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
} catch (\Exception $e) {
    //var_dump($e);exit();
    $template = new Tabulate\Template('error_page.twig');
    $template->title = 'Error';
    $template->e = $e;
    echo $template->render();
    exit(1);
}
