<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\Step\ResolutionStepInterface;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the view in the order of design.md section 5: the steps in their order, then
 * default_view, then tablet_as_mobile.
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
     */
    public function __construct(
        private iterable $steps,
        private DeviceType $defaultView = DeviceType::Desktop,
        private bool $tabletAsMobile = false,
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

        return new ResolvedView($view, $source, array_values(array_unique($consulted)));
    }
}
