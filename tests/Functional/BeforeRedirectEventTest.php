<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Event\BeforeRedirectEvent;
use DeviceViewBundle\Redirect\RedirectReason;
use DeviceViewBundle\Tests\App\Event\BeforeRedirectListener;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * BeforeRedirectEvent through the kernel: the test application listens to it with
 * BeforeRedirectListener.
 */
final class BeforeRedirectEventTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const array M = ['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']]];

    #[Group('EVT-RED-01')]
    public function testSwitchRedirectGetters(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame([['reason' => RedirectReason::Switch, 'url' => '/view', 'status' => 302]], $this->listener()->events);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
    }

    #[Group('EVT-RED-02')]
    public function testDeviceRedirectGetters(): void
    {
        $this->boot(self::M);

        $response = $this->handle(self::request('http://www.example.com/blog/post', 'iphone'));

        self::assertSame([['reason' => RedirectReason::Device, 'url' => 'https://m.example.com/blog/post?device_view=mobile', 'status' => 302]], $this->listener()->events);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testTheConfiguredStatusesReachTheEvent(): void
    {
        $this->boot(['switch' => ['status' => 307], 'redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'status' => 301]]]);

        $this->handle(self::request('https://m.example.com/view?device_view=mobile', 'iphone'));
        self::assertSame([['reason' => RedirectReason::Switch, 'url' => '/view', 'status' => 307]], $this->listener()->events);

        $this->handle(self::request('http://www.example.com/', 'iphone'));
        self::assertSame([['reason' => RedirectReason::Device, 'url' => 'https://m.example.com/?device_view=mobile', 'status' => 301]], $this->listener()->events);
    }

    #[Group('EVT-RED-03')]
    public function testSetUrl(): void
    {
        $this->boot();
        $this->listener()->onEvent = static function (BeforeRedirectEvent $event): void {
            $event->setUrl('/elsewhere');
        };

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/elsewhere', $response->headers->get('Location'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('EVT-RED-04')]
    public function testSetStatus(): void
    {
        $this->boot(self::M);
        $this->listener()->onEvent = static function (BeforeRedirectEvent $event): void {
            $event->setStatus(303);
        };

        $response = $this->handle(self::request('http://www.example.com/', 'iphone'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('https://m.example.com/?device_view=mobile', $response->headers->get('Location'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    #[Group('EVT-RED-05')]
    public function testCancellingASwitchRedirectKeepsTheSwitch(): void
    {
        $this->boot();
        $this->listener()->onEvent = static function (BeforeRedirectEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile switch', $response->getContent());
        self::assertSetCookie(self::sc('mobile'), $response);
        self::assertFalse($response->headers->has('Location'));
        self::assertCount(1, $this->listener()->events);
    }

    /**
     * A cancelled switch redirect leaves the request as switch.target none does: the device
     * redirect still sends a view served on another host there, with its own event.
     */
    public function testACancelledSwitchToAViewOnAnotherHostGetsTheDeviceRedirect(): void
    {
        $this->boot(self::M);
        $this->listener()->onEvent = static function (BeforeRedirectEvent $event): void {
            if (RedirectReason::Switch === $event->getReason()) {
                $event->cancel();
            }
        };

        $response = $this->handle(self::request('http://www.example.com/blog/post?device_view=mobile'));

        self::assertSame([
            ['reason' => RedirectReason::Switch, 'url' => 'https://m.example.com/blog/post?device_view=mobile', 'status' => 302],
            ['reason' => RedirectReason::Device, 'url' => 'https://m.example.com/blog/post?device_view=mobile', 'status' => 302],
        ], $this->listener()->events);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://m.example.com/blog/post?device_view=mobile', $response->headers->get('Location'));
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    public function testCancellingBothRedirectsKeepsTheSwitchOnTheCurrentHost(): void
    {
        $this->boot(self::M);
        $this->listener()->onEvent = static function (BeforeRedirectEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('http://www.example.com/view?device_view=mobile'));

        self::assertCount(2, $this->listener()->events);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile switch', $response->getContent());
        self::assertSetCookie(self::sc('mobile'), $response);
        self::assertFalse($response->headers->has('Location'));
    }

    #[Group('EVT-RED-06')]
    public function testCancellingADeviceRedirect(): void
    {
        $this->boot(self::M);
        $this->listener()->onEvent = static function (BeforeRedirectEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('http://www.example.com/view', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
        self::assertFalse($response->headers->has('Location'));
        self::assertCount(1, $this->listener()->events);
    }

    #[Group('EVT-RED-07')]
    public function testInvalidStatus(): void
    {
        $this->boot();
        $this->listener()->onEvent = static function (BeforeRedirectEvent $event): void {
            $event->setStatus(200);
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The redirect status must be one of 301, 302, 303, 307, 308; got 200.');

        $this->handle(self::request('/view?device_view=mobile'), false);
    }

    #[Group('EVT-RED-08')]
    public function testTheEventNameIsTheClassName(): void
    {
        $this->boot();
        $dispatcher = $this->service('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $this->handle(self::request('/view?device_view=mobile'));

        self::assertTrue($dispatcher->hasListeners(BeforeRedirectEvent::class));
        self::assertCount(1, $this->listener()->events, 'the listener on BeforeRedirectEvent::class is called');
    }

    public function testNotDispatchedWithoutARedirect(): void
    {
        $this->boot(self::M + ['switch' => ['target' => 'none']]);

        $this->handle(self::request('https://m.example.com/view?device_view=mobile', 'iphone'));
        self::assertSame([], $this->listener()->events, 'target none, on the host of the view');

        $this->handle(self::request('https://m.example.com/view', 'iphone'));
        self::assertSame([], $this->listener()->events, 'no device redirect on the host of the view');
    }

    private function listener(): BeforeRedirectListener
    {
        $listener = $this->service(BeforeRedirectListener::class);
        self::assertInstanceOf(BeforeRedirectListener::class, $listener);

        return $listener;
    }
}
