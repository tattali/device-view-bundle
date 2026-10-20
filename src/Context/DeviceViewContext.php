<?php

declare(strict_types=1);

namespace DeviceViewBundle\Context;

use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\View\DeviceType;
use Symfony\Component\HttpFoundation\Request;

/**
 * The device view state of one main request, stored in its ATTRIBUTE attribute.
 *
 * It is the only place that holds request state: the bundle's services are shared between
 * requests and keep none. Sub-requests read the context of the main request.
 */
final class DeviceViewContext
{
    public const string ATTRIBUTE = '_device_view';

    /**
     * The resolved view, or null while nothing has asked for it.
     */
    public private(set) ?ResolvedView $resolved = null;

    /**
     * The view forced with DeviceView::setView(), or null.
     */
    public private(set) ?DeviceType $forcedView = null;

    /**
     * Every request input read by the resolutions of this request, which the response lists in Vary.
     *
     * @var list<string>
     */
    public private(set) array $consultedInputs = [];

    /**
     * @param bool $skipped whether the request is skipped: it still resolves its view, but never switches, redirects or remembers
     */
    public function __construct(
        public private(set) bool $skipped = false,
    ) {
    }

    /**
     * Resolves the view the first time it is asked for, then returns the same result.
     */
    public function resolve(ViewResolverInterface $resolver, Request $request): ResolvedView
    {
        if (null === $this->resolved) {
            $this->resolved = $resolver->resolve($request, $this);
            $this->consultedInputs = array_values(array_unique([...$this->consultedInputs, ...$this->resolved->consultedInputs]));
        }

        return $this->resolved;
    }

    /**
     * Forces the view: the next read resolves again, with the forced view first. The inputs
     * already read stay recorded.
     */
    public function force(DeviceType $view): void
    {
        $this->forcedView = $view;
        $this->resolved = null;
    }
}
