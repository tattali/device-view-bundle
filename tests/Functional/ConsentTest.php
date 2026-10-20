<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Consent\TarteaucitronConsentChecker;
use DeviceViewBundle\Tests\App\Consent\HeaderConsent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The consent checker of consent.provider, through the kernel: no provider, a custom checker
 * service and a built-in reader (design.md section 8). Each built-in reader has its own test
 * class in tests/Functional/Consent.
 */
final class ConsentTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array HEADER_CONSENT = ['consent' => ['provider' => 'app.header_consent']];

    #[Group('CON-01')]
    public function testWithoutProviderThereIsNoChecker(): void
    {
        $container = $this->boot();

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSetCookie(self::sc('mobile'), $response);
        self::assertFalse($container->has('test.device_view.consent_checker'), 'no device_view.consent_checker service');
        self::assertFalse($container->has('test.consent_checker_interface'), 'no ConsentCheckerInterface alias');
    }

    #[Group('CON-02')]
    public function testACustomCheckerGrantingConsent(): void
    {
        $this->assertConsentRow(self::HEADER_CONSENT, [], true, ['X-Consent' => 'yes'], ['app.header_consent' => HeaderConsent::class]);
    }

    public function testACustomCheckerDenyingConsent(): void
    {
        $this->assertConsentRow(self::HEADER_CONSENT, [], false, ['X-Consent' => 'no'], ['app.header_consent' => HeaderConsent::class]);
    }

    #[Group('CON-03')]
    public function testACustomCheckerAnsweringNull(): void
    {
        $this->assertConsentRow(self::HEADER_CONSENT, [], null, services: ['app.header_consent' => HeaderConsent::class]);
    }

    #[Group('CON-04')]
    public function testTheInterfaceAliasFollowsTheConfiguration(): void
    {
        $this->boot(self::HEADER_CONSENT, ['app.header_consent' => HeaderConsent::class]);

        self::assertSame($this->service('app.header_consent'), $this->service('test.device_view.consent_checker'));
        self::assertSame($this->service('app.header_consent'), $this->service('test.consent_checker_interface'));
    }

    public function testABuiltInReaderIsTheChecker(): void
    {
        $this->boot(['consent' => ['provider' => 'tarteaucitron']]);

        self::assertInstanceOf(TarteaucitronConsentChecker::class, $this->service('test.device_view.consent_checker'));
        self::assertSame($this->service('test.device_view.consent_checker'), $this->service('test.consent_checker_interface'));
    }

    public function testKeyAndCookieDoNotApplyToACustomChecker(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'app.header_consent', 'key' => 'other', 'cookie' => 'other']],
            [],
            true,
            ['X-Consent' => 'yes'],
            ['app.header_consent' => HeaderConsent::class],
        );
    }
}
