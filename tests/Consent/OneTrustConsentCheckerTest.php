<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

use DeviceViewBundle\Consent\OneTrustConsentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The OneTrust reader on its own: the fixtures of tests/Fixtures/consent/onetrust and the edge
 * cases of its parser. OneTrustTest checks the CON-OT rows through the kernel.
 */
final class OneTrustConsentCheckerTest extends TestCase
{
    private const string GROUPS = 'isGpcEnabled=0&datestamp=Tue Oct 20 2026 10:00:00 GMT+0200&version=202409.1.0&isIABGlobal=false&hosts=&consentId=0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0&interactionCount=1&landingPath=NotLandingPage&groups=';

    /**
     * The fixtures read as the files of ConsentFixtures, with the cookies decoded as PHP decodes
     * them. ConsentFixtures::cookies() cannot be used here: it splits a value on "&", which a
     * OneTrust value holds, and turns "+" into a space, which PHP keeps in a cookie value.
     *
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function fixtures(): iterable
    {
        $files = glob(\dirname(__DIR__).'/Fixtures/consent/onetrust/*/*.txt');
        self::assertIsArray($files);
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $lines = file($file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $headers = [];
            $expected = [];
            foreach ($lines as $line) {
                if (str_starts_with($line, 'Cookie: ')) {
                    $headers[] = substr($line, \strlen('Cookie: '));
                } elseif (1 === preg_match('/^# hasConsent: (true|false|null)$/', $line, $match)) {
                    $expected[] = ['true' => true, 'false' => false, 'null' => null][$match[1]];
                }
            }
            self::assertCount(1, $headers, $file);
            self::assertCount(1, $expected, $file);

            yield basename(\dirname($file)).'/'.basename($file, '.txt') => [self::cookies($headers[0]), $expected[0]];
        }
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[DataProvider('fixtures')]
    public function testFixtures(array $cookies, ?bool $expected): void
    {
        self::assertSame($expected, new OneTrustConsentChecker()->hasConsent(self::request($cookies)));
    }

    public function testTheFixtureDecoderDecodesAsPhp(): void
    {
        // what $_COOKIE holds for this header under php -S (PHP 8.4, checked 2026-10-20)
        self::assertSame(
            ['a' => 'x=1&y=a+b c', 'b' => ['k' => 'v'], 'c' => '%%'],
            self::cookies('a=x=1&y=a+b%20c; b[k]=v; c=%%; a=second'),
        );
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function values(): iterable
    {
        yield 'granted, the group first' => [self::GROUPS.'C0003:1,C0001:1', true];
        yield 'denied, the group last' => [self::GROUPS.'C0001:1,C0003:0', false];
        yield 'the only group' => [self::GROUPS.'C0003:1', true];
        yield 'groups first' => ['groups=C0003:1&isGpcEnabled=0', true];
        yield 'groups alone' => ['groups=C0003:0', false];
        yield 'groups after other parameters' => ['hosts=&genVendors=V16:0,V20:0,&groups=C0003:1', true];
        yield 'the group value still URL-encoded' => ['isGpcEnabled=0&groups=C0001%3A1%2CC0003%3A1', true];
        yield 'empty groups' => [self::GROUPS, null];
        yield 'a longer group id granted, the group denied' => [self::GROUPS.'C00031:1,C0003:0', false];
        yield 'a group id ending like it' => [self::GROUPS.'XC0003:1', null];
        yield 'a group id starting like it' => [self::GROUPS.'C0003X:1', null];
        yield 'an unknown status' => [self::GROUPS.'C0003:2', null];
        yield 'a status with a suffix' => [self::GROUPS.'C0003:10', null];
        yield 'a status with a space' => [self::GROUPS.'C0003: 1', null];
        yield 'no status' => [self::GROUPS.'C0003:', null];
        yield 'no colon' => [self::GROUPS.'C0003', null];
        yield 'two colons' => [self::GROUPS.'C0003:1:1', null];
        yield 'the group twice, same status' => [self::GROUPS.'C0003:1,C0003:1', null];
        yield 'the groups parameter twice' => ['groups=C0003:1&groups=C0003:1', null];
        yield 'a parameter ending like groups' => ['xgroups=C0003:1', null];
        yield 'a parameter starting like groups' => ['groupsx=C0003:1', null];
        yield 'upper-case parameter name' => ['Groups=C0003:1', null];
        yield 'groups without equal sign' => ['isGpcEnabled=0&groups', null];
        yield 'the value fully URL-encoded' => ['isGpcEnabled%3D0%26groups%3DC0001%253A1%252CC0003%253A1', true];
        yield 'the value fully URL-encoded, denied' => ['groups%3DC0003%253A0', false];
        yield 'an encoded groups parameter in another value is not read' => ['landingPath=%26groups%3DC0003%3A0&groups=C0003:1', true];
        yield 'only percent signs' => ['%%%', null];
        yield 'empty' => ['', null];
        yield 'only separators' => ['&&,,::==', null];
        yield 'invalid UTF-8' => [self::GROUPS."C0003:\xFF", null];
        yield 'a group id with invalid UTF-8' => [self::GROUPS."C0003\xFF:1", null];
    }

    #[DataProvider('values')]
    public function testValues(string $value, ?bool $expected): void
    {
        self::assertSame($expected, new OneTrustConsentChecker()->hasConsent(self::request(['OptanonConsent' => $value])));
    }

    public function testNoCookie(): void
    {
        self::assertNull(new OneTrustConsentChecker()->hasConsent(self::request([])));
    }

    public function testAnArrayCookie(): void
    {
        self::assertNull(new OneTrustConsentChecker()->hasConsent(self::request(['OptanonConsent' => ['groups' => 'C0003:1']])));
    }

    public function testAnOversizedCookie(): void
    {
        $value = self::GROUPS.str_repeat('C0001:1,', 100_000).'C0003:1';

        self::assertTrue(new OneTrustConsentChecker()->hasConsent(self::request(['OptanonConsent' => $value])));
        self::assertNull(new OneTrustConsentChecker()->hasConsent(self::request(['OptanonConsent' => str_repeat('&', 1_000_000)])));
    }

    public function testACustomCookieName(): void
    {
        $request = self::request(['OptanonConsentSite' => self::GROUPS.'C0003:1', 'OptanonConsent' => self::GROUPS.'C0003:0']);

        self::assertTrue(new OneTrustConsentChecker(cookie: 'OptanonConsentSite')->hasConsent($request));
        self::assertFalse(new OneTrustConsentChecker()->hasConsent($request));
    }

    public function testACustomKey(): void
    {
        $request = self::request(['OptanonConsent' => self::GROUPS.'C0001:1,C0003:0,SSPD_BG:1']);

        self::assertTrue(new OneTrustConsentChecker(key: 'SSPD_BG')->hasConsent($request));
        self::assertFalse(new OneTrustConsentChecker()->hasConsent($request));
    }

    public function testNullStandsForTheDefaults(): void
    {
        $request = self::request(['OptanonConsent' => self::GROUPS.'C0003:1']);

        self::assertTrue(new OneTrustConsentChecker(null, null)->hasConsent($request));
    }

    public function testAKeyWithRegexCharactersIsMatchedLiterally(): void
    {
        $checker = new OneTrustConsentChecker(key: 'C.003');

        self::assertTrue($checker->hasConsent(self::request(['OptanonConsent' => self::GROUPS.'C.003:1'])));
        self::assertNull($checker->hasConsent(self::request(['OptanonConsent' => self::GROUPS.'C0003:1'])));
    }

    /**
     * Decodes a raw Cookie header as PHP does for $_COOKIE: pairs split on ";", the value split
     * from the name on the first "=" and URL-decoded with "+" kept, a bracketed name turned into
     * an array, and the first cookie of a name kept.
     *
     * @return array<string, mixed>
     */
    private static function cookies(string $header): array
    {
        $cookies = [];
        foreach (explode(';', $header) as $pair) {
            [$name, $value] = explode('=', ltrim($pair, ' '), 2) + [1 => ''];
            // parse_str() gives the name the shape PHP gives it; the value is encoded again so
            // that parse_str() decodes it to the raw-URL-decoded value, "&" and "+" included
            parse_str($name.'='.rawurlencode(rawurldecode($value)), $parsed);
            foreach ($parsed as $key => $item) {
                $cookies += [(string) $key => $item];
            }
        }

        return $cookies;
    }

    /**
     * @param array<string, mixed> $cookies
     */
    private static function request(array $cookies): Request
    {
        return Request::create('http://localhost/view', cookies: $cookies);
    }
}
