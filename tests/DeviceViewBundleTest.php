<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests;

use DeviceViewBundle\DeviceViewBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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

    private static function configFile(string $name): string
    {
        $path = realpath(\dirname(__DIR__).'/config/'.$name);
        self::assertIsString($path, $name.' exists');

        return $path;
    }
}
