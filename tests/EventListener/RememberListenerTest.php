<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Consent\ConsentCheckerInterface;
use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Event\BeforeRememberEvent;
use DeviceViewBundle\EventListener\RememberListener;
use DeviceViewBundle\Remember\CookieStorage;
use DeviceViewBundle\Remember\RememberPolicy;
use DeviceViewBundle\Remember\RememberTrigger;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\Step\DetectorStep;
use DeviceViewBundle\Resolver\Step\ForcedStep;
use DeviceViewBundle\Resolver\Step\RememberedStep;
use DeviceViewBundle\Resolver\Step\SwitchStep;
use DeviceViewBundle\Resolver\ViewResolver;
use DeviceViewBundle\Routing\RouteDefaults;
use DeviceViewBundle\Tests\App\Consent\CountingConsentChecker;
use DeviceViewBundle\Tests\App\Consent\HeaderConsent;
use DeviceViewBundle\Tests\App\Detector\HeaderDetector;
use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\Tests\App\Resolver\RecordingResolver;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RememberListenerTest extends TestCase
{
    public function testASwitchIsWritten(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);

        $response = self::respond(self::listener(), $request);

        self::assertSame(['device_view' => 'tablet'], self::cookies($response));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($context->responseBuilt);
        self::assertNull($context->resolved, 'the planned view is written without a resolution');
    }

    public function testThePlannedViewIsWrittenRatherThanTheResolvedView(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $context->force(DeviceType::Desktop);
        self::assertSame(DeviceType::Desktop, $context->resolve(self::resolver(), $request)->view);

        self::assertSame(['device_view' => 'tablet'], self::cookies(self::respond(self::listener(), $request)));
    }

    public function testTabletAsMobileMapsThePlannedView(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);

        self::assertSame(['device_view' => 'mobile'], self::cookies(self::respond(self::listener(tabletAsMobile: true), $request)));
        [$request, $context] = self::request('/view');
        $context->force(DeviceType::Desktop, true);
        self::assertSame(['device_view' => 'desktop'], self::cookies(self::respond(self::listener(tabletAsMobile: true), $request)));
    }

    public function testTheWrittenViewUsesItsConfiguredName(): void
    {
        [$request, $context] = self::request('/view?device_view=t');
        $context->markSwitched(DeviceType::Tablet);

        $response = self::respond(self::listener(names: new ViewNames(['tablet' => 't'])), $request);

        self::assertSame(['device_view' => 't'], self::cookies($response));
    }

    public function testTheLifetimeComesFromThePolicy(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $before = time();

        $response = self::respond(self::listener(new RememberPolicy(lifetime: 60)), $request);

        $cookie = $response->headers->getCookies()[0];
        self::assertGreaterThanOrEqual($before + 60, $cookie->getExpiresTime());
        self::assertLessThanOrEqual(time() + 60, $cookie->getExpiresTime());
    }

    public function testAPublicResponseBecomesPrivate(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $response = new Response()->setPublic()->setMaxAge(60);

        self::respond(self::listener(), $request, $response);

        self::assertSame('max-age=60, private', $response->headers->get('Cache-Control'));
    }

    public function testPrivateWhenRememberingFalseLeavesTheResponseAlone(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $response = new Response()->setPublic()->setMaxAge(60);

        self::respond(self::listener(privateWhenRemembering: false), $request, $response);

        self::assertSame(['device_view' => 'tablet'], self::cookies($response));
        self::assertSame('max-age=60, public', $response->headers->get('Cache-Control'));
    }

    public function testARequestedRememberIsWrittenLikeASwitch(): void
    {
        [$request, $context] = self::request('/view');
        $context->force(DeviceType::Mobile, true);

        self::assertSame(['device_view' => 'mobile'], self::cookies(self::respond(self::listener(new RememberPolicy(detectedView: false)), $request)));
        [$request, $context] = self::request('/view');
        $context->force(DeviceType::Mobile, true);
        self::assertSame([], self::cookies(self::respond(self::listener(new RememberPolicy(onSwitch: false, detectedView: true)), $request)));
    }

    /**
     * @return iterable<string, array{ViewSource, bool}>
     */
    public static function detectedSources(): iterable
    {
        yield 'detector' => [ViewSource::Detector, true];
        yield 'trusted header' => [ViewSource::TrustedHeader, true];
        yield 'default' => [ViewSource::Default, false];
        yield 'remembered' => [ViewSource::Remembered, false];
        yield 'forced' => [ViewSource::Forced, false];
        yield 'switch, without the switch listener' => [ViewSource::Switch, false];
    }

    #[DataProvider('detectedSources')]
    public function testOnlyADetectedViewIsWrittenWithTheDetectedTrigger(ViewSource $source, bool $written): void
    {
        [$request, $context] = self::request('/view');
        $context->resolve(new RecordingResolver([new ResolvedView(DeviceType::Tablet, $source)]), $request);

        $response = self::respond(self::listener(new RememberPolicy(detectedView: true)), $request);

        self::assertSame($written ? ['device_view' => 'tablet'] : [], self::cookies($response));
    }

    public function testADetectedViewFollowsDetectedView(): void
    {
        [$request, $context] = self::request('/view');
        $context->resolve(new RecordingResolver([new ResolvedView(DeviceType::Tablet, ViewSource::Detector)]), $request);
        $response = new Response()->setPublic();

        self::respond(self::listener(new RememberPolicy(detectedView: false)), $request, $response);

        self::assertSame([], self::cookies($response));
        self::assertSame('public', $response->headers->get('Cache-Control'), 'nothing was written');
    }

    public function testOnSwitchFalseWritesNothing(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $response = new Response()->setPublic();

        self::respond(self::listener(new RememberPolicy(onSwitch: false)), $request, $response);

        self::assertSame([], self::cookies($response));
        self::assertSame('public', $response->headers->get('Cache-Control'));
        self::assertNull($context->resolved, 'nothing to write: nothing to resolve');
    }

    public function testPrivateWhenRememberingIsTheDefaultAndTabletAsMobileIsNot(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $response = new Response()->setPublic();
        $names = new ViewNames();

        self::respond(new RememberListener(self::resolver($names), new CookieStorage(), new RememberPolicy(), $names), $request, $response);

        self::assertSame('private', $response->headers->get('Cache-Control'));
        self::assertSame(['device_view' => 'tablet'], self::cookies($response));
    }

    public function testAnUnresolvedViewIsNotWritten(): void
    {
        [$request, $context] = self::request('/view');
        $request->headers->set('X-Test-View', 'tablet');
        $response = new Response()->setPublic();

        self::respond(self::listener(new RememberPolicy(detectedView: true)), $request, $response);

        self::assertSame([], self::cookies($response));
        self::assertNull($context->resolved, 'the listener resolves nothing for a detected write');
        self::assertSame('public', $response->headers->get('Cache-Control'));
    }

    public function testSetRememberFalseStopsTheWrite(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $context->setRemember(false);

        self::assertSame([], self::cookies(self::respond(self::listener(), $request)));
    }

    public function testSetRememberTrueDoesNotForceAWrite(): void
    {
        [$request, $context] = self::request('/view');
        $request->headers->set('X-Test-View', 'tablet');
        $context->resolve(self::resolver(), $request);
        $context->setRemember(true);

        self::assertSame([], self::cookies(self::respond(self::listener(), $request)));
    }

    public function testAForgedValueIsExpiredAndMakesTheResponsePrivate(): void
    {
        [$request, $context] = self::request('/view');
        $context->markForgedStoredValue();
        $response = new Response()->setPublic();

        self::respond(self::listener(), $request, $response);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertTrue($cookies[0]->isCleared());
        self::assertSame('private', $response->headers->get('Cache-Control'));
    }

    public function testAForgedValueIsExpiredEvenWhenRememberingIsOff(): void
    {
        [$request, $context] = self::request('/view');
        $context->markForgedStoredValue();
        $context->setRemember(false);

        $response = self::respond(self::listener(), $request);

        self::assertTrue($response->headers->getCookies()[0]->isCleared());
    }

    public function testAValueTheStorageCannotRemoveLeavesTheResponseAlone(): void
    {
        $request = Request::create('/view', cookies: ['device_view' => ['a' => 'mobile']]);
        $context = new DeviceViewContext();
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);
        $context->markForgedStoredValue();
        $response = new Response()->setPublic();

        self::respond(self::listener(), $request, $response);

        self::assertSame([], $response->headers->getCookies());
        self::assertSame('public', $response->headers->get('Cache-Control'), 'nothing was removed');
    }

    public function testAWriteReplacesAForgedValue(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $context->markForgedStoredValue();

        self::assertSame(['device_view' => 'tablet'], self::cookies(self::respond(self::listener(), $request)));
    }

    public function testASkippedRequestNeitherWritesNorExpires(): void
    {
        $request = Request::create('/view?device_view=tablet');
        $context = new DeviceViewContext(true);
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);
        $context->force(DeviceType::Tablet, true);
        $context->markForgedStoredValue();
        $response = new Response()->setPublic();

        self::respond(self::listener(), $request, $response);

        self::assertSame([], $response->headers->getCookies());
        self::assertSame('public', $response->headers->get('Cache-Control'));
        self::assertTrue($context->responseBuilt);
    }

    public function testASubRequestIsLeftAlone(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $response = new Response();

        self::listener()->onKernelResponse(new ResponseEvent(new Kernel(), $request, HttpKernelInterface::SUB_REQUEST, $response));

        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($context->responseBuilt);
    }

    public function testWithoutStorageNothingIsWrittenNorExpired(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $context->markForgedStoredValue();

        $response = self::respond(self::listener(withStorage: false), $request, new Response()->setPublic()->setMaxAge(60));

        self::assertSame([], $response->headers->getCookies());
        self::assertSame('max-age=60, public', $response->headers->get('Cache-Control'), 'nothing was remembered');
        self::assertTrue($context->responseBuilt);
    }

    /**
     * @return iterable<string, array{string|null, bool|null, int|null}>
     */
    public static function switchConsents(): iterable
    {
        yield 'granted' => ['yes', null, 3600];
        yield 'denied' => ['no', null, 60];
        yield 'unknown' => [null, null, 60];
        yield 'denied, overridden to granted' => ['no', true, 3600];
        yield 'granted, overridden to denied' => ['yes', false, 60];
    }

    #[DataProvider('switchConsents')]
    public function testTheConsentGivesTheLifetimeOfASwitch(?string $header, ?bool $override, int $lifetime): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        if (null !== $header) {
            $request->headers->set('X-Consent', $header);
        }
        $context->markSwitched(DeviceType::Tablet);
        if (null !== $override) {
            $context->setConsent($override);
        }
        $checker = new CountingConsentChecker(new HeaderConsent());

        $response = self::respond(self::listener(self::consentPolicy(), consentChecker: $checker), $request);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertEqualsWithDelta(time() + $lifetime, $cookies[0]->getExpiresTime(), 1);
        self::assertSame(null === $override ? 1 : 0, $checker->calls, 'an override is not asked to the checker');
    }

    public function testWithoutACheckerTheOverrideGivesTheLifetime(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $context->setConsent(true);

        $cookies = self::respond(self::listener(new RememberPolicy(lifetime: 60, consentedLifetime: 3600)), $request)->headers->getCookies();

        self::assertCount(1, $cookies);
        self::assertEqualsWithDelta(time() + 3600, $cookies[0]->getExpiresTime(), 1);
    }

    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function detectedConsents(): iterable
    {
        yield 'granted' => ['yes', true];
        yield 'denied' => ['no', false];
        yield 'unknown' => [null, false];
    }

    #[DataProvider('detectedConsents')]
    public function testADetectedViewNeedsTheConsentWhenAProviderIsSet(?string $header, bool $written): void
    {
        [$request, $context] = self::request('/view');
        if (null !== $header) {
            $request->headers->set('X-Consent', $header);
        }
        $context->resolve(new RecordingResolver([new ResolvedView(DeviceType::Tablet, ViewSource::Detector)]), $request);
        $response = new Response()->setPublic();

        self::respond(self::listener(self::consentPolicy(), consentChecker: new HeaderConsent()), $request, $response);

        self::assertSame($written ? ['device_view' => 'tablet'] : [], self::cookies($response));
        self::assertSame($written, $response->headers->hasCacheControlDirective('private'));
    }

    /**
     * @return iterable<string, array{\Closure(Request, DeviceViewContext): void, RememberPolicy}>
     */
    public static function noWrites(): iterable
    {
        yield 'nothing planned, nothing resolved' => [static function (): void {}, self::consentPolicy()];
        yield 'a view the detector did not give' => [
            static function (Request $request, DeviceViewContext $context): void {
                $context->resolve(new RecordingResolver([new ResolvedView(DeviceType::Tablet, ViewSource::Default)]), $request);
            },
            self::consentPolicy(),
        ];
        yield 'setRemember(false)' => [
            static function (Request $request, DeviceViewContext $context): void {
                $context->markSwitched(DeviceType::Tablet);
                $context->setRemember(false);
            },
            self::consentPolicy(),
        ];
        yield 'on_switch false' => [
            static function (Request $request, DeviceViewContext $context): void {
                $context->markSwitched(DeviceType::Tablet);
            },
            new RememberPolicy(onSwitch: false, detectedView: true, consentProvider: true),
        ];
        yield 'detected_view false' => [
            static function (Request $request, DeviceViewContext $context): void {
                $context->resolve(new RecordingResolver([new ResolvedView(DeviceType::Tablet, ViewSource::Detector)]), $request);
            },
            new RememberPolicy(onSwitch: true, detectedView: false, consentProvider: true),
        ];
    }

    /**
     * REM-26: the checker is asked only when a write is planned.
     *
     * @param \Closure(Request, DeviceViewContext): void $prepare
     */
    #[DataProvider('noWrites')]
    public function testTheCheckerIsNotAskedWithoutAPlannedWrite(\Closure $prepare, RememberPolicy $policy): void
    {
        [$request, $context] = self::request('/view');
        $request->headers->set('X-Consent', 'yes');
        $prepare($request, $context);
        $checker = new CountingConsentChecker(new HeaderConsent());

        $response = self::respond(self::listener($policy, consentChecker: $checker), $request);

        self::assertSame([], self::cookies($response));
        self::assertSame(0, $checker->calls);
    }

    public function testASkippedRequestDoesNotAskTheChecker(): void
    {
        $request = Request::create('/view?device_view=tablet');
        $context = new DeviceViewContext(skipped: true);
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);
        $context->markSwitched(DeviceType::Tablet);
        $checker = new CountingConsentChecker(new HeaderConsent());

        self::respond(self::listener(self::consentPolicy(), consentChecker: $checker), $request);

        self::assertSame(0, $checker->calls);
    }

    public function testARequestWithoutContextIsLeftAlone(): void
    {
        $request = Request::create('/view');
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, 'not a context');

        $response = self::respond(self::listener(), $request);

        self::assertSame([], $response->headers->getCookies());
    }

    public function testTheRouteDefaultFalseStopsTheWrite(): void
    {
        [$request, $context] = self::request('/no-remember?device_view=tablet');
        $request->attributes->set('_device_view_remember', 'false');
        $context->markSwitched(DeviceType::Tablet);
        $checker = new CountingConsentChecker(new HeaderConsent());

        $response = self::respond(self::routeListener($checker), $request, new Response()->setPublic());

        self::assertSame([], self::cookies($response));
        self::assertSame('public', $response->headers->get('Cache-Control'), 'nothing was stored');
        self::assertSame(0, $checker->calls, 'the consent is not asked for a write the route stops');
        self::assertFalse($context->routeRemember);
    }

    public function testTheRouteDefaultTrueLetsThePolicyDecide(): void
    {
        [$request, $context] = self::request('/remember?device_view=tablet');
        $request->attributes->set('_device_view_remember', true);
        $context->markSwitched(DeviceType::Tablet);

        self::assertSame(['device_view' => 'tablet'], self::cookies(self::respond(self::routeListener(), $request)));
    }

    public function testTheRouteDefaultFalseStillExpiresAForgedValue(): void
    {
        [$request, $context] = self::request('/no-remember?device_view=tablet');
        $request->attributes->set('_device_view_remember', false);
        $context->markSwitched(DeviceType::Tablet);
        $context->markForgedStoredValue();

        $response = self::respond(self::routeListener(), $request);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertTrue($cookies[0]->isCleared());
    }

    public function testWithoutTheReaderTheRouteDefaultIsNotRead(): void
    {
        [$request, $context] = self::request('/no-remember?device_view=tablet');
        $request->attributes->set('_device_view_remember', false);
        $context->markSwitched(DeviceType::Tablet);

        self::assertSame(['device_view' => 'tablet'], self::cookies(self::respond(self::listener(), $request)));
        self::assertNull($context->routeRemember);
    }

    public function testBeforeRememberEventIsDispatchedBeforeASwitchIsWritten(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $request->headers->set('X-Consent', 'no');
        $context->markSwitched(DeviceType::Tablet);
        $events = [];
        $response = new Response();
        $cookiesWhenDispatched = null;
        $dispatcher = self::dispatcher($events, static function () use ($response, &$cookiesWhenDispatched): void {
            $cookiesWhenDispatched = \count($response->headers->getCookies());
        });

        self::respond(self::listener(self::consentPolicy(), tabletAsMobile: true, consentChecker: new HeaderConsent(), dispatcher: $dispatcher), $request, $response);

        self::assertCount(1, $events);
        self::assertSame(RememberTrigger::Switch, $events[0]->getTrigger());
        self::assertSame(DeviceType::Mobile, $events[0]->getView(), 'the view that is written, after tablet_as_mobile');
        self::assertFalse($events[0]->getConsent());
        self::assertSame(60, $events[0]->getLifetime());
        self::assertSame(0, $cookiesWhenDispatched, 'dispatched before the write');
        self::assertSame(['device_view' => 'mobile'], self::cookies($response));
    }

    public function testBeforeRememberEventGetsTheTriggerAndConsentOfADetectedView(): void
    {
        [$request, $context] = self::request('/view');
        $request->headers->set('X-Consent', 'yes');
        $context->resolve(new RecordingResolver([new ResolvedView(DeviceType::Tablet, ViewSource::Detector)]), $request);
        $events = [];

        $response = self::respond(self::listener(self::consentPolicy(), consentChecker: new HeaderConsent(), dispatcher: self::dispatcher($events)), $request);

        self::assertCount(1, $events);
        self::assertSame(RememberTrigger::Detected, $events[0]->getTrigger());
        self::assertSame(DeviceType::Tablet, $events[0]->getView());
        self::assertTrue($events[0]->getConsent());
        self::assertSame(3600, $events[0]->getLifetime());
        self::assertEqualsWithDelta(time() + 3600, $response->headers->getCookies()[0]->getExpiresTime(), 1);
    }

    public function testTheConsentOverrideReachesBeforeRememberEvent(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $request->headers->set('X-Consent', 'yes');
        $context->markSwitched(DeviceType::Tablet);
        $context->setConsent(false);
        $events = [];

        self::respond(self::listener(self::consentPolicy(), consentChecker: new HeaderConsent(), dispatcher: self::dispatcher($events)), $request);

        self::assertCount(1, $events);
        self::assertFalse($events[0]->getConsent());
        self::assertSame(60, $events[0]->getLifetime());
    }

    /**
     * @return iterable<string, array{int, int|null}>
     */
    public static function eventLifetimes(): iterable
    {
        yield 'longer' => [7200, 7200];
        yield 'browser session' => [0, null];
    }

    #[DataProvider('eventLifetimes')]
    public function testTheLifetimeOfBeforeRememberEventIsWritten(int $lifetime, ?int $maxAge): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $events = [];
        $dispatcher = self::dispatcher($events, static function (BeforeRememberEvent $event) use ($lifetime): void {
            $event->setLifetime($lifetime);
        });

        $response = self::respond(self::listener(new RememberPolicy(lifetime: 60), dispatcher: $dispatcher), $request);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        if (null === $maxAge) {
            self::assertSame(0, $cookies[0]->getExpiresTime(), 'a browser-session cookie');
        } else {
            self::assertEqualsWithDelta(time() + $maxAge, $cookies[0]->getExpiresTime(), 1);
        }
    }

    public function testACancelledBeforeRememberEventWritesNothing(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $events = [];
        $dispatcher = self::dispatcher($events, static function (BeforeRememberEvent $event): void {
            $event->cancel();
        });

        $response = self::respond(self::listener(dispatcher: $dispatcher), $request, new Response()->setPublic()->setMaxAge(60));

        self::assertCount(1, $events);
        self::assertSame([], self::cookies($response));
        self::assertSame('max-age=60, public', $response->headers->get('Cache-Control'), 'nothing private is forced');
    }

    public function testACancelledBeforeRememberEventStillExpiresAForgedValue(): void
    {
        [$request, $context] = self::request('/view');
        $context->resolve(new RecordingResolver([new ResolvedView(DeviceType::Tablet, ViewSource::Detector)]), $request);
        $context->markForgedStoredValue();
        $events = [];
        $dispatcher = self::dispatcher($events, static function (BeforeRememberEvent $event): void {
            $event->cancel();
        });

        $response = self::respond(self::listener(new RememberPolicy(detectedView: true), dispatcher: $dispatcher), $request, new Response()->setPublic());

        self::assertCount(1, $events);
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertTrue($cookies[0]->isCleared());
        self::assertSame('private', $response->headers->get('Cache-Control'));
    }

    public function testTheEventOfTheSwitchListenerIsNotDispatchedAgain(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $request->headers->set('X-Consent', 'no');
        $context->markSwitched(DeviceType::Tablet);
        $recorded = new BeforeRememberEvent(RememberTrigger::Switch, DeviceType::Mobile, false, 60);
        $recorded->setLifetime(7200);
        $context->recordBeforeRemember($recorded);
        $events = [];

        $response = self::respond(self::listener(self::consentPolicy(), tabletAsMobile: true, consentChecker: new HeaderConsent(), dispatcher: self::dispatcher($events)), $request);

        self::assertSame([], $events);
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('mobile', $cookies[0]->getValue());
        self::assertEqualsWithDelta(time() + 7200, $cookies[0]->getExpiresTime(), 1, 'the lifetime of the recorded event');
    }

    public function testACancelledEventOfTheSwitchListenerWritesNothing(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        $recorded = new BeforeRememberEvent(RememberTrigger::Switch, DeviceType::Tablet, null, 0);
        $recorded->cancel();
        $context->recordBeforeRemember($recorded);
        $events = [];

        $response = self::respond(self::listener(dispatcher: self::dispatcher($events)), $request, new Response()->setPublic());

        self::assertSame([], $events);
        self::assertSame([], self::cookies($response));
        self::assertSame('public', $response->headers->get('Cache-Control'));
    }

    /**
     * @return iterable<string, array{DeviceType, bool|null}>
     */
    public static function staleEvents(): iterable
    {
        yield 'another view' => [DeviceType::Desktop, false];
        yield 'another consent' => [DeviceType::Tablet, null];
    }

    /**
     * A view or a consent changed after the switch listener dispatched the event: the response
     * dispatches it again with what it writes.
     */
    #[DataProvider('staleEvents')]
    public function testAStaleEventOfTheSwitchListenerIsDispatchedAgain(DeviceType $view, ?bool $consent): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $request->headers->set('X-Consent', 'no');
        $context->markSwitched(DeviceType::Tablet);
        $recorded = new BeforeRememberEvent(RememberTrigger::Switch, $view, $consent, 60);
        $recorded->cancel();
        $context->recordBeforeRemember($recorded);
        $events = [];

        $response = self::respond(self::listener(self::consentPolicy(), consentChecker: new HeaderConsent(), dispatcher: self::dispatcher($events)), $request);

        self::assertCount(1, $events);
        self::assertSame(DeviceType::Tablet, $events[0]->getView());
        self::assertFalse($events[0]->getConsent());
        self::assertSame(['device_view' => 'tablet'], self::cookies($response));
    }

    /**
     * @param \Closure(Request, DeviceViewContext): void $prepare
     */
    #[DataProvider('noWrites')]
    public function testBeforeRememberEventIsNotDispatchedWithoutAWrite(\Closure $prepare, RememberPolicy $policy): void
    {
        [$request, $context] = self::request('/view');
        $request->headers->set('X-Consent', 'no');
        $prepare($request, $context);
        $events = [];

        self::respond(self::listener($policy, consentChecker: new HeaderConsent(), dispatcher: self::dispatcher($events)), $request);

        self::assertSame([], $events);
    }

    public function testBeforeRememberEventIsNotDispatchedWhenTheConsentStopsTheWrite(): void
    {
        [$request, $context] = self::request('/view');
        $request->headers->set('X-Consent', 'no');
        $context->resolve(new RecordingResolver([new ResolvedView(DeviceType::Tablet, ViewSource::Detector)]), $request);
        $events = [];

        $response = self::respond(self::listener(self::consentPolicy(), consentChecker: new HeaderConsent(), dispatcher: self::dispatcher($events)), $request);

        self::assertSame([], $events);
        self::assertSame([], self::cookies($response));
    }

    public function testBeforeRememberEventIsNotDispatchedWhenTheRouteStopsTheWrite(): void
    {
        [$request, $context] = self::request('/no-remember?device_view=tablet');
        $request->attributes->set('_device_view_remember', false);
        $context->markSwitched(DeviceType::Tablet);
        $events = [];
        $names = new ViewNames();
        $listener = new RememberListener(self::resolver($names), new CookieStorage(), new RememberPolicy(), $names, routeDefaults: new RouteDefaults(), dispatcher: self::dispatcher($events));

        self::respond($listener, $request);

        self::assertSame([], $events);
    }

    public function testBeforeRememberEventIsNotDispatchedOnASkippedRequestNorWithoutStorage(): void
    {
        $request = Request::create('/view?device_view=tablet');
        $context = new DeviceViewContext(skipped: true);
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);
        $context->markSwitched(DeviceType::Tablet);
        $events = [];
        self::respond(self::listener(dispatcher: self::dispatcher($events)), $request);
        self::assertSame([], $events, 'a skipped request');

        [$request, $context] = self::request('/view?device_view=tablet');
        $context->markSwitched(DeviceType::Tablet);
        self::respond(self::listener(withStorage: false, dispatcher: self::dispatcher($events)), $request);
        self::assertSame([], $events, 'no storage');
    }

    private static function routeListener(?ConsentCheckerInterface $consentChecker = null): RememberListener
    {
        $names = new ViewNames();

        return new RememberListener(self::resolver($names), new CookieStorage(), new RememberPolicy(), $names, consentChecker: $consentChecker, routeDefaults: new RouteDefaults());
    }

    /**
     * @return array{Request, DeviceViewContext}
     */
    private static function request(string $uri): array
    {
        $request = Request::create($uri);
        $context = new DeviceViewContext();
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);

        return [$request, $context];
    }

    private static function respond(RememberListener $listener, Request $request, Response $response = new Response()): Response
    {
        $listener->onKernelResponse(new ResponseEvent(new Kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }

    private static function resolver(ViewNames $names = new ViewNames()): ViewResolver
    {
        return new ViewResolver([
            new ForcedStep(),
            new SwitchStep(new SwitchParameter($names)),
            new RememberedStep(new CookieStorage(), $names),
            new DetectorStep(new HeaderDetector()),
        ]);
    }

    private static function listener(RememberPolicy $policy = new RememberPolicy(), ViewNames $names = new ViewNames(), bool $privateWhenRemembering = true, bool $tabletAsMobile = false, bool $withStorage = true, ?ConsentCheckerInterface $consentChecker = null, ?EventDispatcherInterface $dispatcher = null): RememberListener
    {
        return new RememberListener(self::resolver($names), $withStorage ? new CookieStorage() : null, $policy, $names, $privateWhenRemembering, $tabletAsMobile, $consentChecker, dispatcher: $dispatcher);
    }

    /**
     * A dispatcher whose only BeforeRememberEvent listener adds each event to $events, then calls
     * $onEvent.
     *
     * @param list<BeforeRememberEvent>                  $events
     * @param (\Closure(BeforeRememberEvent): void)|null $onEvent
     */
    private static function dispatcher(array &$events, ?\Closure $onEvent = null): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BeforeRememberEvent::class, static function (BeforeRememberEvent $event) use (&$events, $onEvent): void {
            $events[] = $event;
            if (null !== $onEvent) {
                $onEvent($event);
            }
        });

        return $dispatcher;
    }

    /**
     * A policy with a consent provider, which remembers switches and detected views.
     */
    private static function consentPolicy(): RememberPolicy
    {
        return new RememberPolicy(onSwitch: true, detectedView: true, lifetime: 60, consentedLifetime: 3600, consentProvider: true);
    }

    /**
     * @return array<string, string|null> the cookies the response sets, by name
     */
    private static function cookies(Response $response): array
    {
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $cookies;
    }
}
