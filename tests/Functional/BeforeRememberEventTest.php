<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Event\BeforeRedirectEvent;
use DeviceViewBundle\Event\BeforeRememberEvent;
use DeviceViewBundle\Remember\RememberTrigger;
use DeviceViewBundle\Tests\App\Event\BeforeRedirectListener;
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

    /** Mobile served on m.example.com, desktop on www.example.com. */
    private const array MD = ['redirect' => [
        'mobile' => ['enabled' => true, 'host' => 'https://m.example.com'],
        'desktop' => ['enabled' => true, 'host' => 'https://www.example.com'],
    ]];

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
    #[Group('EVT-REM-12')]
    public function testACancelledRememberLeavesTheResponsePublic(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('/cacheable', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertNoCookie($response);
        self::assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
        self::assertCount(1, $this->listener()->events);
    }

    /**
     * As with the route default false, a cancelled remember still expires a forged stored value.
     */
    #[Group('EVT-REM-11')]
    public function testACancelledRememberStillExpiresAForgedValue(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('/cacheable', 'iphone', cookies: ['device_view' => 'phone']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSetCookie(self::scDel(), $response);
        self::assertSame('max-age=3600, private', $response->headers->get('Cache-Control'));
        self::assertCount(1, $this->listener()->events);
    }

    /**
     * The switch listener sees the cancel when the switched view is served on the current host,
     * as REG-21 with remember.on_switch false: the switch stays on its page, so the device
     * redirect cannot send it back to the host that remembers another view.
     */
    #[Group('EVT-REM-13')]
    public function testACancelledSwitchRememberCausesNoLoop(): void
    {
        $this->boot(self::MD);
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->cancel();
        };

        $first = $this->handle(self::request('https://m.example.com/view', 'iphone', cookies: ['device_view' => 'desktop']));
        self::assertSame(302, $first->getStatusCode());
        self::assertSame('https://www.example.com/view?device_view=desktop', $first->headers->get('Location'));
        self::assertSame([], $this->listener()->events, 'the view comes from the storage: nothing to remember');

        $response = $this->handle(self::request('https://www.example.com/view?device_view=desktop', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop switch', $response->getContent());
        self::assertNull($response->headers->get('Location'));
        self::assertNoCookie($response);
        self::assertSame([['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Desktop, 'consent' => null, 'lifetime' => 0]], $this->listener()->events);
    }

    /**
     * When the switch listener dispatches the event, the response writes what the event says and
     * does not dispatch it again.
     */
    #[Group('EVT-REM-14')]
    public function testTheSwitchListenerDispatchesTheEventOnce(): void
    {
        $this->boot(self::MD);
        $this->listener()->onEvent = static function (BeforeRememberEvent $event): void {
            $event->setLifetime(3600);
        };

        $response = $this->handle(self::request('https://www.example.com/view?device_view=desktop', 'iphone'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
        self::assertSetCookie(self::scs('desktop', 3600), $response);
        self::assertCount(1, $this->listener()->events);
    }

    /**
     * The switch listener gets the consent checker of consent.provider: its event has the
     * consent and the lifetime the response writes, so the response does not dispatch it again.
     */
    #[Group('EVT-REM-15')]
    public function testTheSwitchListenerEventHasTheConsent(): void
    {
        $this->boot(self::TAC + self::MD);

        $response = $this->handle(self::request('https://www.example.com/view?device_view=desktop', 'iphone', cookies: self::GRANTED));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
        self::assertSetCookie(self::scs('desktop', 31536000), $response);
        self::assertSame([['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Desktop, 'consent' => true, 'lifetime' => 31536000]], $this->listener()->events);
    }

    /**
     * Without a storage, the switch listener dispatches no event: nothing is written.
     */
    #[Group('EVT-REM-16')]
    public function testTheSwitchListenerDispatchesNothingWithoutStorage(): void
    {
        $this->boot(self::MD + ['remember' => ['storage' => 'none']]);

        $response = $this->handle(self::request('https://www.example.com/view?device_view=desktop', 'iphone'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
        self::assertNoCookie($response);
        self::assertSame([], $this->listener()->events);
    }

    /**
     * The switch listener applies tablet_as_mobile: its event has the view that is written.
     */
    #[Group('EVT-REM-17')]
    public function testTheSwitchListenerEventHasTheMappedView(): void
    {
        $this->boot(['tablet_as_mobile' => true] + self::MD);

        $response = $this->handle(self::request('https://m.example.com/view?device_view=tablet', 'iphone'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
        self::assertSetCookie(self::scs('mobile'), $response);
        self::assertSame([['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Mobile, 'consent' => null, 'lifetime' => 0]], $this->listener()->events);
    }

    /**
     * With the switch redirect cancelled, the controller runs: a consent or a view it changes
     * makes the response dispatch the event again, with the values it writes.
     */
    public function testAControllerChangeAfterTheSwitchEventDispatchesItAgain(): void
    {
        $this->boot(self::TAC + self::MD);
        $this->redirectListener()->onEvent = static function (BeforeRedirectEvent $event): void {
            $event->cancel();
        };

        $response = $this->handle(self::request('https://www.example.com/view?device_view=desktop', 'iphone'));
        self::assertSame('desktop switch', $response->getContent());
        self::assertSetCookie(self::scs('desktop'), $response);
        self::assertCount(1, $this->listener()->events, 'nothing changed: the event of the switch listener is used');

        $response = $this->handle(self::request('https://www.example.com/set-consent/1?device_view=desktop', 'iphone'));
        self::assertSetCookie(self::scs('desktop', 31536000), $response);
        self::assertSame([
            ['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Desktop, 'consent' => null, 'lifetime' => 0],
            ['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Desktop, 'consent' => true, 'lifetime' => 31536000],
        ], $this->listener()->events);

        $response = $this->handle(self::request('https://www.example.com/set-view/tablet?remember=1&device_view=desktop', 'iphone'));
        self::assertSetCookie(self::scs('tablet'), $response);
        self::assertSame([
            ['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Desktop, 'consent' => null, 'lifetime' => 0],
            ['trigger' => RememberTrigger::Switch, 'view' => DeviceType::Tablet, 'consent' => null, 'lifetime' => 0],
        ], $this->listener()->events);
    }

    /**
     * A remember cancelled by the switch listener stays cancelled on the response.
     */
    public function testARememberCancelledOnTheRequestIsNotWritten(): void
    {
        $this->boot(self::MD);
        $calls = 0;
        $this->listener()->onEvent = static function (BeforeRememberEvent $event) use (&$calls): void {
            if (1 === ++$calls) {
                $event->cancel();
            }
        };

        $response = $this->handle(self::request('https://www.example.com/view?device_view=desktop', 'iphone'));

        self::assertSame('desktop switch', $response->getContent());
        self::assertNoCookie($response);
        self::assertSame(1, $calls);
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

    private function redirectListener(): BeforeRedirectListener
    {
        $listener = $this->service(BeforeRedirectListener::class);
        self::assertInstanceOf(BeforeRedirectListener::class, $listener);

        return $listener;
    }

    private function listener(): BeforeRememberListener
    {
        $listener = $this->service(BeforeRememberListener::class);
        self::assertInstanceOf(BeforeRememberListener::class, $listener);

        return $listener;
    }
}
