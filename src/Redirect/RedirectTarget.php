<?php

declare(strict_types=1);

namespace DeviceViewBundle\Redirect;

/**
 * Where a redirect of the bundle goes: its Location and its status.
 *
 * @internal
 */
final readonly class RedirectTarget
{
    /**
     * @param string $url    the absolute URL of the Location
     * @param int    $status the redirect status
     */
    public function __construct(
        public string $url,
        public int $status,
    ) {
    }
}
