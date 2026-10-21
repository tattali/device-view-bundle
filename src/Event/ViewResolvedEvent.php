<?php

declare(strict_types=1);

namespace DeviceViewBundle\Event;

use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per main request, the first time its view is resolved, after tablet_as_mobile.
 *
 * A listener may set another view: the DeviceView facade, the redirects and the remember step
 * then use it, tablet_as_mobile does not map it again, and the source stays the one that decided.
 * A view forced with DeviceView::setView() after this event is not dispatched again.
 */
final class ViewResolvedEvent extends Event
{
    /**
     * @param Request $request the main request
     */
    public function __construct(
        private DeviceType $view,
        private readonly ViewSource $source,
        private readonly Request $request,
    ) {
    }

    public function getView(): DeviceType
    {
        return $this->view;
    }

    public function setView(DeviceType $view): void
    {
        $this->view = $view;
    }

    /**
     * What decided the view before the listeners; setView() does not change it.
     */
    public function getSource(): ViewSource
    {
        return $this->source;
    }

    /**
     * The main request.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
