<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * The view forced with DeviceView::setView(). It reads no request input.
 *
 * @internal
 */
final readonly class ForcedStep implements ResolutionStepInterface
{
    #[\Override]
    public function source(): ViewSource
    {
        return ViewSource::Forced;
    }

    #[\Override]
    public function find(Request $request, DeviceViewContext $context): StepResult
    {
        return new StepResult($context->forcedView);
    }
}
