<?php

declare(strict_types=1);

namespace DeviceViewBundle\ViewSwitch;

use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the relative URLs of the switch: the links that switch the view, and the Location of
 * the redirect that follows a switch.
 *
 * A URL is the base URL of the request, then the path info (or "/" for the home page), then the
 * query rebuilt from the request's query parameters with "&" and RFC 3986 encoding, in their
 * order. Nothing comes from the Host header.
 *
 * @internal
 */
final readonly class SwitchUrlGenerator
{
    /**
     * @param string $parameter the switch query parameter
     */
    public function __construct(
        private string $parameter = 'device_view',
    ) {
    }

    /**
     * The URL that switches to the view of the name: the target page, with the switch parameter
     * set to the name, after the other query parameters of the current page. Without a request,
     * it is the home page.
     */
    public function switchUrl(?Request $request, SwitchTarget $target, string $name): string
    {
        $query = null !== $request && SwitchTarget::Home !== $target ? $this->otherParameters($request) : [];
        $query[$this->parameter] = $name;

        return self::path($request, $target).'?'.self::query($query);
    }

    /**
     * Where a switch redirects: the current page without the switch parameter, or the home page
     * without any query.
     */
    public function redirectUrl(Request $request, SwitchTarget $target): string
    {
        $path = self::path($request, $target);
        $query = SwitchTarget::Home === $target ? '' : self::query($this->otherParameters($request));

        return '' === $query ? $path : $path.'?'.$query;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function otherParameters(Request $request): array
    {
        $query = $request->query->all();
        unset($query[$this->parameter]);

        return $query;
    }

    private static function path(?Request $request, SwitchTarget $target): string
    {
        $path = null === $request ? '/' : $request->getBaseUrl().(SwitchTarget::Home === $target ? '/' : $request->getPathInfo());

        // "//host" or "/\host" would be read as a URL of another host: keep one leading slash
        return '/'.ltrim($path, '/\\');
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function query(array $query): string
    {
        return http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }
}
