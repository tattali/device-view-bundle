<?php

declare(strict_types=1);

namespace DeviceViewBundle;

use DeviceViewBundle\Consent\ConsentCheckerInterface;
use DeviceViewBundle\DependencyInjection\Compiler\CheckConfiguredServicesPass;
use DeviceViewBundle\DependencyInjection\Compiler\CheckSessionStoragePass;
use DeviceViewBundle\DependencyInjection\Compiler\FragmentPathPass;
use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\Detector\MobileDetectDetector;
use DeviceViewBundle\Redirect\RedirectAction;
use DeviceViewBundle\Redirect\RedirectHost;
use DeviceViewBundle\Remember\ViewStorageInterface;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Serves the mobile, tablet or desktop view of a site.
 *
 * The configuration tree and the services live in config/, one file per concern.
 *
 * @phpstan-type RedirectOptions array{enabled: bool, host: string|null, action: value-of<RedirectAction>, status: int, keep_query: bool}
 */
final class DeviceViewBundle extends AbstractBundle
{
    protected string $extensionAlias = 'device_view';

    #[\Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CheckConfiguredServicesPass());
        $container->addCompilerPass(new CheckSessionStoragePass());
        $container->addCompilerPass(new FragmentPathPass());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
        $container->import('../config/resolver.php');
        $container->import('../config/switch.php');
        // before remember.php: the Vary listener runs before the remember listener
        $container->import('../config/http_cache.php');
        $container->import('../config/remember.php');
        $container->import('../config/redirect.php');
        $container->import('../config/route_defaults.php');
        // the built-in consent readers, one file each: see builtInConsentReader()
        $container->import('../config/consent/*.php');

        /** @var array{id: non-empty-string, mobile_detect: array{headers: non-empty-list<string>, cache: string|null}} $detector */
        $detector = $config['detector'];
        $checks = [];
        $id = 'auto' === $detector['id'] ? self::autoDetector() : $detector['id'];

        if ('mobile_detect' === $id) {
            if (!self::isInstalled('mobiledetect/mobiledetectlib', 'Detection\MobileDetect')) {
                throw new \LogicException('The "mobile_detect" detector needs mobiledetect/mobiledetectlib. Run "composer require mobiledetect/mobiledetectlib", or set device_view.detector.id to another detector.');
            }

            $cache = $detector['mobile_detect']['cache'];
            $container->services()
                ->set('device_view.detector.mobile_detect', MobileDetectDetector::class)
                ->args([$detector['mobile_detect']['headers'], null === $cache ? null : new Reference($cache)])
            ;
            if (null !== $cache) {
                $checks[] = [$cache, 'device_view.detector.mobile_detect.cache', 'Psr\SimpleCache\CacheInterface'];
            }
            $id = 'device_view.detector.mobile_detect';
        } else {
            self::assertNotTheAlias($id, 'device_view.detector', 'device_view.detector.id');
            $checks[] = [$id, 'device_view.detector.id', DeviceDetectorInterface::class];
        }

        $container->services()->alias('device_view.detector', $id);

        /** @var array{default_view: value-of<DeviceType>, tablet_as_mobile: bool, views: array{names: array<value-of<DeviceType>, string>}, switch: array{enabled: bool, parameter: non-empty-string, target: value-of<SwitchTarget>, status: int}, trusted_header: array{name: non-empty-string|null, trusted_proxies_only: bool}, redirect: array{methods: non-empty-list<string>, cache_control: string|null, mobile: RedirectOptions, tablet: RedirectOptions, desktop: RedirectOptions}, remember: array{storage: non-empty-string, on_switch: bool, detected_view: bool, lifetime: int, consented_lifetime: int, cookie: array{name: string, path: string, domain: string|null, secure: 'auto'|bool, http_only: bool, same_site: 'lax'|'strict'|'none'|null, raw: bool}, session: array{key: non-empty-string}}, consent: array{provider: non-empty-string|null, key: non-empty-string|null, cookie: non-empty-string|null}, route_defaults: array{redirect: non-empty-string, remember: non-empty-string}, http_cache: array{vary: bool, private_when_remembering: bool}, skip: array{fragments: bool, paths: list<string>}, services: array{resolver: string|null}} $config */
        $defaultView = DeviceType::from($config['default_view']);
        $tabletAsMobile = $config['tablet_as_mobile'];
        $builder->getDefinition('device_view.view_names')->replaceArgument(0, $config['views']['names']);
        $builder->getDefinition('device_view.resolver.default')
            ->replaceArgument(1, $defaultView)
            ->replaceArgument(2, $tabletAsMobile)
        ;
        $builder->getDefinition('device_view.device_view')
            ->replaceArgument(4, $tabletAsMobile && DeviceType::Tablet === $defaultView ? DeviceType::Mobile : $defaultView)
        ;

        // FragmentPathPass replaces /_fragment with framework.fragments.path when it is defined
        $builder->getDefinition(FragmentPathPass::LISTENER)
            ->replaceArgument(0, $config['skip']['fragments'] ? '/_fragment' : null)
            ->replaceArgument(1, $config['skip']['paths'])
        ;

        $trustedHeader = $config['trusted_header'];
        if (null === $trustedHeader['name']) {
            self::removeStep($builder, 'device_view.resolver.step.trusted_header');
        } else {
            $builder->getDefinition('device_view.resolver.step.trusted_header')
                ->replaceArgument(0, $trustedHeader['name'])
                ->replaceArgument(1, $trustedHeader['trusted_proxies_only'])
            ;
        }

        $switch = $config['switch'];
        $builder->getDefinition('device_view.switch_parameter')
            ->replaceArgument(1, $switch['parameter'])
            ->replaceArgument(2, $switch['enabled'])
            ->replaceArgument(3, $config['redirect']['methods'])
        ;
        $builder->getDefinition('device_view.switch_url_generator')->replaceArgument(0, $switch['parameter']);
        $builder->getDefinition('device_view.switch_listener')
            ->replaceArgument(2, SwitchTarget::from($switch['target']))
            ->replaceArgument(3, $switch['status'])
            ->replaceArgument(4, $config['redirect']['cache_control'])
            ->replaceArgument(11, $tabletAsMobile)
            ->replaceArgument(12, 'none' !== $config['remember']['storage'])
        ;

        $remember = $config['remember'];
        $cookie = $remember['cookie'];
        $builder->getDefinition('device_view.storage.cookie')->setArguments([
            $cookie['name'],
            $cookie['path'],
            $cookie['domain'],
            'auto' === $cookie['secure'] ? null : $cookie['secure'],
            $cookie['http_only'],
            $cookie['same_site'],
            $cookie['raw'],
        ]);
        $builder->getDefinition('device_view.storage.session')->replaceArgument(0, $remember['session']['key']);
        $storage = $remember['storage'];
        if (\in_array($storage, ['cookie', 'session', 'none'], true)) {
            $storage = 'device_view.storage.'.$storage;
        } else {
            self::assertNotTheAlias($storage, 'device_view.storage', 'device_view.remember.storage');
            $checks[] = [$storage, 'device_view.remember.storage', ViewStorageInterface::class];
        }
        $container->services()->alias('device_view.storage', $storage);
        $builder->getDefinition('device_view.redirect_policy')
            ->replaceArgument(0, self::redirects($builder, $config['redirect']))
            ->replaceArgument(3, 'cookie' === $remember['storage'] && null === $cookie['domain'] ? $cookie['name'] : null)
        ;
        $builder->getDefinition('device_view.route_defaults')
            ->replaceArgument(0, $config['route_defaults']['redirect'])
            ->replaceArgument(1, $config['route_defaults']['remember'])
        ;
        $builder->getDefinition('device_view.device_redirect_listener')
            ->replaceArgument(2, $config['redirect']['methods'])
            ->replaceArgument(3, $config['redirect']['cache_control'])
        ;
        $builder->setParameter(CheckSessionStoragePass::PARAMETER, 'session' === $remember['storage']);
        $consent = $config['consent'];
        $builder->getDefinition('device_view.remember_policy')->setArguments([
            $remember['on_switch'],
            $remember['detected_view'],
            $remember['lifetime'],
            $remember['consented_lifetime'],
            null !== $consent['provider'],
        ]);
        $builder->getDefinition('device_view.remember_listener')
            // nothing is remembered nor expired without a storage
            ->replaceArgument(1, 'none' === $remember['storage'] ? null : new Reference('device_view.storage'))
            ->replaceArgument(4, $config['http_cache']['private_when_remembering'])
            ->replaceArgument(5, $tabletAsMobile)
        ;
        if (!$config['http_cache']['vary']) {
            $builder->removeDefinition('device_view.vary_listener');
        }

        $provider = $consent['provider'];
        if (null !== $provider) {
            $checker = self::builtInConsentReader($builder, $provider, $consent['cookie'], $consent['key']);
            if (null === $checker) {
                self::assertNotTheAlias($provider, 'device_view.consent_checker', 'device_view.consent.provider');
                $checks[] = [$provider, 'device_view.consent.provider', ConsentCheckerInterface::class];
            }
            // without a provider there is no checker, and the remember listener gets null
            $container->services()
                ->alias('device_view.consent_checker', $checker ?? $provider)
                ->alias(ConsentCheckerInterface::class, 'device_view.consent_checker')
            ;
        }

        $resolver = $config['services']['resolver'];
        if (null !== $resolver) {
            self::assertNotTheAlias($resolver, 'device_view.resolver', 'device_view.services.resolver');
            $checks[] = [$resolver, 'device_view.services.resolver', ViewResolverInterface::class];
        }
        $container->services()->alias('device_view.resolver', $resolver ?? 'device_view.resolver.default');

        $builder->setParameter(CheckConfiguredServicesPass::PARAMETER, $checks);
    }

    /**
     * The options of the enabled redirects, by view, for the redirect policy.
     *
     * A host is checked here, unless it holds an env var: the redirect policy checks it at runtime.
     *
     * @param array{mobile: RedirectOptions, tablet: RedirectOptions, desktop: RedirectOptions, ...} $redirect the redirect options
     *
     * @return array<value-of<DeviceType>, array{host: string, action: RedirectAction, status: int, keep_query: bool}>
     */
    private static function redirects(ContainerBuilder $builder, array $redirect): array
    {
        $redirects = [];
        foreach (DeviceType::cases() as $view) {
            $options = $redirect[$view->value];
            $host = $options['host'];
            if (!$options['enabled'] || null === $host) {
                continue;
            }

            $envs = [];
            $builder->resolveEnvPlaceholders($host, null, $envs);
            if ([] === $envs && null === RedirectHost::parse($host)) {
                throw new InvalidConfigurationException(\sprintf('device_view.redirect.%s.host "%s" must be an absolute http(s) URL with a host, an optional port and an optional path, and no query, fragment or user info.', $view->value, $host));
            }

            $redirects[$view->value] = [
                'host' => $host,
                'action' => RedirectAction::from($options['action']),
                'status' => $options['status'],
                'keep_query' => $options['keep_query'],
            ];
        }

        return $redirects;
    }

    /**
     * Removes a step from the resolution order of the built-in resolver, and its service.
     */
    private static function removeStep(ContainerBuilder $builder, string $id): void
    {
        $resolver = $builder->getDefinition('device_view.resolver.default');
        /** @var list<Reference> $steps */
        $steps = $resolver->getArgument(0);
        $resolver->replaceArgument(0, array_values(array_filter($steps, static fn (Reference $step): bool => $id !== (string) $step)));
        $builder->removeDefinition($id);
    }

    /**
     * The detector that detector.id: auto stands for.
     */
    private static function autoDetector(): string
    {
        if (self::isInstalled('mobiledetect/mobiledetectlib', 'Detection\MobileDetect')) {
            return 'mobile_detect';
        }

        throw new \LogicException('No device detector is installed. Run `composer require mobiledetect/mobiledetectlib` (or `matomo/device-detector`), or set `device_view.detector.id` to `client_hints` or to your own service id.');
    }

    /**
     * The service of the built-in reader that consent.provider names, set up with consent.cookie
     * and consent.key, or null when the provider is not the name of a built-in reader.
     *
     * The built-in readers form a registry with no shared list, so readers are added
     * independently. To add the reader of a consent tool named <name>:
     * 1. write its class in src/Consent: it implements ConsentCheckerInterface, never throws, and
     *    its constructor takes ?string $cookie and ?string $key, null standing for the tool's
     *    default cookie and key;
     * 2. register it as the service device_view.consent.<name> in config/consent/<name>.php,
     *    which loadExtension() imports with every file of that directory;
     * 3. give it its own test files and fixtures (tests/Fixtures/consent/<name>/<version>), and
     *    activate its rows in docs/scenarios.md.
     */
    private static function builtInConsentReader(ContainerBuilder $builder, string $provider, ?string $cookie, ?string $key): ?string
    {
        $id = 'device_view.consent.'.$provider;
        if (!$builder->hasDefinition($id)) {
            return null;
        }

        $builder->getDefinition($id)->setArgument('$cookie', $cookie)->setArgument('$key', $key);

        return $id;
    }

    /**
     * A service id set in the configuration cannot be the alias the bundle points to it.
     */
    private static function assertNotTheAlias(string $id, string $alias, string $path): void
    {
        if ($alias === $id) {
            throw new InvalidConfigurationException(\sprintf('%s cannot be "%s", the alias it sets: set it to the id of your own service.', $path, $alias));
        }
    }

    private static function isInstalled(string $package, string $class): bool
    {
        return ContainerBuilder::willBeAvailable($package, $class, ['tattali/device-view-bundle']);
    }
}
