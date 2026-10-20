<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Remember;

use DeviceViewBundle\Remember\ViewStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A custom remember storage for the tests (scenario STO-04): it keeps one view name in memory,
 * for every visitor, so the requests of one kernel share it.
 */
final class ArrayStorage implements ViewStorageInterface
{
    private ?string $name = null;

    #[\Override]
    public function read(Request $request): ?string
    {
        return $this->name;
    }

    #[\Override]
    public function write(Request $request, Response $response, string $name, int $lifetime): void
    {
        $this->name = $name;
    }

    #[\Override]
    public function clear(Request $request, Response $response): void
    {
        $this->name = null;
    }

    #[\Override]
    public function varyHeaders(): array
    {
        return [];
    }
}
