<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use Symfony\Component\HttpFoundation\Response;

final class TestController
{
    public function home(): Response
    {
        return new Response('home page');
    }
}
