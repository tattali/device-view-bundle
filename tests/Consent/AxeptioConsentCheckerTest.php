<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

use DeviceViewBundle\Consent\AxeptioConsentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Axeptio reader on its own: the fixtures of tests/Fixtures/consent/axeptio and the edge
 * cases of its parser. AxeptioTest checks the CON-AX rows through the kernel.
 */
final class AxeptioConsentCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function fixtures(): iterable
    {
        return ConsentFixtures::of('axeptio');
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[DataProvider('fixtures')]
    public function testFixtures(array $cookies, ?bool $expected): void
    {
        self::assertSame($expected, new AxeptioConsentChecker()->hasConsent(self::request($cookies)));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function values(): iterable
    {
        yield 'granted' => ['{"$$completed":true,"device_view":true}', true];
        yield 'denied' => ['{"$$completed":true,"device_view":false}', false];
        yield 'granted, completed last' => ['{"device_view":true,"analytics":false,"$$completed":true}', true];
        yield 'not completed, denied' => ['{"$$completed":false,"device_view":false}', null];
        yield 'completed null' => ['{"$$completed":null,"device_view":true}', null];
        yield 'completed 1' => ['{"$$completed":1,"device_view":true}', null];
        yield 'completed nested' => ['{"$$completed":{"x":true},"device_view":true}', null];
        yield 'vendor null' => ['{"$$completed":true,"device_view":null}', null];
        yield 'vendor 0' => ['{"$$completed":true,"device_view":0}', null];
        yield 'vendor "false"' => ['{"$$completed":true,"device_view":"false"}', null];
        yield 'vendor nested' => ['{"$$completed":true,"device_view":{"enabled":true}}', null];
        yield 'vendor in a list' => ['{"$$completed":true,"vendors":["device_view"]}', null];
        yield 'a longer vendor granted' => ['{"$$completed":true,"device_view_extra":true}', null];
        yield 'a vendor ending like it' => ['{"$$completed":true,"my_device_view":true}', null];
        yield 'the vendor in another case' => ['{"$$completed":true,"Device_View":true}', null];
        yield 'the vendor twice: the last one counts, as with JSON.parse' => ['{"$$completed":true,"device_view":true,"device_view":false}', false];
        yield 'the metadata under another prefix' => ['{"__completed":true,"device_view":true}', null];
        yield 'spaces around the JSON' => [' {"$$completed":true,"device_view":true} ', true];
        yield 'URL-encoded' => ['%7B%22%24%24completed%22%3Atrue%2C%22device_view%22%3Atrue%7D', true];
        yield 'URL-encoded, denied' => ['%7B%22%24%24completed%22%3Atrue%2C%22device_view%22%3Afalse%7D', false];
        yield 'URL-encoded twice' => ['%257B%2522%2524%2524completed%2522%253Atrue%252C%2522device_view%2522%253Atrue%257D', null];
        // read first as is, like Axeptio's readers: decoding first would turn the %22 into quotes
        yield 'percent sequences inside a JSON string are left alone' => ['{"$$completed":true,"device_view":false,"x":"%22,%22device_view%22:true,%22y%22:%22"}', false];
        yield 'a plus sign stays a plus sign when decoded' => ['%7B%22%24%24completed%22%3Atrue%2C%22device_view%22%3Atrue%2C%22d%22%3A%22a+b%22%7D', true];
        yield 'a JSON number' => ['1', null];
        yield 'JSON true' => ['true', null];
        yield 'JSON null' => ['null', null];
        yield 'a JSON list' => ['[true,true]', null];
        yield 'an empty object' => ['{}', null];
        yield 'compressed' => ['lzc1.EYFwpgTghgtgLmAJgSwHYHMD2A', null];
        yield 'an empty value' => ['', null];
        yield 'a percent sign alone' => ['%', null];
        yield 'invalid UTF-8' => ["{\"\$\$completed\":true,\"device_view\":true,\"x\":\"\xFF\"}", null];
        yield 'a trailing comma' => ['{"$$completed":true,"device_view":true,}', null];
    }

    #[DataProvider('values')]
    public function testValues(string $value, ?bool $expected): void
    {
        self::assertSame($expected, new AxeptioConsentChecker()->hasConsent(self::request(['axeptio_cookies' => $value])));
    }

    public function testNestingDeeperThanJsonDecodeAccepts(): void
    {
        $value = '{"$$completed":true,"device_view":true,"x":'.str_repeat('[', 600).str_repeat(']', 600).'}';

        self::assertNull(new AxeptioConsentChecker()->hasConsent(self::request(['axeptio_cookies' => $value])));
    }

    public function testALargeValidCookie(): void
    {
        $vendors = [];
        for ($i = 0; $i < 200; ++$i) {
            $vendors['vendor_'.$i] = 0 === $i % 2;
        }
        $value = json_encode(['$$completed' => true] + $vendors + ['device_view' => true], \JSON_THROW_ON_ERROR);

        self::assertTrue(new AxeptioConsentChecker()->hasConsent(self::request(['axeptio_cookies' => $value])));
    }

    public function testNoCookie(): void
    {
        self::assertNull(new AxeptioConsentChecker()->hasConsent(self::request([])));
    }

    public function testAnArrayCookie(): void
    {
        self::assertNull(new AxeptioConsentChecker()->hasConsent(self::request(['axeptio_cookies' => ['device_view' => 'true']])));
    }

    public function testACustomCookieName(): void
    {
        $request = self::request([
            'axeptio_site' => '{"$$completed":true,"device_view":true}',
            'axeptio_cookies' => '{"$$completed":true,"device_view":false}',
        ]);

        self::assertTrue(new AxeptioConsentChecker(cookie: 'axeptio_site')->hasConsent($request));
        self::assertFalse(new AxeptioConsentChecker()->hasConsent($request));
    }

    public function testACustomKey(): void
    {
        $request = self::request(['axeptio_cookies' => '{"$$completed":true,"views":true,"device_view":false}']);

        self::assertTrue(new AxeptioConsentChecker(key: 'views')->hasConsent($request));
        self::assertFalse(new AxeptioConsentChecker()->hasConsent($request));
    }

    public function testNullStandsForTheDefaults(): void
    {
        $request = self::request(['axeptio_cookies' => '{"$$completed":true,"device_view":true}']);

        self::assertTrue(new AxeptioConsentChecker(null, null)->hasConsent($request));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function metadataKeys(): iterable
    {
        yield 'the completed flag' => ['$$completed'];
        yield 'the cookies version of the WordPress plugin' => ['$$cookiesVersion'];
        yield 'the prefix alone' => ['$$'];
    }

    /**
     * The SDK's bookkeeping keys start with "$$": none of them is a vendor choice.
     */
    #[DataProvider('metadataKeys')]
    public function testAMetadataKeyIsNeverAChoice(string $key): void
    {
        $request = self::request(['axeptio_cookies' => '{"$$completed":true,"$$cookiesVersion":true,"$$":true}']);

        self::assertNull(new AxeptioConsentChecker(key: $key)->hasConsent($request));
    }

    public function testAKeyWithTheDollarSignInsideIsAVendor(): void
    {
        $request = self::request(['axeptio_cookies' => '{"$$completed":true,"a$$b":true,"$a":false}']);

        self::assertTrue(new AxeptioConsentChecker(key: 'a$$b')->hasConsent($request));
        self::assertFalse(new AxeptioConsentChecker(key: '$a')->hasConsent($request));
    }

    /**
     * @param array<string, mixed> $cookies
     */
    private static function request(array $cookies): Request
    {
        return Request::create('http://localhost/view', cookies: $cookies);
    }
}
