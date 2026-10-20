<?php

declare(strict_types=1);

namespace DeviceViewBundle\Redirect;

use Symfony\Component\HttpFoundation\Request;

/**
 * The host a view is served on (device_view.redirect.<view>.host): an absolute http(s) URL with
 * a host, an optional port and an optional base path.
 *
 * It is normalised so that equal hosts compare equal: the scheme and the host are lower-cased,
 * a trailing dot of the host, a default port (80 for http, 443 for https) and a trailing slash
 * of the base path are removed.
 *
 * @internal
 */
final readonly class RedirectHost
{
    private const array DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @param string   $scheme   http or https
     * @param string   $host     lower case, without a trailing dot
     * @param int|null $port     null for the default port of the scheme
     * @param string   $basePath empty, or a path starting with "/" and without a trailing slash
     */
    private function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
        public string $basePath,
    ) {
    }

    /**
     * The host of the URL, or null when it is not an absolute http(s) URL with a host, or has a
     * query, a fragment, user info, a space or a control character.
     */
    public static function parse(string $url): ?self
    {
        if (1 === preg_match('/[\x00-\x20\x7F]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (false === $parts || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = self::normaliseHost($parts['host'] ?? '');
        if (!\array_key_exists($scheme, self::DEFAULT_PORTS) || '' === $host) {
            return null;
        }

        return new self($scheme, $host, self::port($scheme, $parts['port'] ?? null), rtrim($parts['path'] ?? '', '/'));
    }

    /**
     * Whether the request was sent to this host and port, as the request sees them (trusted
     * proxies included). The scheme and the base path do not count: a host serves one view
     * whatever its scheme, and a base path is a part of that host.
     */
    public function isCurrentHost(Request $request): bool
    {
        return $this->host === self::normaliseHost($request->getHost())
            && $this->port === self::port($request->getScheme(), (int) $request->getPort());
    }

    /**
     * The host, with its port when it is not the default one, e.g. "m.example.com:8080".
     */
    public function authority(): string
    {
        return null === $this->port ? $this->host : $this->host.':'.$this->port;
    }

    /**
     * The absolute URL of a path on this host, after its base path.
     *
     * @param string $path a path starting with "/", with its query
     */
    public function url(string $path): string
    {
        return $this->scheme.'://'.$this->authority().$this->basePath.$path;
    }

    private static function normaliseHost(string $host): string
    {
        return rtrim(strtolower($host), '.');
    }

    private static function port(string $scheme, ?int $port): ?int
    {
        return (self::DEFAULT_PORTS[$scheme] ?? null) === $port ? null : $port;
    }
}
