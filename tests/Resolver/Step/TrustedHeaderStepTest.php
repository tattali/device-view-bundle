<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\Step\TrustedHeaderStep;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\Tests\App\TrustedProxies;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

final class TrustedHeaderStepTest extends TestCase
{
    private const string INVALID = 'Ignored an invalid device view from the trusted header.';

    private const string UNTRUSTED = '203.0.113.9';

    /**
     * The trusted proxies and headers of Request before the test.
     *
     * @var array{proxies: array<string>, headers: int}|null
     */
    private ?array $trustedProxies = null;

    #[\Override]
    protected function setUp(): void
    {
        $this->trustedProxies = TrustedProxies::save();
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (null !== $this->trustedProxies) {
            TrustedProxies::restore($this->trustedProxies);
            $this->trustedProxies = null;
        }
    }

    public function testItsSourceIsTheTrustedHeader(): void
    {
        self::assertSame(ViewSource::TrustedHeader, new TrustedHeaderStep('X-Device-View', true, new ViewNames())->source());
    }

    public function testTheHeaderOfATrustedProxyDecides(): void
    {
        $logger = new TestLogger();
        $context = new DeviceViewContext();

        $result = new TrustedHeaderStep('X-Device-View', true, new ViewNames(), $logger)->find(self::request('tablet'), $context);

        self::assertSame(DeviceType::Tablet, $result->view);
        self::assertSame(['X-Device-View'], $result->consultedInputs);
        self::assertSame([], $logger->records);
        self::assertFalse($context->forgedStoredValue);
    }

    public function testTheConsultedInputIsTheConfiguredName(): void
    {
        $result = new TrustedHeaderStep('x-dv', true, new ViewNames())->find(self::request('mobile', header: 'X-DV'), new DeviceViewContext());

        self::assertSame(DeviceType::Mobile, $result->view);
        self::assertSame(['x-dv'], $result->consultedInputs);
    }

    public function testTheValueIsTrimmedAndLowerCased(): void
    {
        $result = new TrustedHeaderStep('X-Device-View', true, new ViewNames())->find(self::request(' Tablet '), new DeviceViewContext());

        self::assertSame(DeviceType::Tablet, $result->view);
    }

    public function testTheValueIsAConfiguredName(): void
    {
        $step = new TrustedHeaderStep('X-Device-View', true, new ViewNames(['tablet' => 't']));

        self::assertSame(DeviceType::Tablet, $step->find(self::request('t'), new DeviceViewContext())->view);
        self::assertNull($step->find(self::request('tablet'), new DeviceViewContext())->view);
    }

    public function testAnAbsentHeaderIsConsultedWithoutALog(): void
    {
        $logger = new TestLogger();

        $result = new TrustedHeaderStep('X-Device-View', true, new ViewNames(), $logger)->find(self::request(null), new DeviceViewContext());

        self::assertNull($result->view);
        self::assertSame(['X-Device-View'], $result->consultedInputs);
        self::assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'unknown word' => ['phone'];
        yield 'empty' => [''];
        yield 'not_mobile' => ['not_mobile'];
    }

    #[DataProvider('invalidValues')]
    public function testAnInvalidValueIsConsultedAndLogged(string $value): void
    {
        $logger = new TestLogger();
        $context = new DeviceViewContext();

        $result = new TrustedHeaderStep('X-Device-View', true, new ViewNames(), $logger)->find(self::request($value), $context);

        self::assertNull($result->view);
        self::assertSame(['X-Device-View'], $result->consultedInputs);
        self::assertSame([self::INVALID], $logger->messages(LogLevel::DEBUG));
        self::assertCount(1, $logger->records);
        self::assertFalse($context->forgedStoredValue);
    }

    public function testAnUntrustedClientIsNotRead(): void
    {
        $logger = new TestLogger();

        $result = new TrustedHeaderStep('X-Device-View', true, new ViewNames(), $logger)->find(self::request('phone', self::UNTRUSTED), new DeviceViewContext());

        self::assertNull($result->view);
        self::assertSame([], $result->consultedInputs);
        self::assertSame([], $logger->records);
    }

    public function testNoClientIsTrustedWithoutTrustedProxies(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

        $result = new TrustedHeaderStep('X-Device-View', true, new ViewNames())->find(self::request('tablet'), new DeviceViewContext());

        self::assertNull($result->view);
        self::assertSame([], $result->consultedInputs);
    }

    public function testAnyClientIsReadWhenTrustedProxiesAreNotRequired(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

        $result = new TrustedHeaderStep('X-Device-View', false, new ViewNames())->find(self::request('tablet', self::UNTRUSTED), new DeviceViewContext());

        self::assertSame(DeviceType::Tablet, $result->view);
        self::assertSame(['X-Device-View'], $result->consultedInputs);
    }

    public function testWithoutALogger(): void
    {
        self::assertNull(new TrustedHeaderStep('X-Device-View', true, new ViewNames())->find(self::request('phone'), new DeviceViewContext())->view);
    }

    private static function request(?string $value, string $remoteAddress = '127.0.0.1', string $header = 'X-Device-View'): Request
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => $remoteAddress]);
        if (null !== $value) {
            $request->headers->set($header, $value);
        }

        return $request;
    }
}
