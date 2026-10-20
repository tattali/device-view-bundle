<?php

declare(strict_types=1);

namespace DeviceViewBundle;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The view of the current request, for the application's code.
 *
 * It reads and changes the context of the main request, so sub-requests see the view of their
 * main request. Outside a request (a console command, a request before the context is attached)
 * it gives default_view with the source default and never throws.
 */
final readonly class DeviceView
{
    /**
     * @param DeviceType $defaultView the view outside a request: default_view, after tablet_as_mobile
     */
    public function __construct(
        private RequestStack $requestStack,
        private ViewResolverInterface $resolver,
        private ViewNames $names,
        private SwitchUrlGenerator $switchUrls = new SwitchUrlGenerator(),
        private DeviceType $defaultView = DeviceType::Desktop,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function getView(): DeviceType
    {
        return $this->resolved()->view;
    }

    /**
     * The configured name of the view (device_view.views.names).
     */
    public function getViewName(): string
    {
        return $this->names->nameOf($this->getView());
    }

    public function isMobile(): bool
    {
        return DeviceType::Mobile === $this->getView();
    }

    public function isTablet(): bool
    {
        return DeviceType::Tablet === $this->getView();
    }

    public function isDesktop(): bool
    {
        return DeviceType::Desktop === $this->getView();
    }

    /**
     * @param DeviceType|string $view a view, or one of the configured names
     *
     * @throws \InvalidArgumentException when the name is not a configured name
     */
    public function is(DeviceType|string $view): bool
    {
        return $this->toView($view) === $this->getView();
    }

    public function getSource(): ViewSource
    {
        return $this->resolved()->source;
    }

    /**
     * Forces the view of the current request (source forced).
     *
     * @param DeviceType|string $view a view, or one of the configured names
     *
     * @throws \InvalidArgumentException when the name is not a configured name
     */
    public function setView(DeviceType|string $view): void
    {
        $view = $this->toView($view);
        $context = $this->context()[1] ?? null;
        if (null === $context) {
            $this->logger?->debug('Ignored DeviceView::setView(): there is no request.');

            return;
        }

        $context->force($view);
    }

    /**
     * The relative URL of a link that switches to the view: the current page with the switch
     * parameter, or the home page. Outside a request it is the home page.
     *
     * @param DeviceType|string $view   a view, or one of the configured names
     * @param string            $target "current" or "home"
     *
     * @throws \InvalidArgumentException when the name is not a configured name or the target is not "current" or "home"
     */
    public function getSwitchUrl(DeviceType|string $view, string $target = 'current'): string
    {
        $name = $this->names->nameOf($this->toView($view));
        $switchTarget = SwitchTarget::tryFrom($target);
        if (null === $switchTarget || SwitchTarget::None === $switchTarget) {
            throw new \InvalidArgumentException(\sprintf('Unknown switch target "%s". Expected "current" or "home".', $target));
        }

        return $this->switchUrls->switchUrl($this->requestStack->getMainRequest(), $switchTarget, $name);
    }

    private function resolved(): ResolvedView
    {
        $current = $this->context();
        if (null === $current) {
            return new ResolvedView($this->defaultView, ViewSource::Default);
        }

        [$request, $context] = $current;

        return $context->resolve($this->resolver, $request);
    }

    /**
     * @return array{Request, DeviceViewContext}|null the main request and its context
     */
    private function context(): ?array
    {
        $request = $this->requestStack->getMainRequest();
        $context = $request?->attributes->get(DeviceViewContext::ATTRIBUTE);

        return null !== $request && $context instanceof DeviceViewContext ? [$request, $context] : null;
    }

    private function toView(DeviceType|string $view): DeviceType
    {
        return $view instanceof DeviceType ? $view : $this->names->fromName($view);
    }
}
