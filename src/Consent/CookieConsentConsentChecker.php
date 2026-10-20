<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the consent CookieConsent v3 (orestbida) stores for a category (consent.provider: cookieconsent).
 *
 * CookieConsent writes encodeURIComponent(JSON.stringify(...)), so PHP gives the JSON, e.g.
 * {"categories":["necessary","preferences"],"services":{"preferences":["device_view"]},...}.
 * A value still URL-encoded is decoded once more, as CookieConsent's decodeURIComponent() would.
 *
 * The key is a category, granted when "categories" lists it and denied otherwise. A key
 * "<category>.<service>" (split on the first dot) also needs "services"."<category>" to list the
 * service: missing or null services deny, as CookieConsent's acceptedService() reads them. Any
 * other value of the cookie, of "categories", of "services" or of the category's services gives
 * null.
 *
 * The reader does not repeat CookieConsent's checks of "revision" and "consentId", since the
 * server does not know the revision the site configured. With cookie.useLocalStorage, CookieConsent
 * writes no cookie, so the answer is null.
 */
final readonly class CookieConsentConsentChecker implements ConsentCheckerInterface
{
    public const string DEFAULT_COOKIE = 'cc_cookie';

    public const string DEFAULT_KEY = 'preferences';

    private string $cookie;

    private string $category;

    private ?string $service;

    /**
     * @param string|null $cookie the cookie name (CookieConsent's cookie.name option), or null for "cc_cookie"
     * @param string|null $key    the category, or "<category>.<service>", or null for "preferences"
     */
    public function __construct(?string $cookie = null, ?string $key = null)
    {
        $this->cookie = $cookie ?? self::DEFAULT_COOKIE;
        $key = explode('.', $key ?? self::DEFAULT_KEY, 2);
        $this->category = $key[0];
        $this->service = $key[1] ?? null;
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        // all(), not get(): get() throws on an array value such as cc_cookie[categories]
        $value = $request->cookies->all()[$this->cookie] ?? null;
        if (!\is_string($value)) {
            return null;
        }

        $consent = self::decode($value) ?? self::decode(rawurldecode($value));
        $categories = $consent->categories ?? null;
        if (!self::isListOfStrings($categories)) {
            return null;
        }

        if (!\in_array($this->category, $categories, true)) {
            return false;
        }

        if (null === $this->service) {
            return true;
        }

        $services = $consent->services ?? new \stdClass();
        if (!$services instanceof \stdClass) {
            return null;
        }

        $accepted = get_object_vars($services)[$this->category] ?? [];

        return self::isListOfStrings($accepted) ? \in_array($this->service, $accepted, true) : null;
    }

    private static function decode(string $value): ?\stdClass
    {
        $decoded = json_decode($value);

        return $decoded instanceof \stdClass ? $decoded : null;
    }

    /**
     * A JSON array decodes to a list, so any array here is a list.
     *
     * @phpstan-assert-if-true array<string> $value
     */
    private static function isListOfStrings(mixed $value): bool
    {
        return \is_array($value) && array_all($value, static fn (mixed $item): bool => \is_string($item));
    }
}
