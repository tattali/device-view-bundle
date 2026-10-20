<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

use DeviceViewBundle\Consent\KlaroConsentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Klaro reader on its own: the fixtures of tests/Fixtures/consent/klaro and the edge cases
 * of its parser. KlaroTest checks the CON-KLA rows through the kernel.
 */
final class KlaroConsentCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function fixtures(): iterable
    {
        return ConsentFixtures::of('klaro');
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[DataProvider('fixtures')]
    public function testFixtures(array $cookies, ?bool $expected): void
    {
        self::assertSame($expected, new KlaroConsentChecker()->hasConsent(self::request($cookies)));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function values(): iterable
    {
        // values as PHP gives them, after it URL-decoded the cookie once
        yield 'URL-encoded three times' => ['%25257B%252522device_view%252522%25253Atrue%25257D', null];
        yield 'granted' => ['{"device_view":true}', true];
        yield 'denied' => ['{"device_view":false}', false];
        yield 'granted among others' => ['{"analytics":false,"device_view":true,"youtube":false}', true];
        yield 'URL-encoded once' => ['%7B%22device_view%22%3Atrue%7D', true];
        yield 'URL-encoded once, denied' => ['%7B%22device_view%22%3Afalse%7D', false];
        yield 'URL-encoded once, lower-case hex' => ['%7b%22device_view%22%3atrue%7d', true];
        yield 'URL-encoded twice' => ['%257B%2522device_view%2522%253Atrue%257D', true];
        yield 'JSON is not URL-decoded' => ['{"a%22b":false,"device_view":true}', true];
        yield 'a service named like an encoded quote' => ['{"device_view%22":true}', null];
        yield 'the key twice: the last wins, as in JSON.parse' => ['{"device_view":true,"device_view":false}', false];
        yield 'a string' => ['{"device_view":"true"}', null];
        yield 'a number' => ['{"device_view":1}', null];
        yield 'null' => ['{"device_view":null}', null];
        yield 'an object' => ['{"device_view":{}}', null];
        yield 'nested' => ['{"x":{"device_view":true}}', null];
        yield 'service absent' => ['{"analytics":true}', null];
        yield 'empty object' => ['{}', null];
        yield 'a list' => ['[true]', null];
        yield 'a JSON string' => ['"device_view"', null];
        yield 'a JSON boolean' => ['true', null];
        yield 'not JSON' => ['not-json', null];
        yield 'truncated' => ['{"device_view":true', null];
        yield 'empty' => ['', null];
        yield 'a stray percent sign' => ['%%%', null];
        yield 'invalid UTF-8' => ["{\"device_view\":true,\"\xFF\":true}", null];
        yield 'a key ending like it' => ['{"my_device_view":true}', null];
        yield 'a longer key' => ['{"device_view_extra":true}', null];
        yield 'another case' => ['{"Device_View":true}', null];
    }

    #[DataProvider('values')]
    public function testValues(string $value, ?bool $expected): void
    {
        self::assertSame($expected, new KlaroConsentChecker()->hasConsent(self::request(['klaro' => $value])));
    }

    public function testAValueOfTheMaximumLength(): void
    {
        $value = self::padded(4096);

        self::assertSame(4096, \strlen($value));
        self::assertTrue(new KlaroConsentChecker()->hasConsent(self::request(['klaro' => $value])));
    }

    public function testALongerValue(): void
    {
        $value = self::padded(4097);

        self::assertSame(4097, \strlen($value));
        self::assertNull(new KlaroConsentChecker()->hasConsent(self::request(['klaro' => $value])));
    }

    public function testNoCookie(): void
    {
        self::assertNull(new KlaroConsentChecker()->hasConsent(self::request([])));
    }

    public function testAnArrayCookie(): void
    {
        self::assertNull(new KlaroConsentChecker()->hasConsent(self::request(['klaro' => ['device_view' => 'true']])));
    }

    public function testACustomCookieName(): void
    {
        $request = self::request(['klaro-consent' => '{"device_view":true}', 'klaro' => '{"device_view":false}']);

        self::assertTrue(new KlaroConsentChecker(cookie: 'klaro-consent')->hasConsent($request));
        self::assertFalse(new KlaroConsentChecker()->hasConsent($request));
    }

    public function testACustomKey(): void
    {
        $request = self::request(['klaro' => '{"views":true,"device_view":false}']);

        self::assertTrue(new KlaroConsentChecker(key: 'views')->hasConsent($request));
        self::assertFalse(new KlaroConsentChecker()->hasConsent($request));
    }

    public function testNullStandsForTheDefaults(): void
    {
        $request = self::request(['klaro' => '{"device_view":true}']);

        self::assertTrue(new KlaroConsentChecker(null, null)->hasConsent($request));
    }

    public function testANumericKey(): void
    {
        $checker = new KlaroConsentChecker(key: '0');

        self::assertTrue($checker->hasConsent(self::request(['klaro' => '{"0":true}'])));
        self::assertNull($checker->hasConsent(self::request(['klaro' => '[true]'])));
    }

    /**
     * The custom storage name fixture, read with the configuration that names its cookie and key.
     */
    public function testTheCustomStorageNameFixture(): void
    {
        $fixtures = iterator_to_array(ConsentFixtures::of('klaro'));
        [$cookies] = $fixtures['0.7.21/custom-storage-name'];

        self::assertTrue(new KlaroConsentChecker('klaro-consent', 'views')->hasConsent(self::request($cookies)));
    }

    /**
     * A granted value of $length bytes.
     */
    private static function padded(int $length): string
    {
        $value = '{"device_view":true,"pad":"';

        return $value.str_repeat('x', $length - \strlen($value) - 2).'"}';
    }

    /**
     * @param array<string, mixed> $cookies
     */
    private static function request(array $cookies): Request
    {
        return Request::create('http://localhost/view', cookies: $cookies);
    }
}
