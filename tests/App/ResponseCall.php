<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use DeviceViewBundle\DeviceView;

/**
 * Calls the DeviceView facade on kernel.response at priority -10, after the bundle's response
 * listener built the response. A test sets $calls before handling a request.
 */
final class ResponseCall
{
    /** @var (\Closure(DeviceView): void)|null */
    public ?\Closure $calls = null;

    public function __construct(
        private readonly DeviceView $deviceView,
    ) {
    }

    public function onKernelResponse(): void
    {
        if (null !== $this->calls) {
            ($this->calls)($this->deviceView);
        }
    }
}
