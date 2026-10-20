<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\DependencyInjection\Compiler;

use DeviceViewBundle\DependencyInjection\Compiler\CheckSessionStoragePass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Session\SessionFactory;

final class CheckSessionStoragePassTest extends TestCase
{
    public function testAContainerWithoutTheParameterPasses(): void
    {
        $container = new ContainerBuilder();

        new CheckSessionStoragePass()->process($container);

        self::assertFalse($container->hasParameter(CheckSessionStoragePass::PARAMETER));
    }

    public function testAnotherStorageNeedsNoSession(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(CheckSessionStoragePass::PARAMETER, false);

        new CheckSessionStoragePass()->process($container);

        self::assertFalse($container->has('session.factory'));
    }

    #[Group('CFG-57')]
    public function testTheSessionStorageNeedsTheFrameworkSession(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(CheckSessionStoragePass::PARAMETER, true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.remember.storage "session" needs the framework session: enable framework.session, or set device_view.remember.storage to another storage.');

        new CheckSessionStoragePass()->process($container);
    }

    public function testTheSessionStoragePassesWithTheFrameworkSession(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(CheckSessionStoragePass::PARAMETER, true);
        $container->setDefinition('session.factory', new Definition(SessionFactory::class));

        new CheckSessionStoragePass()->process($container);

        self::assertTrue($container->has('session.factory'));
    }
}
