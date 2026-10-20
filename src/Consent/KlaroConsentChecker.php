<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the consent Klaro stores for a service (consent.provider: klaro).
 *
 * Klaro writes the cookie as encodeURIComponent(JSON.stringify(consents)), a JSON object mapping
 * each service name to true or false, e.g. {"device_view":true,"analytics":false}; PHP decodes it
 * once. A value still URL-encoded once or twice is decoded again. The service named by the key
 * decides: true grants, false denies. An absent service, any other value, a value that is not a
 * JSON object, or a value longer than 4096 bytes (more than a browser keeps for a cookie) gives
 * null.
 */
final readonly class KlaroConsentChecker implements ConsentCheckerInterface
{
    public const string DEFAULT_COOKIE = 'klaro';

    public const string DEFAULT_KEY = 'device_view';

    private const int MAX_LENGTH = 4096;

    private string $cookie;

    private string $key;

    /**
     * @param string|null $cookie the cookie name (Klaro's storageName option), or null for "klaro"
     * @param string|null $key    the service name, or null for "device_view"
     */
    public function __construct(?string $cookie = null, ?string $key = null)
    {
        $this->cookie = $cookie ?? self::DEFAULT_COOKIE;
        $this->key = $key ?? self::DEFAULT_KEY;
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        // all(), not get(): get() throws on an array value such as klaro[device_view]
        $value = $request->cookies->all()[$this->cookie] ?? null;
        if (!\is_string($value) || \strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        // a JSON object starts with "{": a value that does not is URL-decoded, at most twice
        for ($decodings = 0; $decodings < 2 && !str_starts_with($value, '{'); ++$decodings) {
            $value = rawurldecode($value);
        }

        $consents = json_decode($value);
        $consent = \is_object($consents) ? get_object_vars($consents)[$this->key] ?? null : null;

        return \is_bool($consent) ? $consent : null;
    }
}
