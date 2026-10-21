<?php

declare(strict_types=1);

namespace DeviceViewBundle\Context;

use DeviceViewBundle\Redirect\RedirectAction;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\Routing\RouteDefaults;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
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
     * The view the switch parameter asks for, once read: null when the request has no valid
     * switch parameter or nothing has read it yet.
     */
    public private(set) ?DeviceType $switchView = null;

    /**
     * Whether the switch listener applied the switch parameter to this request.
     */
    public private(set) bool $switched = false;

    /**
     * The view planned for a write with the switch trigger, or null: the view of the switch, or
     * the last view DeviceView::setView() asked to remember. The response remembers it, after
     * tablet_as_mobile, even when the request uses another view.
     */
    public private(set) ?DeviceType $plannedView = null;

    /**
     * The override set with DeviceView::setRemember(): false stops any write, true lets the
     * remember policy decide; null when it was not called.
     */
    public private(set) ?bool $remember = null;

    /**
     * The override set with DeviceView::setConsent(): true grants consent, false denies it, for
     * this request; null when it was not called or was reset, so the consent checker answers.
     */
    public private(set) ?bool $consent = null;

    /**
     * Whether the remember storage holds a string that is not a view name, which the response
     * expires.
     */
    public private(set) bool $forgedStoredValue = false;

    /**
     * Whether ViewResolvedEvent was dispatched for this request: it is dispatched once.
     */
    public private(set) bool $viewResolvedDispatched = false;

    /**
     * Whether the response listener ran: DeviceView ignores changes from then on.
     */
    public private(set) bool $responseBuilt = false;

    /**
     * The action the redirect route default of the matched route sets, once read: null when the
     * route sets none or an invalid one, or nothing has read it yet.
     */
    public private(set) ?RedirectAction $routeRedirect = null;

    /**
     * What the remember route default of the matched route says, once read: false stops any
     * write; null when the route says nothing or something invalid, or nothing has read it yet.
     */
    public private(set) ?bool $routeRemember = null;

    private bool $switchRead = false;

    private bool $routeRedirectRead = false;

    private bool $routeRememberRead = false;

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
     * Reads the switch parameter the first time it is asked for, then returns the same view, so
     * an invalid value is logged once even though the listener and the resolver both ask.
     */
    public function readSwitch(SwitchParameter $parameter, Request $request): ?DeviceType
    {
        if (!$this->switchRead) {
            $this->switchRead = true;
            $this->switchView = $parameter->read($request, $this);
        }

        return $this->switchView;
    }

    /**
     * Reads the redirect route default the first time it is asked for, then returns the same
     * action, so an invalid value is logged once even though several listeners ask. It is asked
     * for only after routing, when the request attributes hold the defaults of the matched route.
     */
    public function routeRedirect(RouteDefaults $defaults, Request $request): ?RedirectAction
    {
        if (!$this->routeRedirectRead) {
            $this->routeRedirectRead = true;
            $this->routeRedirect = $defaults->redirect($request);
        }

        return $this->routeRedirect;
    }

    /**
     * Reads the remember route default the first time it is asked for, then returns the same
     * answer, as routeRedirect() does.
     */
    public function routeRemember(RouteDefaults $defaults, Request $request): ?bool
    {
        if (!$this->routeRememberRead) {
            $this->routeRememberRead = true;
            $this->routeRemember = $defaults->remember($request);
        }

        return $this->routeRemember;
    }

    /**
     * Records that the switch applied to this request, and plans to remember its view.
     */
    public function markSwitched(DeviceType $view): void
    {
        $this->switched = true;
        $this->plannedView = $view;
    }

    /**
     * Forces the view: the next read resolves again, with the forced view first. The inputs
     * already read stay recorded.
     *
     * @param bool $remember whether to remember the view like a switch, which replaces the planned view; without it, the planned view stays
     */
    public function force(DeviceType $view, bool $remember = false): void
    {
        $this->forcedView = $view;
        if ($remember) {
            $this->plannedView = $view;
        }

        $this->resolved = null;
    }

    public function setRemember(bool $remember): void
    {
        $this->remember = $remember;
    }

    public function setConsent(?bool $consent): void
    {
        $this->consent = $consent;
    }

    /**
     * Records that the remember storage holds a forged value.
     */
    public function markForgedStoredValue(): void
    {
        $this->forgedStoredValue = true;
    }

    /**
     * Records that ViewResolvedEvent was dispatched for this request.
     */
    public function markViewResolvedDispatched(): void
    {
        $this->viewResolvedDispatched = true;
    }

    /**
     * Records that the response listener ran.
     */
    public function markResponseBuilt(): void
    {
        $this->responseBuilt = true;
    }
}
