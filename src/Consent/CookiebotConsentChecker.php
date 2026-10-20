<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the consent Cookiebot stores for a cookie category (consent.provider: cookiebot).
 *
 * The CookieConsent cookie holds "-1" when the visitor is in a region that needs no consent,
 * which grants every category, "0" when no cookie was accepted, which denies them, or a
 * JavaScript-style object such as "{stamp:'…',necessary:true,preferences:false,…}". The object is
 * read as Cookiebot's own PHP reader does (the server-side example of its developer
 * documentation and Cookie_Consent::scan_cookie() of its WordPress plugin 4.7.5): single quotes
 * become double quotes, bare names and bare values are quoted, then the result is decoded as
 * JSON. The category named by the key then decides: true grants, false denies. A missing
 * category, any other value, a value that is not such an object, or a value longer than a
 * browser stores in a cookie gives null.
 */
final readonly class CookiebotConsentChecker implements ConsentCheckerInterface
{
    public const string DEFAULT_COOKIE = 'CookieConsent';

    public const string DEFAULT_KEY = 'preferences';

    /**
     * The longest value read: browsers store at most 4096 bytes for a cookie (RFC 6265, section 6.1).
     */
    public const int MAX_LENGTH = 4096;

    private string $cookie;

    private string $key;

    /**
     * @param string|null $cookie the cookie name, or null for "CookieConsent"
     * @param string|null $key    the cookie category, or null for "preferences"
     */
    public function __construct(?string $cookie = null, ?string $key = null)
    {
        $this->cookie = $cookie ?? self::DEFAULT_COOKIE;
        $this->key = $key ?? self::DEFAULT_KEY;
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        // all(), not get(): get() throws on an array value such as CookieConsent[a]
        $value = $request->cookies->all()[$this->cookie] ?? null;
        if (!\is_string($value) || \strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        return match ($value) {
            '-1' => true,
            '0' => false,
            default => $this->readObject($value),
        };
    }

    private function readObject(string $value): ?bool
    {
        // Cookiebot's normalisation, without the stripslashes() that undid PHP's magic quotes
        $json = preg_replace(
            ['/([{\[,])\s*(\w+?):/', '/\s*:\s*(\w+?)([}\[,])/'],
            ['$1"$2":', ':"$1"$2'],
            str_replace("'", '"', $value),
        );
        $consent = json_decode($json ?? '');
        if (!$consent instanceof \stdClass) {
            return null;
        }

        return match (get_object_vars($consent)[$this->key] ?? null) {
            true, 'true' => true,
            false, 'false' => false,
            default => null,
        };
    }
}
