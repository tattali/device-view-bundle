<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\EventListener\RememberListener;
use DeviceViewBundle\Remember\CookieStorage;
use DeviceViewBundle\Remember\RememberPolicy;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\Step\DetectorStep;
use DeviceViewBundle\Resolver\Step\ForcedStep;
use DeviceViewBundle\Resolver\Step\RememberedStep;
use DeviceViewBundle\Resolver\Step\SwitchStep;
use DeviceViewBundle\Resolver\ViewResolver;
use DeviceViewBundle\Tests\App\Detector\HeaderDetector;
use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\Tests\App\Resolver\RecordingResolver;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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

    public function testARequestWithoutContextIsLeftAlone(): void
    {
        $request = Request::create('/view');
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, 'not a context');

        $response = self::respond(self::listener(), $request);

        self::assertSame([], $response->headers->getCookies());
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

    private static function listener(RememberPolicy $policy = new RememberPolicy(), ViewNames $names = new ViewNames(), bool $privateWhenRemembering = true, bool $tabletAsMobile = false, bool $withStorage = true): RememberListener
    {
        return new RememberListener(self::resolver($names), $withStorage ? new CookieStorage() : null, $policy, $names, $privateWhenRemembering, $tabletAsMobile);
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
