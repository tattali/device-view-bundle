<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use DeviceViewBundle\DeviceView;
use DeviceViewBundle\View\DeviceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The controllers of the routes listed in docs/scenarios.md, "Test application routes".
 */
final readonly class TestController
{
    public function __construct(
        private DeviceView $deviceView,
        private HttpKernelInterface $httpKernel,
    ) {
    }

    public function home(): Response
    {
        return new Response('home page');
    }

    /**
     * Prints the view name and its source, e.g. "mobile detector".
     */
    public function view(): Response
    {
        return new Response($this->deviceView->getViewName().' '.$this->deviceView->getSource()->value);
    }

    public function setView(string $view): Response
    {
        $this->deviceView->setView($view);

        return $this->view();
    }

    /**
     * Every reader of the DeviceView facade, with is() asked for the name in the path.
     */
    public function readers(string $name): JsonResponse
    {
        return new JsonResponse([
            'view' => $this->deviceView->getView()->value,
            'name' => $this->deviceView->getViewName(),
            'mobile' => $this->deviceView->isMobile(),
            'tablet' => $this->deviceView->isTablet(),
            'desktop' => $this->deviceView->isDesktop(),
            'is' => $this->deviceView->is($name),
            'is_mobile_type' => $this->deviceView->is(DeviceType::Mobile),
            'source' => $this->deviceView->getSource()->value,
        ]);
    }

    /**
     * Handles a sub-request to the "sub" query parameter (default /view) before reading the
     * view of the main request.
     */
    public function subRequest(Request $request): Response
    {
        $sub = $this->httpKernel->handle(Request::create($request->query->getString('sub', '/view')), HttpKernelInterface::SUB_REQUEST);

        return new Response(\sprintf('main=%s sub=%s', $this->deviceView->getViewName(), (string) $sub->getContent()));
    }
}
