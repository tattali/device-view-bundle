<?php

declare(strict_types=1);

namespace DeviceViewBundle\Remember;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where the view of a visitor is remembered between requests: the cookie storage by default.
 *
 * The storage keeps a view name (device_view.views.names) and gives back what it holds as the
 * request gives it: the remembered step of the resolver validates it, so a storage never needs
 * to. Implementations are shared between requests, so they keep no request state. Replace it by
 * decorating device_view.storage.
 */
interface ViewStorageInterface
{
    /**
     * The stored value, unchecked, or null when nothing is stored. It may be anything the request
     * gives: PHP decodes a cookie named device_view[a] into an array, for instance.
     */
    public function read(Request $request): mixed;

    /**
     * Stores the view name on the response.
     *
     * @param string $name     the configured name of the view
     * @param int    $lifetime seconds; 0 keeps it until the browser session ends
     */
    public function write(Request $request, Response $response, string $name, int $lifetime): void;

    /**
     * Removes the stored value on the response.
     */
    public function clear(Request $request, Response $response): void;

    /**
     * The request headers read() reads, which the response lists in Vary.
     *
     * @return list<string>
     */
    public function varyHeaders(): array;
}
