<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the consent OneTrust stores for a cookie category (consent.provider: onetrust).
 *
 * The cookie value is a query string, each value URL-encoded, e.g.
 * "isGpcEnabled=0&interactionCount=1&groups=C0001%3A1%2CC0003%3A0", which PHP gives decoded:
 * "isGpcEnabled=0&interactionCount=1&groups=C0001:1,C0003:0". Its groups parameter lists
 * "<group id>:<status>": the group named by the key decides, 1 grants and 0 denies. A value with
 * no "=" is read as still URL-encoded and decoded once more. No groups parameter, the group
 * absent, any other status, the parameter or the group listed twice, or any other value gives
 * null.
 */
final readonly class OneTrustConsentChecker implements ConsentCheckerInterface
{
    public const string DEFAULT_COOKIE = 'OptanonConsent';

    public const string DEFAULT_KEY = 'C0003';

    private const string GROUPS = 'groups=';

    private string $cookie;

    private string $key;

    /**
     * @param string|null $cookie the cookie name, or null for "OptanonConsent"
     * @param string|null $key    the group id (OneTrust's cookie category id), or null for "C0003"
     */
    public function __construct(?string $cookie = null, ?string $key = null)
    {
        $this->cookie = $cookie ?? self::DEFAULT_COOKIE;
        $this->key = $key ?? self::DEFAULT_KEY;
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        // all(), not get(): get() throws on an array value such as OptanonConsent[groups]
        $value = $request->cookies->all()[$this->cookie] ?? null;
        if (!\is_string($value)) {
            return null;
        }

        if (!str_contains($value, '=')) {
            $value = rawurldecode($value);
        }

        $groups = [];
        foreach (explode('&', $value) as $parameter) {
            if (str_starts_with($parameter, self::GROUPS)) {
                $groups[] = substr($parameter, \strlen(self::GROUPS));
            }
        }

        if (1 !== \count($groups)) {
            return null;
        }

        $statuses = [];
        // the groups value stays encoded when the whole value was decoded above
        foreach (explode(',', rawurldecode($groups[0])) as $group) {
            if (str_starts_with($group, $this->key.':')) {
                $statuses[] = substr($group, \strlen($this->key) + 1);
            }
        }

        return 1 === \count($statuses) ? match ($statuses[0]) {
            '1' => true,
            '0' => false,
            default => null,
        } : null;
    }
}
