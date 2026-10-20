<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver;

use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;

/**
 * The view of a request, what decided it, and the request inputs read to get there.
 */
final readonly class ResolvedView
{
    /**
     * @param list<string> $consultedInputs the request headers read during the resolution, which the response lists in Vary
     */
    public function __construct(
        public DeviceType $view,
        public ViewSource $source,
        public array $consultedInputs = [],
    ) {
    }
}
