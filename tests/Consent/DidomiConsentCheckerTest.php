<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

use DeviceViewBundle\Consent\DidomiConsentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Didomi reader on its own: the fixtures of tests/Fixtures/consent/didomi and the edge cases
 * of its decoding. DidomiTest checks the CON-DID rows through the kernel.
 */
final class DidomiConsentCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function fixtures(): iterable
    {
        return ConsentFixtures::of('didomi');
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[DataProvider('fixtures')]
    public function testFixtures(array $cookies, ?bool $expected): void
    {
        self::assertSame($expected, new DidomiConsentChecker()->hasConsent(self::request($cookies)));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function tokens(): iterable
    {
        yield 'granted, no disabled list' => ['{"purposes":{"enabled":["device_view"]}}', true];
        yield 'denied, no enabled list' => ['{"purposes":{"disabled":["device_view"]}}', false];
        yield 'no list at all' => ['{"purposes":{}}', null];
        yield 'purposes an empty JSON array' => ['{"purposes":[]}', null];
        yield 'no purposes' => ['{"vendors":{"enabled":["device_view"]}}', null];
        yield 'only the legitimate interest basis' => ['{"purposes_li":{"enabled":["device_view"]}}', null];
        yield 'enabled null' => ['{"purposes":{"enabled":null,"disabled":[]}}', null];
        yield 'disabled null' => ['{"purposes":{"enabled":["device_view"],"disabled":null}}', null];
        yield 'disabled an object' => ['{"purposes":{"enabled":["device_view"],"disabled":{"a":"b"}}}', null];
        yield 'enabled an object with a list key' => ['{"purposes":{"enabled":{"1":"device_view"}}}', null];
        yield 'the id as a number' => ['{"purposes":{"enabled":[1]}}', null];
        yield 'a longer id' => ['{"purposes":{"enabled":["device_view_extra"]}}', null];
        yield 'the id in upper case' => ['{"purposes":{"enabled":["DEVICE_VIEW"]}}', null];
        yield 'the id nested in a list' => ['{"purposes":{"enabled":[["device_view"]]}}', null];
        yield 'a JSON list' => ['[{"purposes":{"enabled":["device_view"]}}]', null];
        yield 'a JSON number' => ['1', null];
        yield 'JSON null' => ['null', null];
        yield 'the JSON followed by garbage' => ['{"purposes":{"enabled":["device_view"]}}x', null];
    }

    #[DataProvider('tokens')]
    public function testTokens(string $json, ?bool $expected): void
    {
        self::assertSame($expected, new DidomiConsentChecker()->hasConsent(self::request(['didomi_token' => base64_encode($json)])));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function values(): iterable
    {
        // the base64 of {"user_id":"~~~???","purposes":{"enabled":["device_view"],"disabled":[]},"version":2}
        $token = 'eyJ1c2VyX2lkIjoifn5+Pz8/IiwicHVycG9zZXMiOnsiZW5hYmxlZCI6WyJkZXZpY2VfdmlldyJdLCJkaXNhYmxlZCI6W119LCJ2ZXJzaW9uIjoyfQ==';

        yield 'standard base64' => [$token, true];
        yield 'without padding' => [rtrim($token, '='), true];
        yield 'URL-safe' => [strtr($token, '+/', '-_'), true];
        yield 'a space for each "+"' => [strtr($token, '+', ' '), true];
        yield 'a padding sign too many' => [$token.'=', null];
        yield 'a padding sign inside' => ['=='.$token, null];
        yield 'a dot' => [str_replace('+', '.', $token), null];
        yield 'a percent-encoded value left as is' => [rawurlencode($token), null];
        yield 'a NUL byte' => ["\0".$token, null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('values')]
    public function testValues(string $value, ?bool $expected): void
    {
        self::assertSame($expected, new DidomiConsentChecker()->hasConsent(self::request(['didomi_token' => $value])));
    }

    public function testATokenOfTheLongestCookieABrowserKeeps(): void
    {
        $token = self::paddedToken(4096);

        self::assertSame(4096, \strlen($token));
        self::assertTrue(new DidomiConsentChecker()->hasConsent(self::request(['didomi_token' => $token])));
        // base64 skips whitespace: one byte more is refused for its length only
        self::assertNull(new DidomiConsentChecker()->hasConsent(self::request(['didomi_token' => $token."\n"])));
    }

    public function testNoCookie(): void
    {
        self::assertNull(new DidomiConsentChecker()->hasConsent(self::request([])));
    }

    public function testAnArrayCookie(): void
    {
        self::assertNull(new DidomiConsentChecker()->hasConsent(self::request(['didomi_token' => ['purposes' => 'x']])));
    }

    public function testACustomCookieName(): void
    {
        $request = self::request([
            'didomi_site' => base64_encode('{"purposes":{"enabled":["device_view"]}}'),
            'didomi_token' => base64_encode('{"purposes":{"disabled":["device_view"]}}'),
        ]);

        self::assertTrue(new DidomiConsentChecker(cookie: 'didomi_site')->hasConsent($request));
        self::assertFalse(new DidomiConsentChecker()->hasConsent($request));
    }

    public function testACustomPurpose(): void
    {
        $request = self::request(['didomi_token' => base64_encode('{"purposes":{"enabled":["dv-pref"],"disabled":["device_view"]}}')]);

        self::assertTrue(new DidomiConsentChecker(key: 'dv-pref')->hasConsent($request));
        self::assertFalse(new DidomiConsentChecker()->hasConsent($request));
    }

    public function testAVendorKey(): void
    {
        $request = self::request(['didomi_token' => base64_encode('{"purposes":{"enabled":["c:analytics"],"disabled":["c:ads"]},"vendors":{"enabled":["c:ads"],"disabled":["c:analytics"]}}')]);

        self::assertTrue(new DidomiConsentChecker(key: 'vendors:c:ads')->hasConsent($request));
        self::assertFalse(new DidomiConsentChecker(key: 'vendors:c:analytics')->hasConsent($request));
        self::assertNull(new DidomiConsentChecker(key: 'vendors:c:other')->hasConsent($request));
    }

    public function testOnlyALeadingVendorsPrefixNamesAVendor(): void
    {
        $request = self::request(['didomi_token' => base64_encode('{"purposes":{"enabled":["my-vendors:x"]},"vendors":{"disabled":["x"]}}')]);

        self::assertTrue(new DidomiConsentChecker(key: 'my-vendors:x')->hasConsent($request));
    }

    public function testAPurposeNamedVendorsIsAPurpose(): void
    {
        $request = self::request(['didomi_token' => base64_encode('{"purposes":{"enabled":["vendors"]},"vendors":{"disabled":["vendors"]}}')]);

        self::assertTrue(new DidomiConsentChecker(key: 'vendors')->hasConsent($request));
    }

    public function testNullStandsForTheDefaults(): void
    {
        $request = self::request(['didomi_token' => base64_encode('{"purposes":{"enabled":["device_view"]}}')]);

        self::assertTrue(new DidomiConsentChecker(null, null)->hasConsent($request));
    }

    /**
     * The base64 of a granted token, $length characters long: a multiple of 4.
     */
    private static function paddedToken(int $length): string
    {
        $json = '{"purposes":{"enabled":["device_view"]},"padding":""}';
        $json = str_replace('""', '"'.str_repeat('a', intdiv($length, 4) * 3 - \strlen($json)).'"', $json);

        return base64_encode($json);
    }

    /**
     * @param array<string, mixed> $cookies
     */
    private static function request(array $cookies): Request
    {
        return Request::create('http://localhost/view', cookies: $cookies);
    }
}
