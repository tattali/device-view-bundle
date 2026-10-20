<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\EventListener\VaryListener;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class VaryListenerTest extends TestCase
{
    public function testTheConsultedInputsAreListed(): void
    {
        $request = self::resolvedRequest(['Cookie', 'User-Agent']);

        $response = self::respond($request);

        self::assertSame(['Cookie, User-Agent'], $response->headers->all('vary'));
    }

    public function testNothingWithoutAContext(): void
    {
        $response = self::respond(Request::create('/view'));

        self::assertFalse($response->headers->has('Vary'));
    }

    public function testNothingWhenNoInputWasConsulted(): void
    {
        $request = Request::create('/view');
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, new DeviceViewContext());

        $response = self::respond($request, new Response()->setVary('Accept-Language'));

        self::assertSame(['Accept-Language'], $response->headers->all('vary'));
    }

    public function testSubRequestsAreLeftAlone(): void
    {
        $request = self::resolvedRequest(['Cookie', 'User-Agent']);

        $response = self::respond($request, type: HttpKernelInterface::SUB_REQUEST);

        self::assertFalse($response->headers->has('Vary'));
    }

    public function testExistingValuesAreKeptAndComparedWithoutCase(): void
    {
        $request = self::resolvedRequest(['Cookie', 'User-Agent', 'X-Device-User-Agent']);

        $response = self::respond($request, new Response()->setVary(['Accept-Language', 'user-agent']));

        self::assertSame(['Accept-Language', 'user-agent', 'Cookie, X-Device-User-Agent'], $response->headers->all('vary'));
        self::assertSame(['Accept-Language', 'user-agent', 'Cookie', 'X-Device-User-Agent'], $response->getVary());
    }

    public function testSkippedRequestsVaryToo(): void
    {
        $request = self::resolvedRequest(['User-Agent'], new DeviceViewContext(skipped: true));

        self::assertSame(['User-Agent'], self::respond($request)->getVary());
    }

    /**
     * A request whose context resolved the view after consulting $inputs.
     *
     * @param list<string> $inputs
     */
    private static function resolvedRequest(array $inputs, DeviceViewContext $context = new DeviceViewContext()): Request
    {
        $request = Request::create('/view');
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);
        $context->resolve(new readonly class($inputs) implements ViewResolverInterface {
            /**
             * @param list<string> $inputs
             */
            public function __construct(private array $inputs)
            {
            }

            #[\Override]
            public function resolve(Request $request, DeviceViewContext $context): ResolvedView
            {
                return new ResolvedView(DeviceType::Mobile, ViewSource::Detector, $this->inputs);
            }
        }, $request);

        return $request;
    }

    private static function respond(Request $request, Response $response = new Response(), int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        new VaryListener()->onKernelResponse(new ResponseEvent(new Kernel(), $request, $type, $response));

        return $response;
    }
}
