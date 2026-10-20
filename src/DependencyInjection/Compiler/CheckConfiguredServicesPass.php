<?php

declare(strict_types=1);

namespace DeviceViewBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks that every service id set in the bundle configuration exists and implements the
 * interface its option expects.
 *
 * The extension lists the checks in the PARAMETER build parameter: one [service id, option
 * path, interface] entry per option that names a service.
 */
final class CheckConfiguredServicesPass implements CompilerPassInterface
{
    public const string PARAMETER = '.device_view.configured_services';

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        /** @var list<array{string, string, string}> $checks */
        $checks = $container->getParameter(self::PARAMETER);
        foreach ($checks as [$id, $path, $interface]) {
            if (!$container->has($id)) {
                throw new InvalidConfigurationException(\sprintf('The service "%s" set in %s does not exist.', $id, $path));
            }

            $class = self::classOf($container, $id);
            if (null === $class || !is_a($class, $interface, true)) {
                throw new InvalidConfigurationException(\sprintf('The service "%s" set in %s must implement %s.', $id, $path, $interface));
            }
        }
    }

    private static function classOf(ContainerBuilder $container, string $id): ?string
    {
        $definition = $container->findDefinition($id);
        $class = $definition->getClass();
        while (null === $class && $definition instanceof ChildDefinition) {
            $definition = $container->findDefinition($definition->getParent());
            $class = $definition->getClass();
        }

        $class = $container->getParameterBag()->resolveValue($class);

        return \is_string($class) ? $class : null;
    }
}
