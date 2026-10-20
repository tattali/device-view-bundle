<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\DeviceView;
use DeviceViewBundle\Tests\App\ResponseCall;
use DeviceViewBundle\Tests\App\TestLogger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembering the view in the cookie storage, through the kernel: the remembered view as a source
 * of the resolution, the decision table of design.md section 7 without a consent provider, and
 * the expiry of a forged stored value.
 *
 * Rows that also expect a Vary header or need a consent provider stay planned; the tests without
 * a group check the rest of them.
 */
final class RememberTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const string INVALID_STORED = 'Ignored an invalid device view from the remember storage.';

    #[Group('REM-04')]
    public function testASwitchIsRememberedForTheBrowserSession(): void
    {
        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertRedirect('/view', $response);
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('REM-05')]
    public function testASwitchIsRememberedForTheLifetime(): void
    {
        $this->boot(['remember' => ['lifetime' => 3600]]);

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertRedirect('/view', $response);
        self::assertSetCookie(self::sc('mobile', 3600), $response);
    }

    public function testTheConsentedLifetimeIsNotUsedWithoutConsent(): void
    {
        $this->boot(['remember' => ['lifetime' => 60, 'consented_lifetime' => 3600]]);

        self::assertSetCookie(self::sc('mobile', 60), $this->handle(self::request('/view?device_view=mobile')));
    }

    public function testOnSwitchFalseRemembersNoSwitch(): void
    {
        $this->boot(['remember' => ['on_switch' => false]]);

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertRedirect('/view', $response);
        self::assertNoCookie($response);
    }

    public function testADetectedViewIsNotRememberedByDefault(): void
    {
        self::assertPage('mobile detector', $this->handle(self::request('/view', 'iphone')));
    }

    #[Group('REM-08')]
    public function testADetectedViewIsRememberedWhenDetectedViewIsTrue(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame('mobile detector', $response->getContent());
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('REM-09')]
    public function testADetectedViewIsRememberedForAPersistentLifetime(): void
    {
        $this->boot(['remember' => ['detected_view' => true, 'lifetime' => 31536000]]);

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame('mobile detector', $response->getContent());
        self::assertSetCookie(self::sc('mobile', 31536000), $response);
    }

    #[Group('REM-13')]
    public function testARememberedViewIsNotWrittenAgain(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        self::assertPage('mobile remembered', $this->handle(self::request('/view', 'iphone', cookies: ['device_view' => 'mobile'])));
    }

    #[Group('REM-14')]
    public function testNothingIsWrittenWhenTheViewIsNotUsed(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        self::assertPage('home page', $this->handle(self::request('/', 'iphone')));
    }

    #[Group('REM-15')]
    public function testTheDefaultViewIsNotADetectedView(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        self::assertPage('desktop default', $this->handle(self::request('/view', 'empty')));
    }

    #[Group('REM-17')]
    public function testSetRememberFalseStopsAWrite(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        self::assertPage('mobile switch', $this->handle(self::request('/set-remember-off?device_view=mobile')));
    }

    public function testSetRememberFalseStopsADetectedWrite(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        self::assertPage('mobile detector', $this->handle(self::request('/set-remember-off', 'iphone')));
    }

    #[Group('REM-18')]
    public function testSetViewWithRememberWritesLikeASwitch(): void
    {
        $response = $this->handle(self::request('/set-view/tablet?remember=1'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet forced', $response->getContent());
        self::assertSetCookie(self::sc('tablet'), $response);
    }

    public function testSetViewWithRememberFollowsOnSwitch(): void
    {
        $this->boot(['remember' => ['on_switch' => false]]);

        self::assertPage('tablet forced', $this->handle(self::request('/set-view/tablet?remember=1')));
    }

    #[Group('REM-20')]
    public function testSetViewWithoutRememberDoesNotWrite(): void
    {
        self::assertPage('tablet forced', $this->handle(self::request('/set-view/tablet')));
    }

    #[Group('VAL-01')]
    public function testTheSwitchTokenIsTrimmedAndLowerCased(): void
    {
        $response = $this->handle(self::request('/view?device_view=%20Mobile%20'));

        self::assertRedirect('/view', $response);
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('VAL-07')]
    public function testTheStoredTokenIsTrimmedAndLowerCased(): void
    {
        self::assertPage('mobile remembered', $this->handle(self::request('/view', cookies: ['device_view' => 'MOBILE'])));
    }

    public function testAStoredValueBeatsTheDetector(): void
    {
        $request = self::request('/view', 'iphone', cookies: ['device_view' => 'tablet']);

        self::assertPage('tablet remembered', $this->handle($request));
        self::assertSame(['Cookie'], self::context($request)->consultedInputs, 'the detector is not consulted');
    }

    public function testTheCookieIsConsultedWhenItHoldsNothing(): void
    {
        $request = self::request('/view', 'iphone');

        self::assertPage('mobile detector', $this->handle($request));
        self::assertSame(['Cookie', 'User-Agent'], self::context($request)->consultedInputs);
    }

    /**
     * VAL-08 also expects Vary: [Cookie, User-Agent], which comes with the Vary listener.
     */
    public function testAForgedCookieIsIgnoredAndExpired(): void
    {
        $this->boot(services: ['logger' => TestLogger::class]);
        $request = self::request('/view', cookies: ['device_view' => 'phone']);

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop detector', $response->getContent());
        self::assertSetCookie(self::scDel(), $response);
        self::assertSame(['Cookie', 'User-Agent'], self::context($request)->consultedInputs);
        self::assertSame([self::INVALID_STORED], $this->logger()->messages(LogLevel::DEBUG));
    }

    #[Group('VAL-09')]
    public function testAnArrayCookieIsIgnoredNotExpired(): void
    {
        $this->boot(services: ['logger' => TestLogger::class]);

        self::assertPage('desktop detector', $this->handle(self::request('/view', cookies: ['device_view' => ['a' => 'mobile']])));
        self::assertSame([self::INVALID_STORED], $this->logger()->messages(LogLevel::DEBUG));
    }

    #[Group('VAL-10')]
    public function testAnEmptyCookieIsIgnoredAndExpired(): void
    {
        $response = $this->handle(self::request('/view', cookies: ['device_view' => '']));

        self::assertSame('desktop detector', $response->getContent());
        self::assertSetCookie(self::scDel(), $response);
    }

    #[Group('VAL-11')]
    public function testAForgedValueIsFoundOnlyWhenRead(): void
    {
        $response = $this->handle(self::request('/', cookies: ['device_view' => 'phone']));

        self::assertPage('home page', $response);
        self::assertFalse($response->headers->has('Vary'));
    }

    public function testAValidStoredValueIsNotLogged(): void
    {
        $this->boot(services: ['logger' => TestLogger::class]);

        self::assertPage('tablet remembered', $this->handle(self::request('/view', cookies: ['device_view' => 'tablet'])));
        self::assertSame([], $this->logger()->messages(LogLevel::DEBUG));
    }

    #[Group('RES-05')]
    public function testTheSwitchBeatsTheRememberedView(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        $response = $this->handle(self::request('/view?device_view=tablet', 'iphone', cookies: ['device_view' => 'mobile']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet switch', $response->getContent());
        self::assertSetCookie(self::sc('tablet'), $response);
        self::assertFalse($response->headers->has('Vary'));
    }

    public function testASwitchReplacesAForgedCookie(): void
    {
        self::assertSetCookie(self::sc('mobile'), $this->handle(self::request('/view?device_view=mobile', cookies: ['device_view' => 'phone'])));
    }

    #[Group('VW-04')]
    public function testTabletAsMobileMapsARememberedTablet(): void
    {
        $this->boot(['tablet_as_mobile' => true]);

        self::assertPage('mobile remembered', $this->handle(self::request('/view', cookies: ['device_view' => 'tablet'])));
    }

    #[Group('VW-05')]
    public function testTabletAsMobileMapsASwitchAndTheCookieStoresTheMappedView(): void
    {
        $this->boot(['tablet_as_mobile' => true, 'switch' => ['target' => 'none']]);

        $response = $this->handle(self::request('/view?device_view=tablet'));

        self::assertSame('mobile switch', $response->getContent());
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('VW-08')]
    public function testRenamedViewsSwitch(): void
    {
        $this->boot(['views' => ['names' => ['mobile' => 'm', 'tablet' => 't', 'desktop' => 'd']]]);

        $response = $this->handle(self::request('/view?device_view=m'));

        self::assertRedirect('/view', $response);
        self::assertSetCookie('device_view=m; path=/; httponly; samesite=lax', $response);
    }

    #[Group('VW-10')]
    public function testRenamedViewsRemembered(): void
    {
        $this->boot(['views' => ['names' => ['mobile' => 'm', 'tablet' => 't', 'desktop' => 'd']]]);

        self::assertPage('t remembered', $this->handle(self::request('/view', cookies: ['device_view' => 't'])));
    }

    #[Group('VW-11')]
    public function testRenamedViewsAStoredCanonicalNameIsForged(): void
    {
        $this->boot(['views' => ['names' => ['mobile' => 'm', 'tablet' => 't', 'desktop' => 'd']]]);

        $response = $this->handle(self::request('/view', cookies: ['device_view' => 'mobile']));

        self::assertSame('d detector', $response->getContent());
        self::assertSetCookie(self::scDel(), $response);
    }

    #[Group('VW-14')]
    public function testRenamingOneViewKeepsTheOthers(): void
    {
        $this->boot(['views' => ['names' => ['mobile' => 'phone']]]);

        $response = $this->handle(self::request('/view?device_view=tablet'));

        self::assertRedirect('/view', $response);
        self::assertSetCookie(self::sc('tablet'), $response);
    }

    #[Group('HC-01')]
    public function testAResponseWritingTheCookieBecomesPrivate(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        $response = $this->handle(self::request('/cacheable', 'iphone'));

        self::assertSetCookie(self::sc('mobile'), $response);
        self::assertSame('max-age=3600, private', $response->headers->get('Cache-Control'));
    }

    #[Group('HC-02')]
    public function testPrivateWhenRememberingFalse(): void
    {
        $this->boot(['remember' => ['detected_view' => true], 'http_cache' => ['private_when_remembering' => false]]);

        $response = $this->handle(self::request('/cacheable', 'iphone'));

        self::assertSetCookie(self::sc('mobile'), $response);
        self::assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
    }

    /**
     * HC-03 also expects Vary: [Cookie], which comes with the Vary listener.
     */
    public function testAResponseWithoutTheCookieStaysPublic(): void
    {
        $response = $this->handle(self::request('/cacheable', cookies: ['device_view' => 'mobile']));

        self::assertNoCookie($response);
        self::assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
    }

    #[Group('HC-04')]
    public function testExpiringAForgedCookieAlsoMakesTheResponsePrivate(): void
    {
        $response = $this->handle(self::request('/cacheable', cookies: ['device_view' => 'phone']));

        self::assertSetCookie(self::scDel(), $response);
        self::assertSame('max-age=3600, private', $response->headers->get('Cache-Control'));
    }

    public function testExpiringAForgedCookieWithPrivateWhenRememberingFalse(): void
    {
        $this->boot(['http_cache' => ['private_when_remembering' => false]]);

        $response = $this->handle(self::request('/cacheable', cookies: ['device_view' => 'phone']));

        self::assertSetCookie(self::scDel(), $response);
        self::assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
    }

    #[Group('REG-01')]
    public function testACustomCookieNameIsHonouredOnRead(): void
    {
        $this->boot(['remember' => ['cookie' => ['name' => 'dv']]]);

        self::assertPage('mobile remembered', $this->handle(self::request('/view', cookies: ['dv' => 'mobile'])));
    }

    #[Group('REG-02')]
    public function testACustomSwitchParameterIsHonoured(): void
    {
        $this->boot(['switch' => ['parameter' => 'view']]);

        $response = $this->handle(self::request('/view?view=mobile'));

        self::assertRedirect('/view', $response);
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    /**
     * FAC-06 also calls DeviceView::setConsent(), which comes with the consent checker.
     */
    public function testChangesAfterTheResponseIsBuiltAreIgnored(): void
    {
        $this->boot(services: ['logger' => TestLogger::class]);
        $call = $this->service(ResponseCall::class);
        self::assertInstanceOf(ResponseCall::class, $call);
        $call->calls = static function (DeviceView $deviceView): void {
            $deviceView->setView('mobile', true);
            $deviceView->setRemember(true);
        };

        self::assertPage('desktop detector', $this->handle(self::request('/view')));
        self::assertSame([
            'Ignored a device view change made after the response was built.',
            'Ignored a device view change made after the response was built.',
        ], $this->logger()->messages(LogLevel::DEBUG));
    }

    public function testARememberDoesNotLeakToTheNextRequest(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        self::assertSetCookie(self::sc('tablet'), $this->handle(self::request('/set-view/tablet?remember=1', 'iphone')));
        self::assertPage('mobile detector', $this->handle(self::request('/set-remember-off', 'iphone')));
        self::assertSetCookie(self::sc('mobile'), $this->handle(self::request('/view?device_view=mobile', 'iphone')));
    }

    public function testASubRequestNeverWrites(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        self::assertSetCookie(self::sc('mobile'), $this->handle(self::request('/with-sub-request', 'iphone')));
    }

    private static function assertRedirect(string $location, Response $response): void
    {
        self::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($location, $response->headers->get('Location'));
    }

    private static function assertPage(string $body, Response $response): void
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($body, $response->getContent());
        self::assertFalse($response->headers->has('Location'));
        self::assertNoCookie($response);
    }

    private static function context(Request $request): DeviceViewContext
    {
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);

        return $context;
    }

    private function logger(): TestLogger
    {
        $logger = $this->service('logger');
        self::assertInstanceOf(TestLogger::class, $logger);

        return $logger;
    }
}
