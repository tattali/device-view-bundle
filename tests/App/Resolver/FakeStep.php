<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Resolver;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\Step\ResolutionStepInterface;
use DeviceViewBundle\Resolver\Step\StepResult;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * A step giving a fixed result and recording its calls.
 */
final class FakeStep implements ResolutionStepInterface
{
    /** @var list<array{Request, DeviceViewContext}> */
    public private(set) array $calls = [];

    public function __construct(
        private readonly ViewSource $source,
        private readonly StepResult $result,
    ) {
    }

    #[\Override]
    public function source(): ViewSource
    {
        return $this->source;
    }

    #[\Override]
    public function find(Request $request, DeviceViewContext $context): StepResult
    {
        $this->calls[] = [$request, $context];

        return $this->result;
    }
}
