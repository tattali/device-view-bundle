<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Remember;

use DeviceViewBundle\Remember\NullStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class NullStorageTest extends TestCase
{
    public function testReadsNothingAndConsultsNothing(): void
    {
        $storage = new NullStorage();

        self::assertNull($storage->read(Request::create('/view', cookies: ['device_view' => 'mobile'])));
        self::assertSame([], $storage->varyHeaders());
    }

    public function testWritesAndClearsNothing(): void
    {
        $storage = new NullStorage();
        $request = Request::create('/view');
        $response = new Response();

        $storage->write($request, $response, 'mobile', 3600);
        self::assertFalse($storage->clear($request, $response));

        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($request->hasSession());
    }
}
