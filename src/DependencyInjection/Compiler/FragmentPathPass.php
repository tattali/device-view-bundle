<?php

declare(strict_types=1);

namespace DeviceViewBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives the context listener the fragment path it skips when skip.fragments is true: the
 * fragment.path parameter, which FrameworkBundle sets from framework.fragments.path, or the
 * default /_fragment that the extension set without it.
 *
 * The parameter of another bundle is known only once every extension is loaded, so the
 * extension cannot read it. The extension sets the argument to null when skip.fragments is false.
 */
final class FragmentPathPass implements CompilerPassInterface
{
    public const string LISTENER = 'device_view.context_listener';

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::LISTENER) || !$container->hasParameter('fragment.path')) {
            return;
        }

        $listener = $container->getDefinition(self::LISTENER);
        if (null !== $listener->getArgument(0)) {
            $listener->replaceArgument(0, '%fragment.path%');
        }
    }
}
