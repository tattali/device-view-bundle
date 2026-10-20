<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use DeviceViewBundle\DeviceView;

/**
 * Calls the DeviceView facade on kernel.controller, while the request is the main request on the
 * stack, as a controller would. A test sets $calls before handling a request, then reads
 * $results.
 */
final class ControllerCall
{
    /** @var (\Closure(DeviceView): list<string>)|null */
    public ?\Closure $calls = null;

    /** @var list<string>|null */
    public private(set) ?array $results = null;

    public function __construct(
        private readonly DeviceView $deviceView,
    ) {
    }

    public function onKernelController(): void
    {
        if (null !== $this->calls) {
            $this->results = ($this->calls)($this->deviceView);
        }
    }
}
