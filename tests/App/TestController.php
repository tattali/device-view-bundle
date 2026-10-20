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

    /**
     * Same as view(), with a public response cacheable for an hour.
     */
    public function cacheable(): Response
    {
        return $this->view()->setPublic()->setMaxAge(3600);
    }

    /**
     * Forces the view, and asks to remember it when the "remember" query parameter is true.
     */
    public function setView(Request $request, string $view): Response
    {
        $this->deviceView->setView($view, $request->query->getBoolean('remember'));

        return $this->view();
    }

    public function setRememberOff(): Response
    {
        $this->deviceView->setRemember(false);

        return $this->view();
    }

    /**
     * Overrides the consent of the request: "1" grants it, "0" denies it, "null" gives the answer
     * back to the consent checker.
     */
    public function setConsent(string $value): Response
    {
        $this->deviceView->setConsent(match ($value) {
            '1' => true,
            '0' => false,
            'null' => null,
            default => throw new \InvalidArgumentException(\sprintf('Unknown consent "%s".', $value)),
        });

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
