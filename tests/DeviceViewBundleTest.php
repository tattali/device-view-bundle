<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests;

use DeviceViewBundle\DeviceViewBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

final class DeviceViewBundleTest extends TestCase
{
    public function testTheBundleRootHoldsItsFiles(): void
    {
        self::assertSame(\dirname(__DIR__), new DeviceViewBundle()->getPath());
    }

    public function testTheExtensionLoadsItsDefinitionAndServicesFromConfig(): void
    {
        $extension = new DeviceViewBundle()->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('device_view', $extension->getAlias());

        $container = new ContainerBuilder(new ParameterBag(['kernel.environment' => 'test']));
        $extension->load([], $container);

        $resources = array_map(
            static fn (ResourceInterface $resource): string => (string) $resource,
            $container->getResources(),
        );
        self::assertContains(self::configFile('definition.php'), $resources);
        self::assertContains(self::configFile('services.php'), $resources);
    }

    public function testWithoutATrustedHeaderNameTheStepIsRemoved(): void
    {
        $container = self::load([]);

        self::assertFalse($container->hasDefinition('device_view.resolver.step.trusted_header'));
        self::assertSame([
            'device_view.resolver.step.forced',
            'device_view.resolver.step.switch',
            'device_view.resolver.step.remembered',
            'device_view.resolver.step.detector',
        ], self::steps($container));
    }

    public function testATrustedHeaderNameAddsTheStepBeforeTheDetector(): void
    {
        $container = self::load(['trusted_header' => ['name' => 'X-Device-View', 'trusted_proxies_only' => false]]);

        $step = $container->getDefinition('device_view.resolver.step.trusted_header');
        self::assertSame('X-Device-View', $step->getArgument(0));
        self::assertFalse($step->getArgument(1));
        self::assertSame([
            'device_view.resolver.step.forced',
            'device_view.resolver.step.switch',
            'device_view.resolver.step.remembered',
            'device_view.resolver.step.trusted_header',
            'device_view.resolver.step.detector',
        ], self::steps($container));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function load(array $config): ContainerBuilder
    {
        $extension = new DeviceViewBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $container = new ContainerBuilder(new ParameterBag(['kernel.environment' => 'test']));
        $extension->load([$config], $container);

        return $container;
    }

    /**
     * The ids of the steps of the built-in resolver, with their keys.
     *
     * @return array<array-key, string>
     */
    private static function steps(ContainerBuilder $container): array
    {
        $steps = $container->getDefinition('device_view.resolver.default')->getArgument(0);
        self::assertIsArray($steps);

        return array_map(static function (mixed $step): string {
            self::assertInstanceOf(Reference::class, $step);

            return (string) $step;
        }, $steps);
    }

    private static function configFile(string $name): string
    {
        $path = realpath(\dirname(__DIR__).'/config/'.$name);
        self::assertIsString($path, $name.' exists');

        return $path;
    }
}
