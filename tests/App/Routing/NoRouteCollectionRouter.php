<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Decorates the router of the application: it matches and generates as the router does, but its
 * getRouteCollection() throws, so a test proves that nothing loads the route collection while a
 * request is handled (REG-15).
 *
 * The decorated router still loads its own collection when it compiles its matcher.
 */
final readonly class NoRouteCollectionRouter implements RouterInterface, RequestMatcherInterface, WarmableInterface
{
    public function __construct(
        private RouterInterface&RequestMatcherInterface&WarmableInterface $router,
    ) {
    }

    #[\Override]
    public function getRouteCollection(): RouteCollection
    {
        throw new \LogicException('The route collection must not be loaded while a request is handled.');
    }

    /**
     * @return array<array-key, mixed>
     */
    #[\Override]
    public function matchRequest(Request $request): array
    {
        return $this->router->matchRequest($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    #[\Override]
    public function match(string $pathinfo): array
    {
        return $this->router->match($pathinfo);
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[\Override]
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->router->generate($name, $parameters, $referenceType);
    }

    #[\Override]
    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    #[\Override]
    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    #[\Override]
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->router->warmUp($cacheDir, $buildDir);
    }
}
