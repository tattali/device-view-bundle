<?php

declare(strict_types=1);

use DeviceViewBundle\Tests\App\TestController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('home', '/')->controller([TestController::class, 'home']);
    $routes->add('view', '/view')->controller([TestController::class, 'view']);
    $routes->add('form', '/form')->controller([TestController::class, 'view'])->methods(['GET', 'HEAD', 'POST']);
    $routes->add('set_view', '/set-view/{view}')->controller([TestController::class, 'setView']);
    $routes->add('readers', '/readers/{name}')->controller([TestController::class, 'readers']);
    $routes->add('sub_request', '/with-sub-request')->controller([TestController::class, 'subRequest']);
};
