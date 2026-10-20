<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Remember\RememberPolicy;
use DeviceViewBundle\Remember\RememberTrigger;
use DeviceViewBundle\Remember\ViewStorageInterface;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Remembers the view on the response of the main request, on kernel.response at priority 0.
 *
 * A switch, or DeviceView::setView() asked to remember, plans a write of its view with the switch
 * trigger: that view is written, after tablet_as_mobile, even when the request uses another view.
 * A view the request used and that came from the detector or the trusted header plans one with
 * the detected trigger. The remember policy decides whether the write happens and its lifetime;
 * DeviceView::setRemember(false) stops it. When nothing is written, a forged stored value is
 * expired. A response that writes or expires the stored value becomes private when
 * http_cache.private_when_remembering is set.
 *
 * Skipped requests never write nor expire. Once this listener ran, DeviceView ignores changes.
 */
final readonly class RememberListener
{
    public function __construct(
        private ViewResolverInterface $resolver,
        private ViewStorageInterface $storage,
        private RememberPolicy $policy,
        private ViewNames $names,
        private bool $privateWhenRemembering = true,
        private bool $tabletAsMobile = false,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        if (!$context instanceof DeviceViewContext) {
            return;
        }

        $context->markResponseBuilt();
        if ($context->skipped) {
            return;
        }

        $response = $event->getResponse();
        $stored = $this->remember($request, $response, $context);
        if (!$stored && $context->forgedStoredValue) {
            $this->storage->clear($request, $response);
            $stored = true;
        }

        if ($stored && $this->privateWhenRemembering) {
            $response->setPrivate();
        }
    }

    /**
     * Writes the view when a write is planned and allowed.
     */
    private function remember(Request $request, Response $response, DeviceViewContext $context): bool
    {
        $trigger = $this->trigger($context);
        if (null === $trigger || false === $context->remember) {
            return false;
        }

        // the consent is unknown until a consent checker can be configured
        $lifetime = $this->policy->lifetime($trigger);
        if (null === $lifetime) {
            return false;
        }

        $view = $context->plannedView ?? $context->resolve($this->resolver, $request)->view;
        if ($this->tabletAsMobile && DeviceType::Tablet === $view) {
            $view = DeviceType::Mobile;
        }

        $this->storage->write($request, $response, $this->names->nameOf($view), $lifetime);

        return true;
    }

    private function trigger(DeviceViewContext $context): ?RememberTrigger
    {
        if (null !== $context->plannedView) {
            return RememberTrigger::Switch;
        }

        // a detected write only remembers a view the request used: nothing is resolved here
        $source = $context->resolved?->source;

        return ViewSource::Detector === $source || ViewSource::TrustedHeader === $source ? RememberTrigger::Detected : null;
    }
}
