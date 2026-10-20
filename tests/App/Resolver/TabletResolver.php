<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Resolver;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * A custom resolver for services.resolver: always tablet, with the source default.
 */
final class TabletResolver implements ViewResolverInterface
{
    #[\Override]
    public function resolve(Request $request, DeviceViewContext $context): ResolvedView
    {
        return new ResolvedView(DeviceType::Tablet, ViewSource::Default);
    }
}
