<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the consent Axeptio stores for a vendor (consent.provider: axeptio).
 *
 * The cookie value is a JSON object, which the Axeptio SDK writes URL-encoded, so PHP decodes it
 * to JSON, e.g. {"$$token":"…","$$completed":true,"device_view":true,"analytics":false}. Keys
 * starting with "$$" are the SDK's bookkeeping: $$completed is true once the visitor finished
 * the banner. Every other key is a vendor. When the banner is completed, the vendor named by the
 * key decides: true grants, false denies, absent is no choice yet.
 *
 * A value that is still URL-encoded is decoded once more, as Axeptio's own readers do. Anything
 * else gives null: $$completed other than true, a vendor value other than a boolean, a key
 * starting with "$$", a value that is not a JSON object, or a cookie the SDK compressed (its
 * compressUserCookie setting, values starting with "lzc1.").
 */
final readonly class AxeptioConsentChecker implements ConsentCheckerInterface
{
    public const string DEFAULT_COOKIE = 'axeptio_cookies';

    public const string DEFAULT_KEY = 'device_view';

    private string $cookie;

    private string $key;

    /**
     * @param string|null $cookie the cookie name (Axeptio's jsonCookieName setting), or null for "axeptio_cookies"
     * @param string|null $key    the vendor key, or null for "device_view"
     */
    public function __construct(?string $cookie = null, ?string $key = null)
    {
        $this->cookie = $cookie ?? self::DEFAULT_COOKIE;
        $this->key = $key ?? self::DEFAULT_KEY;
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        // all(), not get(): get() throws on an array value such as axeptio_cookies[a]
        $value = $request->cookies->all()[$this->cookie] ?? null;
        if (!\is_string($value) || str_starts_with($this->key, '$$')) {
            return null;
        }

        // json_decode() never throws without JSON_THROW_ON_ERROR: it gives null on bad input
        $choices = json_decode($value, true) ?? json_decode(rawurldecode($value), true);
        if (!\is_array($choices) || true !== ($choices['$$completed'] ?? null)) {
            return null;
        }

        $choice = $choices[$this->key] ?? null;

        return \is_bool($choice) ? $choice : null;
    }
}
