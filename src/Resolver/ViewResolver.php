<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Event\ViewResolvedEvent;
use DeviceViewBundle\Resolver\Step\ResolutionStepInterface;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Resolves the view in the order of design.md section 5: the steps in their order, then
 * default_view, then tablet_as_mobile, then ViewResolvedEvent, once per request.
 *
 * The steps are wired in config/resolver.php, one per source. The resolver records the source
 * that decided and every input the steps asked read.
 */
final readonly class ViewResolver implements ViewResolverInterface
{
    /**
     * @param iterable<ResolutionStepInterface> $steps          the sources, in the resolution order
     * @param DeviceType                        $defaultView    the view when no step decides
     * @param bool                              $tabletAsMobile whether a tablet view becomes the mobile view
     * @param EventDispatcherInterface|null     $dispatcher     dispatches ViewResolvedEvent; without it, no event
     */
    public function __construct(
        private iterable $steps,
        private DeviceType $defaultView = DeviceType::Desktop,
        private bool $tabletAsMobile = false,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    #[\Override]
    public function resolve(Request $request, DeviceViewContext $context): ResolvedView
    {
        $view = $this->defaultView;
        $source = ViewSource::Default;
        $consulted = [];
        foreach ($this->steps as $step) {
            $result = $step->find($request, $context);
            array_push($consulted, ...$result->consultedInputs);
            if (null !== $result->view) {
                $view = $result->view;
                $source = $step->source();
                break;
            }
        }

        if ($this->tabletAsMobile && DeviceType::Tablet === $view) {
            $view = DeviceType::Mobile;
        }

        $consulted = array_values(array_unique($consulted));
        if (null !== $this->dispatcher && !$context->viewResolvedDispatched) {
            $event = new ViewResolvedEvent($view, $source, $request);
            // a listener reading the view gets the view of the event, without resolving again
            $context->beginViewResolvedDispatch($event, $consulted);
            try {
                $this->dispatcher->dispatch($event);
            } finally {
                $context->endViewResolvedDispatch();
            }

            if ($context->forcedDuringViewResolved) {
                // DeviceView::setView() in a listener: the forced view wins, without a new event. It
                // runs only the steps up to the forced one, which this resolution already ran, so
                // the inputs read before the event are all the inputs.
                $forced = $this->resolve($request, $context);

                return new ResolvedView($forced->view, $forced->source, $consulted);
            }

            $view = $event->getView();
        }

        return new ResolvedView($view, $source, $consulted);
    }
}
