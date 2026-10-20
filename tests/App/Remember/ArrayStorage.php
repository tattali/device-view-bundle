<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Remember;

use DeviceViewBundle\Remember\ViewStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A custom remember storage for the tests (scenario STO-04): it keeps one value in memory,
 * for every visitor, so the requests of one kernel share it.
 */
final class ArrayStorage implements ViewStorageInterface
{
    /** the stored value; a test may set any value */
    public mixed $value = null;

    #[\Override]
    public function read(Request $request): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function write(Request $request, Response $response, string $name, int $lifetime): void
    {
        $this->value = $name;
    }

    #[\Override]
    public function clear(Request $request, Response $response): bool
    {
        $this->value = null;

        return true;
    }

    #[\Override]
    public function varyHeaders(): array
    {
        return [];
    }
}
