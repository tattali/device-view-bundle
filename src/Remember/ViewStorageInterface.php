<?php

declare(strict_types=1);

namespace DeviceViewBundle\Remember;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where the view of a visitor is remembered between requests: the cookie storage by default,
 * the session storage or none with device_view.remember.storage.
 *
 * The storage keeps a view name (device_view.views.names) and gives back what it holds as the
 * request gives it: the remembered step of the resolver validates it, so a storage never needs
 * to. Implementations are shared between requests, so they keep no request state. Replace it by
 * setting device_view.remember.storage to the id of your service, or by decorating
 * device_view.storage.
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
     * Removes the stored value, which is not a configured view name, on the response.
     *
     * @return bool true when the value was removed; false when nothing was done, because this
     *              storage cannot remove what the request holds
     */
    public function clear(Request $request, Response $response): bool;

    /**
     * The request headers read() reads, which the response lists in Vary.
     *
     * @return list<string>
     */
    public function varyHeaders(): array;
}
