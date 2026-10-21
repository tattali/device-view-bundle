<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\DependencyInjection\Compiler;

use DeviceViewBundle\DependencyInjection\Compiler\FragmentPathPass;
use DeviceViewBundle\EventListener\ContextListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class FragmentPathPassTest extends TestCase
{
    public function testTheFragmentPathParameterReplacesTheDefault(): void
    {
        $container = self::container('/_fragment');
        $container->setParameter('fragment.path', '/_frag');

        new FragmentPathPass()->process($container);

        self::assertSame('%fragment.path%', $container->getDefinition('device_view.context_listener')->getArgument(0));
    }

    public function testWithoutTheParameterTheDefaultStays(): void
    {
        $container = self::container('/_fragment');

        new FragmentPathPass()->process($container);

        self::assertSame('/_fragment', $container->getDefinition('device_view.context_listener')->getArgument(0));
    }

    public function testFragmentsThatAreNotSkippedStayNotSkipped(): void
    {
        $container = self::container(null);
        $container->setParameter('fragment.path', '/_frag');

        new FragmentPathPass()->process($container);

        self::assertNull($container->getDefinition('device_view.context_listener')->getArgument(0));
    }

    public function testAContainerWithoutTheListenerPasses(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('fragment.path', '/_frag');

        new FragmentPathPass()->process($container);

        self::assertFalse($container->hasDefinition('device_view.context_listener'));
    }

    private static function container(?string $fragmentPath): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('device_view.context_listener', new Definition(ContextListener::class, [$fragmentPath, []]));

        return $container;
    }
}
