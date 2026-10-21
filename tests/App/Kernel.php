<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use DeviceViewBundle\Consent\ConsentCheckerInterface;
use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\DeviceView;
use DeviceViewBundle\DeviceViewBundle;
use DeviceViewBundle\Remember\ViewStorageInterface;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The application of the functional tests: FrameworkBundle, this bundle and the routes of
 * config/routes.php.
 *
 * Each bundle configuration gets its own cache directory and container class, so one test
 * run can boot the kernel with several configurations.
 *
 * Tests read the bundle's private services through the public aliases of TEST_ALIASES, and of
 * OPTIONAL_TEST_ALIASES when the configuration defines the service.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Public aliases of the bundle's services, keyed by the public id tests ask for.
     *
     * @var array<string, string>
     */
    public const array TEST_ALIASES = [
        'test.device_view.detector' => 'device_view.detector',
        'test.device_detector_interface' => DeviceDetectorInterface::class,
        'test.device_view.resolver' => 'device_view.resolver',
        'test.view_resolver_interface' => ViewResolverInterface::class,
        'test.device_view.device_view' => 'device_view.device_view',
        'test.device_view' => DeviceView::class,
        'test.device_view.storage' => 'device_view.storage',
        'test.view_storage_interface' => ViewStorageInterface::class,
    ];

    /**
     * Public aliases of the bundle's services that only some configurations define.
     *
     * @var array<string, string>
     */
    public const array OPTIONAL_TEST_ALIASES = [
        'test.device_view.consent_checker' => 'device_view.consent_checker',
        'test.consent_checker_interface' => ConsentCheckerInterface::class,
    ];

    /**
     * Every Request::HEADER_* bit.
     */
    private const int TRUSTED_HEADERS = Request::HEADER_FORWARDED | Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PREFIX;

    private static ?string $runDir = null;

    private readonly string $configHash;

    /** @var callable|null */
    private $exceptionHandlerBeforeBoot;

    /**
     * SHELL_VERBOSITY before boot, in $_ENV, $_SERVER and getenv(); null when it was not set.
     *
     * @var array{env: mixed, server: mixed, getenv: string|false}|null
     */
    private ?array $shellVerbosityBeforeBoot = null;

    /**
     * The trusted proxies and headers of Request before boot, which framework.trusted_proxies
     * sets for the whole process; null when not booted.
     *
     * @var array{proxies: array<string>, headers: int}|null
     */
    private ?array $trustedProxiesBeforeBoot = null;

    /**
     * @param array<string, mixed>                       $deviceViewConfig the device_view configuration
     * @param array<string, class-string>                $services         public services of the application, by id
     * @param array<string, mixed>                       $framework        framework configuration added to the baseline, e.g. a session
     * @param array<string, array{class-string, string}> $decorators       public services of the application decorating a service, by id: [class, id of the decorated service]; the class takes the decorated service as its only argument
     */
    public function __construct(
        private readonly array $deviceViewConfig = [],
        string $environment = 'test',
        bool $debug = false,
        private readonly array $services = [],
        private readonly array $framework = [],
        private readonly array $decorators = [],
    ) {
        parent::__construct($environment, $debug);
        $this->configHash = hash('xxh128', serialize([$deviceViewConfig, $environment, $debug, $services, $framework, $decorators]));
    }

    #[\Override]
    public function boot(): void
    {
        if (!$this->booted) {
            $this->exceptionHandlerBeforeBoot = self::currentExceptionHandler();
            $this->shellVerbosityBeforeBoot = [
                'env' => $_ENV['SHELL_VERBOSITY'] ?? null,
                'server' => $_SERVER['SHELL_VERBOSITY'] ?? null,
                'getenv' => getenv('SHELL_VERBOSITY'),
            ];
            $this->trustedProxiesBeforeBoot = ['proxies' => Request::getTrustedProxies(), 'headers' => Request::getTrustedHeaderSet()];
        }

        parent::boot();
    }

    /**
     * Also removes the exception handlers that booting added, and restores SHELL_VERBOSITY.
     *
     * On Symfony 8.0, FrameworkBundle::boot() registers the ErrorHandler and nothing removes
     * it, so PHPUnit marks the test risky. A debug kernel sets SHELL_VERBOSITY to 3 for the
     * whole process, so every kernel booted after it would print its info logs.
     * framework.trusted_proxies sets the trusted proxies of every later Request too.
     */
    #[\Override]
    public function shutdown(): void
    {
        parent::shutdown();

        while (null !== ($handler = self::currentExceptionHandler()) && $handler !== $this->exceptionHandlerBeforeBoot) {
            restore_exception_handler();
        }

        if (null !== $before = $this->shellVerbosityBeforeBoot) {
            self::restore($_ENV, $before['env']);
            self::restore($_SERVER, $before['server']);
            putenv(false === $before['getenv'] ? 'SHELL_VERBOSITY' : 'SHELL_VERBOSITY='.$before['getenv']);
            $this->shellVerbosityBeforeBoot = null;
        }

        if (null !== $before = $this->trustedProxiesBeforeBoot) {
            Request::setTrustedProxies($before['proxies'], $before['headers'] & self::TRUSTED_HEADERS);
            $this->trustedProxiesBeforeBoot = null;
        }
    }

    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DeviceViewBundle();
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return self::runDir().'/'.$this->configHash.'/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return self::runDir().'/'.$this->configHash.'/log';
    }

    /**
     * FrameworkBundle writes reference.php into the config directory when it builds the
     * container. The test app points it to a directory that does not exist, so nothing is
     * written into the repository.
     *
     * @return array<array-key, mixed>
     */
    #[\Override]
    protected function getKernelParameters(): array
    {
        return ['.kernel.config_dir' => self::runDir().'/'.$this->configHash.'/config'] + parent::getKernelParameters();
    }

    #[\Override]
    protected function getContainerClass(): string
    {
        return parent::getContainerClass().'_'.$this->configHash;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        // the secrets vault is not used: without it, each container is smaller, and a test run
        // keeps one container per configuration in memory
        $container->extension('framework', [
            'secret' => 'device-view-bundle-tests',
            'test' => true,
            'secrets' => false,
        ] + $this->framework);
        $container->extension('device_view', $this->deviceViewConfig);

        $services = $container->services();
        $services
            ->set(TestController::class)
            ->args([new Reference(DeviceView::class), new Reference('http_kernel')])
            ->tag('controller.service_arguments')
        ;
        $services
            ->set(ControllerCall::class)
            ->public()
            ->args([new Reference(DeviceView::class)])
            ->tag('kernel.event_listener', ['event' => KernelEvents::CONTROLLER, 'method' => 'onKernelController'])
        ;
        $services
            ->set(ResponseCall::class)
            ->public()
            ->args([new Reference(DeviceView::class)])
            ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse', 'priority' => -10])
        ;
        $services
            ->set(RequestRecorder::class)
            ->public()
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => -1000])
        ;
        $services
            ->set(ResponseRecorder::class)
            ->public()
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => -1000])
            ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse', 'priority' => -1000])
        ;
        foreach ($this->services as $id => $class) {
            $services->set($id, $class)->public();
        }
        foreach ($this->decorators as $id => [$class, $decorated]) {
            $services->set($id, $class)->public()->decorate($decorated)->args([new Reference('.inner')]);
        }
        foreach (self::TEST_ALIASES as $alias => $id) {
            $services->alias($alias, $id)->public();
        }
    }

    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new OptionalAliasesPass(self::OPTIONAL_TEST_ALIASES));
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/config/routes.php');
    }

    /**
     * @param array<array-key, mixed> $variables
     */
    private static function restore(array &$variables, mixed $value): void
    {
        if (null === $value) {
            unset($variables['SHELL_VERBOSITY']);
        } else {
            $variables['SHELL_VERBOSITY'] = $value;
        }
    }

    private static function currentExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    /**
     * The cache root of this PHP process, removed when the process ends.
     *
     * Containers are never shared between processes: Infection runs each mutant in its own
     * process, and a container compiled from the unmutated code would hide the mutant.
     */
    private static function runDir(): string
    {
        if (null === self::$runDir) {
            $runDir = sys_get_temp_dir().'/device-view-bundle-tests/'.bin2hex(random_bytes(8));
            register_shutdown_function(static function () use ($runDir): void {
                new Filesystem()->remove($runDir);
            });
            self::$runDir = $runDir;
        }

        return self::$runDir;
    }
}
