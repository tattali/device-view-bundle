<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the consent Complianz stores for a category (consent.provider: complianz).
 *
 * Complianz writes one cookie per category, named after its prefix and the category, e.g.
 * "cmplz_preferences=allow". The cookie of the category named by the key decides: allow grants,
 * deny denies. No such cookie, or any other value (another case, spaces, an array), gives null.
 * Complianz itself treats a missing cookie as consent in opt-out regions; this reader does not.
 */
final readonly class ComplianzConsentChecker implements ConsentCheckerInterface
{
    public const string DEFAULT_COOKIE = 'cmplz_';

    public const string DEFAULT_KEY = 'preferences';

    private string $cookie;

    /**
     * @param string|null $cookie the cookie name prefix, or null for "cmplz_" ("cmplz_rt_" on the main site of a multisite network that does not set its cookies on the root domain)
     * @param string|null $key    the category, or null for "preferences"
     */
    public function __construct(?string $cookie = null, ?string $key = null)
    {
        $this->cookie = ($cookie ?? self::DEFAULT_COOKIE).($key ?? self::DEFAULT_KEY);
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        // all(), not get(): get() throws on an array value such as cmplz_preferences[a]
        return match ($request->cookies->all()[$this->cookie] ?? null) {
            'allow' => true,
            'deny' => false,
            default => null,
        };
    }
}
