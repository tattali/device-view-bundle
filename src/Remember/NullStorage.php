<?php

declare(strict_types=1);

namespace DeviceViewBundle\Remember;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The storage of device_view.remember.storage: none, which remembers nothing: it holds no view,
 * reads no request input and writes nothing.
 */
final readonly class NullStorage implements ViewStorageInterface
{
    #[\Override]
    public function read(Request $request): mixed
    {
        return null;
    }

    #[\Override]
    public function write(Request $request, Response $response, string $name, int $lifetime): void
    {
    }

    #[\Override]
    public function clear(Request $request, Response $response): void
    {
    }

    #[\Override]
    public function varyHeaders(): array
    {
        return [];
    }
}
