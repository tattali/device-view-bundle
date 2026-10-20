<?php

declare(strict_types=1);

namespace DeviceViewBundle;

use DeviceViewBundle\DependencyInjection\Compiler\CheckConfiguredServicesPass;
use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\Detector\MobileDetectDetector;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Serves the mobile, tablet or desktop view of a site.
 *
 * The configuration tree and the services live in config/, one file per concern.
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
        $container->import('../config/remember.php');

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
            $checks[] = [$id, 'device_view.detector.id', DeviceDetectorInterface::class];
        }

        $container->services()->alias('device_view.detector', $id);

        /** @var array{default_view: value-of<DeviceType>, tablet_as_mobile: bool, views: array{names: array<value-of<DeviceType>, string>}, switch: array{enabled: bool, parameter: non-empty-string, target: value-of<SwitchTarget>, status: int}, redirect: array{methods: non-empty-list<string>, cache_control: string|null}, remember: array{on_switch: bool, detected_view: bool, lifetime: int, consented_lifetime: int, cookie: array{name: string, path: string, domain: string|null, secure: 'auto'|bool, http_only: bool, same_site: 'lax'|'strict'|'none'|null, raw: bool}}, http_cache: array{private_when_remembering: bool}, services: array{resolver: string|null}} $config */
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
        $builder->getDefinition('device_view.remember_policy')->setArguments([
            $remember['on_switch'],
            $remember['detected_view'],
            $remember['lifetime'],
            $remember['consented_lifetime'],
        ]);
        $builder->getDefinition('device_view.remember_listener')->replaceArgument(4, $config['http_cache']['private_when_remembering']);

        $resolver = $config['services']['resolver'];
        if (null !== $resolver) {
            $checks[] = [$resolver, 'device_view.services.resolver', ViewResolverInterface::class];
        }
        $container->services()->alias('device_view.resolver', $resolver ?? 'device_view.resolver.default');

        $builder->setParameter(CheckConfiguredServicesPass::PARAMETER, $checks);
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

    private static function isInstalled(string $package, string $class): bool
    {
        return ContainerBuilder::willBeAvailable($package, $class, ['tattali/device-view-bundle']);
    }
}
