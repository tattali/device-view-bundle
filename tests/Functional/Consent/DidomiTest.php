<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional\Consent;

use DeviceViewBundle\Tests\Consent\ConsentFixtures;
use DeviceViewBundle\Tests\Functional\AssertsCookies;
use DeviceViewBundle\Tests\Functional\ChecksConsent;
use DeviceViewBundle\Tests\Functional\HandlesRequests;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The Didomi reader through the kernel: the CON-DID rows of docs/scenarios.md.
 */
final class DidomiTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array DIDOMI = ['consent' => ['provider' => 'didomi']];

    /**
     * G of docs/scenarios.md.
     */
    private const string G = '{"user_id":"0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0","created":"2026-10-20T10:00:00.000Z","updated":"2026-10-20T10:00:00.000Z","purposes":{"enabled":["device_view"],"disabled":[]},"vendors":{"enabled":["c:analytics"],"disabled":["c:ads"]},"version":2}';

    /**
     * The token with "+" and "=" of CON-DID-05 and HTTP-03.
     */
    private const string PLUS_TOKEN = 'eyJ1c2VyX2lkIjoiMGYxZTJkM2MtNGI1YS02OTc4LTg3OTYtYTViNGMzZDJlMWYwMCIsInB1cnBvc2VzIjp7ImVuYWJsZWQiOlsiZGV2aWNlX3ZpZXciXSwiZGlzYWJsZWQiOltdfSwidmVuZG9ycyI6eyJlbmFibGVkIjpbImM6ZHZ+dGVzdCJdLCJkaXNhYmxlZCI6W119LCJ2ZXJzaW9uIjoyfQ==';

    #[Group('CON-DID-01')]
    public function testPurposeEnabled(): void
    {
        $this->assertConsentRow(self::DIDOMI, ['didomi_token' => base64_encode(self::G)], true);
    }

    #[Group('CON-DID-02')]
    public function testPurposeDisabled(): void
    {
        $this->assertConsentRow(self::DIDOMI, ['didomi_token' => self::b64G('{"enabled":[],"disabled":["device_view"]}')], false);
    }

    #[Group('CON-DID-03')]
    public function testPurposeInNeitherList(): void
    {
        $this->assertConsentRow(self::DIDOMI, ['didomi_token' => self::b64G('{"enabled":["other"],"disabled":[]}')], null);
    }

    #[Group('CON-DID-04')]
    public function testUrlSafeBase64WithoutPadding(): void
    {
        $this->assertConsentRow(self::DIDOMI, ['didomi_token' => rtrim(strtr(base64_encode(self::G), '+/', '-_'), '=')], true);
    }

    #[Group('CON-DID-05')]
    public function testPlusReadBackFromASpace(): void
    {
        $cookies = ConsentFixtures::cookies('didomi_token='.self::PLUS_TOKEN);
        self::assertSame(['didomi_token' => strtr(self::PLUS_TOKEN, '+', ' ')], $cookies);

        $this->assertConsentRow(self::DIDOMI, $cookies, true);
    }

    #[Group('CON-DID-06')]
    public function testVendorKeyEnabled(): void
    {
        $this->assertConsentRow(['consent' => ['provider' => 'didomi', 'key' => 'vendors:c:analytics']], ['didomi_token' => base64_encode(self::G)], true);
    }

    #[Group('CON-DID-07')]
    public function testVendorKeyDisabled(): void
    {
        $this->assertConsentRow(['consent' => ['provider' => 'didomi', 'key' => 'vendors:c:ads']], ['didomi_token' => base64_encode(self::G)], false);
    }

    #[Group('CON-DID-08')]
    public function testNotBase64(): void
    {
        $this->assertConsentRow(self::DIDOMI, ConsentFixtures::cookies('didomi_token=***'), null);
    }

    #[Group('CON-DID-09')]
    public function testBase64OfSomethingThatIsNotJson(): void
    {
        $this->assertConsentRow(self::DIDOMI, ConsentFixtures::cookies('didomi_token=bm90IGpzb24='), null);
    }

    #[Group('CON-DID-10')]
    public function testNoCookie(): void
    {
        $this->assertConsentRow(self::DIDOMI, [], null);
    }

    #[Group('CON-DID-11')]
    public function testArrayCookie(): void
    {
        $this->assertConsentRow(self::DIDOMI, ['didomi_token' => ['purposes' => 'x']], null);
    }

    #[Group('CON-DID-12')]
    public function testCustomCookieName(): void
    {
        $this->assertConsentRow(['consent' => ['provider' => 'didomi', 'cookie' => 'didomi_site']], ['didomi_site' => base64_encode(self::G)], true);
    }

    /**
     * The base64 of G with its purposes replaced by $purposes.
     */
    private static function b64G(string $purposes): string
    {
        return base64_encode(str_replace('{"enabled":["device_view"],"disabled":[]}', $purposes, self::G));
    }
}
