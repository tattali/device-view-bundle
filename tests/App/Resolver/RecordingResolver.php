<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Resolver;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns the given results in order and records its calls.
 */
final class RecordingResolver implements ViewResolverInterface
{
    /** @var list<array{Request, DeviceViewContext}> */
    public private(set) array $calls = [];

    /**
     * @param list<ResolvedView> $results
     */
    public function __construct(
        private array $results,
    ) {
    }

    #[\Override]
    public function resolve(Request $request, DeviceViewContext $context): ResolvedView
    {
        $this->calls[] = [$request, $context];

        return array_shift($this->results) ?? throw new \LogicException('No result left.');
    }
}
