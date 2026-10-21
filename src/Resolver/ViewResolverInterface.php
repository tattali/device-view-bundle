<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver;

use DeviceViewBundle\Context\DeviceViewContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the view of a main request.
 *
 * The context calls it lazily, the first time something asks for the view, and keeps the
 * result for the rest of the request. Implementations are shared between requests, so they keep
 * no request state. Replace it with device_view.services.resolver or by decorating
 * device_view.resolver. The built-in resolver dispatches ViewResolvedEvent; a replacement
 * dispatches it itself when it needs it.
 */
interface ViewResolverInterface
{
    public function resolve(Request $request, DeviceViewContext $context): ResolvedView;
}
