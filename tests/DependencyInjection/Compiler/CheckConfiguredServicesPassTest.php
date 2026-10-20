<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\DependencyInjection\Compiler;

use DeviceViewBundle\DependencyInjection\Compiler\CheckConfiguredServicesPass;
use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\Tests\App\Detector\HeaderDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class CheckConfiguredServicesPassTest extends TestCase
{
    public function testAContainerWithoutChecksPasses(): void
    {
        $container = new ContainerBuilder();

        new CheckConfiguredServicesPass()->process($container);

        self::assertFalse($container->hasParameter(CheckConfiguredServicesPass::PARAMETER));
    }

    public function testAServiceImplementingTheInterfacePasses(): void
    {
        $container = self::containerChecking('app.detector');
        $container->setDefinition('app.detector', new Definition(HeaderDetector::class));

        new CheckConfiguredServicesPass()->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testAnAliasIsFollowed(): void
    {
        $container = self::containerChecking('app.detector');
        $container->setDefinition('app.real_detector', new Definition(HeaderDetector::class));
        $container->setAlias('app.detector', 'app.real_detector');

        new CheckConfiguredServicesPass()->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testAClassGivenByAParameterIsResolved(): void
    {
        $container = self::containerChecking('app.detector');
        $container->setParameter('app.detector_class', HeaderDetector::class);
        $container->setDefinition('app.detector', new Definition('%app.detector_class%'));

        new CheckConfiguredServicesPass()->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testAChildDefinitionTakesTheClassOfItsParents(): void
    {
        $container = self::containerChecking('app.detector');
        $container->setDefinition('app.base_detector', new Definition(HeaderDetector::class)->setAbstract(true));
        $container->setDefinition('app.middle_detector', new ChildDefinition('app.base_detector'));
        $container->setDefinition('app.detector', new ChildDefinition('app.middle_detector'));

        new CheckConfiguredServicesPass()->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testAChildDefinitionWithItsOwnClassIsCheckedOnThatClass(): void
    {
        $container = self::containerChecking('app.detector');
        $container->setDefinition('app.base_detector', new Definition(HeaderDetector::class)->setAbstract(true));
        $container->setDefinition('app.detector', new ChildDefinition('app.base_detector')->setClass(\stdClass::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.detector" set in device_view.detector.id must implement DeviceViewBundle\Detector\DeviceDetectorInterface.');

        new CheckConfiguredServicesPass()->process($container);
    }

    public function testAMissingServiceFails(): void
    {
        $container = self::containerChecking('app.nope');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.nope" set in device_view.detector.id does not exist.');

        new CheckConfiguredServicesPass()->process($container);
    }

    public function testAServiceOfAnotherClassFails(): void
    {
        $container = self::containerChecking('app.not_a_detector');
        $container->setDefinition('app.not_a_detector', new Definition(\stdClass::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.not_a_detector" set in device_view.detector.id must implement DeviceViewBundle\Detector\DeviceDetectorInterface.');

        new CheckConfiguredServicesPass()->process($container);
    }

    public function testAServiceWithoutAClassFails(): void
    {
        $container = self::containerChecking('app.detector');
        $container->setDefinition('app.detector', new Definition());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.detector" set in device_view.detector.id must implement DeviceViewBundle\Detector\DeviceDetectorInterface.');

        new CheckConfiguredServicesPass()->process($container);
    }

    public function testEveryCheckRuns(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(CheckConfiguredServicesPass::PARAMETER, [
            ['app.detector', 'device_view.detector.id', DeviceDetectorInterface::class],
            ['app.cache', 'device_view.detector.mobile_detect.cache', 'Psr\SimpleCache\CacheInterface'],
        ]);
        $container->setDefinition('app.detector', new Definition(HeaderDetector::class));
        $container->setDefinition('app.cache', new Definition(\stdClass::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.cache" set in device_view.detector.mobile_detect.cache must implement Psr\SimpleCache\CacheInterface.');

        new CheckConfiguredServicesPass()->process($container);
    }

    private static function containerChecking(string $id): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(CheckConfiguredServicesPass::PARAMETER, [
            [$id, 'device_view.detector.id', DeviceDetectorInterface::class],
        ]);

        return $container;
    }
}
