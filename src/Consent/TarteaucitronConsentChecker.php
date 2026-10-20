<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the consent tarteaucitron stores for a service (consent.provider: tarteaucitron).
 *
 * The cookie value is "!<service>=<status>" repeated, the status being true, false or wait, e.g.
 * "!gtag=true!device_view=false". The service named by the key decides: true grants, false
 * denies, wait or absent is no choice yet. Any other value of the cookie or of the service, a
 * value that does not start with "!", or the service listed twice gives null.
 */
final readonly class TarteaucitronConsentChecker implements ConsentCheckerInterface
{
    public const string DEFAULT_COOKIE = 'tarteaucitron';

    public const string DEFAULT_KEY = 'device_view';

    private string $cookie;

    private string $key;

    /**
     * @param string|null $cookie the cookie name (tarteaucitron's cookieName parameter), or null for "tarteaucitron"
     * @param string|null $key    the service key, or null for "device_view"
     */
    public function __construct(?string $cookie = null, ?string $key = null)
    {
        $this->cookie = $cookie ?? self::DEFAULT_COOKIE;
        $this->key = $key ?? self::DEFAULT_KEY;
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        // all(), not get(): get() throws on an array value such as tarteaucitron[a]
        $value = $request->cookies->all()[$this->cookie] ?? null;
        if (!\is_string($value) || !str_starts_with($value, '!')) {
            return null;
        }

        $statuses = [];
        foreach (explode('!', $value) as $service) {
            if (str_starts_with($service, $this->key.'=')) {
                $statuses[] = substr($service, \strlen($this->key) + 1);
            }
        }

        return 1 === \count($statuses) ? match ($statuses[0]) {
            'true' => true,
            'false' => false,
            default => null,
        } : null;
    }
}
