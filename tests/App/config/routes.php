<?php

declare(strict_types=1);

use DeviceViewBundle\Tests\App\TestController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('home', '/')->controller([TestController::class, 'home']);
};
