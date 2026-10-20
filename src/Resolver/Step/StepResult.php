<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver\Step;

use DeviceViewBundle\View\DeviceType;

/**
 * What one source of the resolution order gave: a view, or null to let the next source decide,
 * and the request inputs it read either way.
 *
 * @internal
 */
final readonly class StepResult
{
    /**
     * @param list<string> $consultedInputs the request headers read, which the response lists in Vary
     */
    public function __construct(
        public ?DeviceType $view = null,
        public array $consultedInputs = [],
    ) {
    }
}
