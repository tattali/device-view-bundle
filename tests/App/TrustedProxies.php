<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use Symfony\Component\HttpFoundation\Request;

/**
 * Saves and restores the trusted proxies and headers of Request, which are set for the whole
 * process.
 *
 * Request::setTrustedProxies() only accepts a mask of Request::HEADER_* bits, so it cannot put
 * back the initial header set, -1. The restore writes both static properties as they were.
 */
final class TrustedProxies
{
    /**
     * @return array{proxies: array<string>, headers: int}
     */
    public static function save(): array
    {
        return ['proxies' => Request::getTrustedProxies(), 'headers' => Request::getTrustedHeaderSet()];
    }

    /**
     * @param array{proxies: array<string>, headers: int} $saved what save() returned
     */
    public static function restore(array $saved): void
    {
        new \ReflectionProperty(Request::class, 'trustedProxies')->setValue(null, $saved['proxies']);
        new \ReflectionProperty(Request::class, 'trustedHeaderSet')->setValue(null, $saved['headers']);
    }
}
