<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional\Consent;

use DeviceViewBundle\Consent\ComplianzConsentChecker;
use DeviceViewBundle\Tests\Consent\ConsentFixtures;
use DeviceViewBundle\Tests\Functional\AssertsCookies;
use DeviceViewBundle\Tests\Functional\ChecksConsent;
use DeviceViewBundle\Tests\Functional\HandlesRequests;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The Complianz reader through the kernel: the CON-CMP rows of docs/scenarios.md.
 */
final class ComplianzTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array CMP = ['consent' => ['provider' => 'complianz']];

    #[Group('CON-CMP-01')]
    public function testAllowed(): void
    {
        $this->assertConsentRow(self::CMP, ConsentFixtures::cookies('cmplz_preferences=allow'), true);
    }

    #[Group('CON-CMP-02')]
    public function testDenied(): void
    {
        $this->assertConsentRow(self::CMP, ConsentFixtures::cookies('cmplz_preferences=deny'), false);
    }

    #[Group('CON-CMP-03')]
    public function testNoCookie(): void
    {
        $this->assertConsentRow(self::CMP, [], null);
    }

    #[Group('CON-CMP-04')]
    public function testOtherValue(): void
    {
        $this->assertConsentRow(self::CMP, ConsentFixtures::cookies('cmplz_preferences=maybe'), null);
    }

    #[Group('CON-CMP-05')]
    public function testExactValueOnly(): void
    {
        $this->assertConsentRow(self::CMP, ConsentFixtures::cookies('cmplz_preferences=ALLOW'), null);
    }

    #[Group('CON-CMP-06')]
    public function testCustomKey(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'complianz', 'key' => 'statistics']],
            ConsentFixtures::cookies('cmplz_statistics=allow; cmplz_preferences=deny'),
            true,
        );
    }

    #[Group('CON-CMP-07')]
    public function testCustomPrefix(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'complianz', 'cookie' => 'site_']],
            ConsentFixtures::cookies('site_preferences=allow'),
            true,
        );
    }

    #[Group('CON-CMP-08')]
    public function testArrayCookie(): void
    {
        $this->assertConsentRow(self::CMP, ['cmplz_preferences' => ['a' => 'allow']], null);
    }

    public function testTheProviderIsTheBuiltInReader(): void
    {
        $this->boot(self::CMP);

        self::assertInstanceOf(ComplianzConsentChecker::class, $this->service('test.device_view.consent_checker'));
    }
}
