<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the consent Didomi stores for a custom purpose or vendor (consent.provider: didomi).
 *
 * The didomi_token cookie is the base64 of a JSON object whose "purposes" and "vendors" each
 * hold an "enabled" and a "disabled" list of ids, the choices on the consent legal basis, e.g.
 * {"purposes":{"enabled":["device_view"],"disabled":[]},"vendors":{"enabled":["c:analytics"]}}.
 * The key is a purpose id, or "vendors:<id>" for a vendor. The id listed as enabled grants,
 * listed as disabled denies, and listed in neither is no choice yet; a missing list counts as
 * empty. Standard and URL-safe base64 are read, with or without padding, and a space stands for
 * the "+" PHP reads it from. Any other value gives null: a value longer than a browser keeps,
 * not base64, not a JSON object, a list that is not a JSON array, or the id in both lists. The
 * legitimate interest lists (purposes_li, vendors_li) are not consent, so they are not read.
 */
final readonly class DidomiConsentChecker implements ConsentCheckerInterface
{
    public const string DEFAULT_COOKIE = 'didomi_token';

    public const string DEFAULT_KEY = 'device_view';

    /**
     * The prefix of a key naming a vendor.
     */
    private const string VENDOR_PREFIX = 'vendors:';

    /**
     * The longest value read: a browser keeps at most 4096 bytes of a cookie (RFC 6265, section 6.1).
     */
    private const int MAX_LENGTH = 4096;

    private string $cookie;

    /**
     * The member of the token that lists the choices: "purposes" or "vendors".
     */
    private string $choices;

    private string $id;

    /**
     * @param string|null $cookie the cookie name, or null for "didomi_token"
     * @param string|null $key    the custom purpose id, or "vendors:<id>" for a vendor, or null for "device_view"
     */
    public function __construct(?string $cookie = null, ?string $key = null)
    {
        $this->cookie = $cookie ?? self::DEFAULT_COOKIE;
        $key ??= self::DEFAULT_KEY;
        $vendor = str_starts_with($key, self::VENDOR_PREFIX);
        $this->choices = $vendor ? 'vendors' : 'purposes';
        $this->id = $vendor ? substr($key, \strlen(self::VENDOR_PREFIX)) : $key;
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        // all(), not get(): get() throws on an array value such as didomi_token[a]
        $value = $request->cookies->all()[$this->cookie] ?? null;
        if (!\is_string($value) || \strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        $json = base64_decode(strtr($value, ' -_', '++/'), true);
        if (false === $json) {
            return null;
        }

        try {
            $token = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $choices = \is_array($token) ? $token[$this->choices] ?? null : null;
        if (!\is_array($choices)) {
            return null;
        }

        $enabled = $this->isListedIn($choices, 'enabled');
        $disabled = $this->isListedIn($choices, 'disabled');

        return null === $enabled || null === $disabled || $enabled === $disabled ? null : $enabled;
    }

    /**
     * Whether the id is in the list $name of $choices, or null when that list is not a JSON array.
     *
     * @param array<mixed> $choices
     */
    private function isListedIn(array $choices, string $name): ?bool
    {
        // a list set to null is not a JSON array: only a missing list counts as empty
        $ids = \array_key_exists($name, $choices) ? $choices[$name] : [];

        return \is_array($ids) && array_is_list($ids) ? \in_array($this->id, $ids, true) : null;
    }
}
