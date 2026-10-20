<?php

declare(strict_types=1);

namespace DeviceViewBundle\Remember;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the view in a cookie (device_view.remember.cookie).
 */
final readonly class CookieStorage implements ViewStorageInterface
{
    /**
     * @param string                     $name     the cookie name
     * @param bool|null                  $secure   true or false, or null for auto: secure on HTTPS requests
     * @param 'lax'|'strict'|'none'|null $sameSite the SameSite attribute; none always adds Secure, which browsers require for it
     * @param bool                       $raw      whether the value is written without URL encoding
     */
    public function __construct(
        private string $name = 'device_view',
        private string $path = '/',
        private ?string $domain = null,
        private ?bool $secure = null,
        private bool $httpOnly = true,
        private ?string $sameSite = Cookie::SAMESITE_LAX,
        private bool $raw = false,
    ) {
    }

    #[\Override]
    public function read(Request $request): mixed
    {
        // all(), not get(): get() throws on an array value such as device_view[a]
        return $request->cookies->all()[$this->name] ?? null;
    }

    #[\Override]
    public function write(Request $request, Response $response, string $name, int $lifetime): void
    {
        // a lifetime of 0 gives an expiry of 0: a browser-session cookie
        $expire = $lifetime > 0 ? time() + $lifetime : $lifetime;
        $response->headers->setCookie(new Cookie($this->name, $name, $expire, $this->path, $this->domain, $this->isSecure($request), $this->httpOnly, $this->raw, $this->sameSite));
    }

    /**
     * A cookie PHP decoded into an array (device_view[a]) is left alone: a device_view deletion
     * would not remove the browser's bracketed cookie.
     */
    #[\Override]
    public function clear(Request $request, Response $response): bool
    {
        if (\is_array($request->cookies->all()[$this->name] ?? null)) {
            return false;
        }

        $response->headers->clearCookie($this->name, $this->path, $this->domain, $this->isSecure($request), $this->httpOnly, $this->sameSite);

        return true;
    }

    #[\Override]
    public function varyHeaders(): array
    {
        return ['Cookie'];
    }

    private function isSecure(Request $request): bool
    {
        return Cookie::SAMESITE_NONE === $this->sameSite || ($this->secure ?? $request->isSecure());
    }
}
