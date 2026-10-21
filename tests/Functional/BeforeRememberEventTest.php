<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Event\BeforeRememberEvent;
use DeviceViewBundle\Remember\RememberTrigger;
use DeviceViewBundle\Tests\App\Event\BeforeRememberListener;
use DeviceViewBundle\View\DeviceType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * BeforeRememberEvent through the kernel: the test application listens to it with
 * BeforeRememberListener.
 */
final class BeforeRememberEventTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const array TAC = ['consent' => ['provider' => 'tarteaucitron']];

    private const array GRANTED = ['tarteaucitron' => '!device_view=true'];

    private const array DENIED = ['tarteaucitron' => '!device_view=false'];

    #[Group('EVT-REM-01')]
    public function testSwitchGetters(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame([['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Mobile, 'consent' => null, 'lifetime' => 0]], $this->listener()->events);
        self::assertSame(302, $response->getStatusCode());
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('EVT-REM-02')]
    public function testDetectedGetters(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame([['trigger' => RememberTrigger::Detected, 'view' => DeviceType::Mobile, 'consent' => null, 'lifetime' => 0]], $this->listener()->events);
        self::assertSame('mobile detector', $response->getContent());
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('EVT-REM-03')]
    public function testConsentGetters(): void
    {
        $this->boot(self::TAC);

        $response = $this->handle(self::request('/view?device_view=mobile', cookies: self::GRANTED));

        self::assertSame([['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Mobile, 'consent' => true, 'lifetime' => 31536000]], $this->listener()->events);
        self::assertSetCookie(self::sc('mobile', 31536000), $response);
    }

    public function testADeniedConsentReachesTheEvent(): void
    {
        $this->boot(self::TAC + ['remember' => ['lifetime' => 60]]);

        $this->handle(self::request('/view?device_view=mobile', cookies: self::DENIED));

        self::assertSame([['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Mobile, 'consent' => false, 'lifetime' => 60]], $this->listener()->events);
    }

    public function testTheConsentOverrideReachesTheEvent(): void
    {
        $this->boot(self::TAC + ['switch' => ['target' => 'none']]);

        $this->handle(self::request('/set-consent/1?device_view=mobile', cookies: self::DENIED));

        self::assertSame([['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Mobile, 'consent' => true, 'lifetime' => 31536000]], $this->listener()->events);
    }

    public function testTheEventHasTheViewThatIsWritten(): void
    {
        $this->boot(['tablet_as_mobile' => true, 'switch' => ['target' => 'none']]);

        $response = $this->handle(self::request('/set-view/tablet?remember=1'));

        self::assertSame([['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Mobile, 'consent' => null, 'lifetime' => 0]], $this->listener()->events);
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('EVT-REM-04')]
    public function testCancel(): void
    {
        $this->boot();
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
        self::assertNoCookie($response);
        self::assertCount(1, $this->listener()->events);
    }

    /**
     * A cancelled remember forces nothing private: the response keeps its own Cache-Control.
     */
    public function testACancelledRememberLeavesTheResponsePublic(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('/cacheable', 'iphone'));

        self::assertSame('mobile detector', $response->getContent());
        self::assertNoCookie($response);
        self::assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
        self::assertCount(1, $this->listener()->events);
    }

    /**
     * As with the route default false, a cancelled remember still expires a forged stored value.
     */
    public function testACancelledRememberStillExpiresAForgedValue(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('/cacheable', 'iphone', cookies: ['device_view' => 'phone']));

        self::assertSame('mobile detector', $response->getContent());
        self::assertSetCookie(self::scDel(), $response);
        self::assertSame('max-age=3600, private', $response->headers->get('Cache-Control'));
        self::assertCount(1, $this->listener()->events);
    }

    #[Group('EVT-REM-05')]
    public function testSetLifetime(): void
    {
        $this->boot();
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->setLifetime(3600);
        };

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSetCookie(self::sc('mobile', 3600), $response);
    }

    #[Group('EVT-REM-06')]
    public function testSetLifetimeZeroMakesASessionCookie(): void
    {
        $this->boot(self::TAC);
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->setLifetime(0);
        };

        $response = $this->handle(self::request('/view?device_view=mobile', cookies: self::GRANTED));

        self::assertSame(31536000, $this->listener()->events[0]['lifetime'], 'the listener saw the consented lifetime');
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('EVT-REM-07')]
    public function testNotDispatchedWhenTheTableSaysNo(): void
    {
        $this->boot(self::TAC + ['remember' => ['detected_view' => true]]);

        $response = $this->handle(self::request('/view', 'iphone', cookies: self::DENIED));

        self::assertSame([], $this->listener()->events);
        self::assertSame('mobile detector', $response->getContent());
        self::assertNoCookie($response);
    }

    public function testNotDispatchedWhenTheRequestStopsTheWrite(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        $this->handle(self::request('/set-remember-off?device_view=mobile'));
        self::assertSame([], $this->listener()->events, 'setRemember(false)');

        $this->handle(self::request('/no-remember?device_view=mobile'));
        self::assertSame([], $this->listener()->events, 'the remember route default false');

        $this->handle(self::request('/view', 'iphone'));
        self::assertSame([], $this->listener()->events, 'detected_view false');
    }

    public function testNotDispatchedOnASkippedRequestNorWithoutStorage(): void
    {
        $this->boot(['remember' => ['detected_view' => true], 'skip' => ['paths' => ['^/api']]]);
        $this->handle(self::request('/api/thing', 'iphone'));
        self::assertSame([], $this->listener()->events, 'a skipped request');

        $this->boot(['remember' => ['storage' => 'none']]);
        $this->handle(self::request('/view?device_view=mobile'));
        self::assertSame([], $this->listener()->events, 'remember.storage none');
    }

    #[Group('EVT-REM-09')]
    public function testNegativeLifetime(): void
    {
        $this->boot();
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->setLifetime(-1);
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The remember lifetime must be 0 or more seconds; got -1.');

        $this->handle(self::request('/view?device_view=mobile'), false);
    }

    #[Group('EVT-REM-10')]
    public function testTheEventNameIsTheClassName(): void
    {
        $this->boot();
        $dispatcher = $this->service('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $this->handle(self::request('/view?device_view=mobile'));

        self::assertTrue($dispatcher->hasListeners(BeforeRememberEvent::class));
        self::assertCount(1, $this->listener()->events, 'the listener on BeforeRememberEvent::class is called');
    }

    private function listener(): BeforeRememberListener
    {
        $listener = $this->service(BeforeRememberListener::class);
        self::assertInstanceOf(BeforeRememberListener::class, $listener);

        return $listener;
    }
}
