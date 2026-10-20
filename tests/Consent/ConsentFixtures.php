<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

/**
 * The consent fixtures of a tool: tests/Fixtures/consent/<tool>/<version>/*.txt.
 *
 * A fixture holds "# key: value" comment lines, among them "# hasConsent: true|false|null", the
 * answer the reader must give with the tool's default cookie and key, and one "Cookie:" line,
 * the raw request header.
 */
final class ConsentFixtures
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}> the request cookies as PHP decodes them and the expected answer, by "<version>/<file>"
     */
    public static function of(string $tool): iterable
    {
        $files = glob(\dirname(__DIR__).'/Fixtures/consent/'.$tool.'/*/*.txt');
        if (false === $files || [] === $files) {
            throw new \LogicException(\sprintf('No consent fixture for "%s".', $tool));
        }

        foreach ($files as $file) {
            yield basename(\dirname($file)).'/'.basename($file, '.txt') => self::read($file);
        }
    }

    /**
     * Decodes a raw Cookie header as PHP does for $_COOKIE: pairs split on ";", the value
     * URL-decoded ("+" becomes a space), a bracketed name turned into an array, and the first
     * cookie of a name kept.
     *
     * @return array<string, mixed>
     */
    public static function cookies(string $header): array
    {
        $cookies = [];
        foreach (explode(';', $header) as $pair) {
            parse_str(ltrim($pair, ' '), $parsed);
            foreach ($parsed as $name => $value) {
                $cookies += [(string) $name => $value];
            }
        }

        return $cookies;
    }

    /**
     * @return array{array<string, mixed>, bool|null}
     */
    private static function read(string $file): array
    {
        $lines = file($file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (false === $lines) {
            throw new \LogicException(\sprintf('Cannot read "%s".', $file));
        }

        $header = null;
        $expected = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, 'Cookie: ')) {
                $header = substr($line, \strlen('Cookie: '));
            } elseif (1 === preg_match('/^# hasConsent: (true|false|null)$/', $line, $match)) {
                $expected[] = ['true' => true, 'false' => false, 'null' => null][$match[1]];
            }
        }

        if (null === $header || 1 !== \count($expected)) {
            throw new \LogicException(\sprintf('"%s" needs one "Cookie:" line and one "# hasConsent:" line.', $file));
        }

        return [self::cookies($header), $expected[0]];
    }
}
