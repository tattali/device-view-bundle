<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional\Consent;

use DeviceViewBundle\Consent\AxeptioConsentChecker;
use DeviceViewBundle\Tests\Consent\ConsentFixtures;
use DeviceViewBundle\Tests\Functional\AssertsCookies;
use DeviceViewBundle\Tests\Functional\ChecksConsent;
use DeviceViewBundle\Tests\Functional\HandlesRequests;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The Axeptio reader through the kernel: the CON-AX rows of docs/scenarios.md.
 */
final class AxeptioTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array AX = ['consent' => ['provider' => 'axeptio']];

    private const string GRANTED = '{"$$token":"k1x8b3","$$date":"2026-10-20T10:00:00.000Z","$$cookiesVersion":{"name":"site-en","identifier":"64f0"},"device_view":true,"$$completed":true}';

    public function testTheProviderNamesTheReader(): void
    {
        $this->boot(self::AX);

        self::assertInstanceOf(AxeptioConsentChecker::class, $this->service('test.device_view.consent_checker'));
    }

    #[Group('CON-AX-01')]
    public function testGranted(): void
    {
        $this->assertConsentRow(self::AX, ['axeptio_cookies' => self::GRANTED], true);
    }

    #[Group('CON-AX-02')]
    public function testDenied(): void
    {
        $this->assertConsentRow(self::AX, ['axeptio_cookies' => str_replace('"device_view":true', '"device_view":false', self::GRANTED)], false);
    }

    #[Group('CON-AX-03')]
    public function testNotCompleted(): void
    {
        $this->assertConsentRow(self::AX, ['axeptio_cookies' => '{"device_view":true,"$$completed":false}'], null);
    }

    #[Group('CON-AX-04')]
    public function testVendorAbsent(): void
    {
        $this->assertConsentRow(self::AX, ['axeptio_cookies' => '{"$$completed":true,"analytics":true}'], null);
    }

    #[Group('CON-AX-05')]
    public function testNoCookie(): void
    {
        $this->assertConsentRow(self::AX, [], null);
    }

    #[Group('CON-AX-06')]
    public function testValueStillUrlEncoded(): void
    {
        $this->assertConsentRow(self::AX, ['axeptio_cookies' => '%7B%22%24%24completed%22%3Atrue%2C%22device_view%22%3Atrue%7D'], true);
    }

    #[Group('CON-AX-07')]
    public function testNotABoolean(): void
    {
        $this->assertConsentRow(self::AX, ['axeptio_cookies' => '{"$$completed":true,"device_view":1}'], null);
    }

    #[Group('CON-AX-08')]
    public function testGarbage(): void
    {
        $this->assertConsentRow(self::AX, ['axeptio_cookies' => '[[['], null);
    }

    #[Group('CON-AX-09')]
    public function testArrayCookie(): void
    {
        $this->assertConsentRow(self::AX, ['axeptio_cookies' => ['device_view' => 'true']], null);
    }

    #[Group('CON-AX-10')]
    public function testCustomCookieNameAndKey(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'axeptio', 'cookie' => 'axeptio_site', 'key' => 'views']],
            ['axeptio_site' => '{"$$completed":true,"views":true}'],
            true,
        );
    }

    /**
     * The raw Cookie header the SDK sends, decoded as PHP does: URL-encoded JSON.
     */
    #[Group('CON-AX-01')]
    public function testTheRawHeaderOfTheSdk(): void
    {
        $this->assertConsentRow(
            self::AX,
            ConsentFixtures::cookies('axeptio_cookies='.rawurlencode(self::GRANTED)),
            true,
        );
    }
}
