<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Remember;

use DeviceViewBundle\Remember\SessionStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SessionStorageTest extends TestCase
{
    public function testReadsThePreviousSession(): void
    {
        $request = self::requestWithPreviousSession(['_device_view' => 'tablet']);

        self::assertSame('tablet', new SessionStorage()->read($request));
    }

    public function testReadsTheConfiguredKey(): void
    {
        $request = self::requestWithPreviousSession(['_device_view' => 'tablet', 'dv' => 'mobile']);

        self::assertSame('mobile', new SessionStorage('dv')->read($request));
    }

    public function testReadsNothingWhenThePreviousSessionHoldsNothing(): void
    {
        self::assertNull(new SessionStorage()->read(self::requestWithPreviousSession([])));
    }

    public function testGivesBackWhatTheSessionHolds(): void
    {
        $request = self::requestWithPreviousSession(['_device_view' => ['a' => 'mobile']]);

        self::assertSame(['a' => 'mobile'], new SessionStorage()->read($request));
    }

    public function testNeverStartsASessionToRead(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/view');
        $request->setSession($session);

        self::assertNull(new SessionStorage()->read($request));
        self::assertFalse($session->isStarted());
    }

    public function testReadsNothingWithoutSession(): void
    {
        self::assertNull(new SessionStorage()->read(Request::create('/view')));
    }

    public function testWritesTheViewName(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/view');
        $request->setSession($session);
        $response = new Response();

        new SessionStorage()->write($request, $response, 'mobile', 0);

        self::assertSame(['_device_view' => 'mobile'], $session->all());
        self::assertSame([], $response->headers->getCookies(), 'the session cookie is the framework\'s');
    }

    public function testWritesTheConfiguredKeyWhateverTheLifetime(): void
    {
        $request = self::requestWithPreviousSession(['_device_view' => 'tablet']);

        new SessionStorage('dv')->write($request, new Response(), 'mobile', 31536000);

        self::assertSame(['_device_view' => 'tablet', 'dv' => 'mobile'], $request->getSession()->all());
    }

    public function testWritingNeedsTheFrameworkSession(): void
    {
        $this->expectException(SessionNotFoundException::class);

        new SessionStorage()->write(Request::create('/view'), new Response(), 'mobile', 0);
    }

    public function testClearRemovesTheKeyOnly(): void
    {
        $request = self::requestWithPreviousSession(['_device_view' => 'phone', 'other' => 'kept']);
        $response = new Response();

        self::assertTrue(new SessionStorage()->clear($request, $response));

        self::assertSame(['other' => 'kept'], $request->getSession()->all());
        self::assertSame([], $response->headers->getCookies());
    }

    public function testClearRemovesAValueOfAnyType(): void
    {
        $request = self::requestWithPreviousSession(['_device_view' => ['a' => 'mobile'], 'other' => 'kept']);

        self::assertTrue(new SessionStorage()->clear($request, new Response()));

        self::assertSame(['other' => 'kept'], $request->getSession()->all());
    }

    public function testClearNeverStartsASession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/view');
        $request->setSession($session);

        self::assertFalse(new SessionStorage()->clear($request, new Response()));

        self::assertFalse($session->isStarted());
    }

    public function testClearWithoutSessionDoesNothing(): void
    {
        $request = Request::create('/view');

        self::assertFalse(new SessionStorage()->clear($request, new Response()));

        self::assertFalse($request->hasSession());
    }

    public function testVariesOnTheSessionCookie(): void
    {
        self::assertSame(['Cookie'], new SessionStorage()->varyHeaders());
    }

    /**
     * A request that carries the cookie of a session holding $values.
     *
     * @param array<string, mixed> $values
     */
    private static function requestWithPreviousSession(array $values): Request
    {
        $session = new Session(new MockArraySessionStorage());
        foreach ($values as $key => $value) {
            $session->set($key, $value);
        }

        $request = Request::create('/view', cookies: [$session->getName() => $session->getId()]);
        $request->setSession($session);

        return $request;
    }
}
