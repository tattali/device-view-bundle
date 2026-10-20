<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds a public alias of each service the configuration defines, so tests can read it, and
 * nothing for the others.
 */
final readonly class OptionalAliasesPass implements CompilerPassInterface
{
    /**
     * @param array<string, string> $aliases the ids of the services, by public alias
     */
    public function __construct(
        private array $aliases,
    ) {
    }

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        foreach ($this->aliases as $alias => $id) {
            if ($container->has($id)) {
                $container->setAlias($alias, $id)->setPublic(true);
            }
        }
    }
}
