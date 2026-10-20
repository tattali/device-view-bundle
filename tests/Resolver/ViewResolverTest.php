<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Resolver;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\Step\StepResult;
use DeviceViewBundle\Resolver\ViewResolver;
use DeviceViewBundle\Tests\App\Resolver\FakeStep;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ViewResolverTest extends TestCase
{
    public function testWithoutAnyAnswerTheDefaultViewApplies(): void
    {
        $resolved = new ViewResolver([], DeviceType::Tablet)->resolve(Request::create('/'), new DeviceViewContext());

        self::assertSame(DeviceType::Tablet, $resolved->view);
        self::assertSame(ViewSource::Default, $resolved->source);
        self::assertSame([], $resolved->consultedInputs);
    }

    public function testTheDefaultViewIsDesktopByDefault(): void
    {
        self::assertSame(DeviceType::Desktop, new ViewResolver([])->resolve(Request::create('/'), new DeviceViewContext())->view);
    }

    public function testTheFirstStepWithAnAnswerDecidesAndTheNextOnesAreNotAsked(): void
    {
        $request = Request::create('/');
        $context = new DeviceViewContext();
        $silent = new FakeStep(ViewSource::Remembered, new StepResult(null, ['Cookie']));
        $deciding = new FakeStep(ViewSource::Detector, new StepResult(DeviceType::Mobile, ['User-Agent']));
        $after = new FakeStep(ViewSource::Forced, new StepResult(DeviceType::Tablet, ['X-After']));

        $resolved = new ViewResolver([$silent, $deciding, $after])->resolve($request, $context);

        self::assertSame(DeviceType::Mobile, $resolved->view);
        self::assertSame(ViewSource::Detector, $resolved->source);
        self::assertSame(['Cookie', 'User-Agent'], $resolved->consultedInputs);
        self::assertSame([[$request, $context]], $silent->calls);
        self::assertSame([[$request, $context]], $deciding->calls);
        self::assertSame([], $after->calls);
    }

    public function testTheInputsOfStepsWithoutAnswerAreKeptForTheDefault(): void
    {
        $resolved = new ViewResolver([
            new FakeStep(ViewSource::Remembered, new StepResult(null, ['Cookie', 'Cookie'])),
            new FakeStep(ViewSource::Detector, new StepResult(null, ['User-Agent', 'cookie', 'Cookie'])),
        ])->resolve(Request::create('/'), new DeviceViewContext());

        self::assertSame(DeviceType::Desktop, $resolved->view);
        self::assertSame(ViewSource::Default, $resolved->source);
        self::assertSame(['Cookie', 'User-Agent', 'cookie'], $resolved->consultedInputs);
    }

    /**
     * @return iterable<string, array{DeviceType|null, DeviceType, DeviceType, ViewSource}>
     */
    public static function tabletAsMobile(): iterable
    {
        yield 'a tablet answer' => [DeviceType::Tablet, DeviceType::Desktop, DeviceType::Mobile, ViewSource::Detector];
        yield 'a mobile answer' => [DeviceType::Mobile, DeviceType::Desktop, DeviceType::Mobile, ViewSource::Detector];
        yield 'a desktop answer' => [DeviceType::Desktop, DeviceType::Tablet, DeviceType::Desktop, ViewSource::Detector];
        yield 'a tablet default view' => [null, DeviceType::Tablet, DeviceType::Mobile, ViewSource::Default];
        yield 'a desktop default view' => [null, DeviceType::Desktop, DeviceType::Desktop, ViewSource::Default];
    }

    #[DataProvider('tabletAsMobile')]
    public function testTabletAsMobileMapsTheTabletViewAndKeepsTheSource(?DeviceType $answer, DeviceType $default, DeviceType $expected, ViewSource $source): void
    {
        $resolved = new ViewResolver([new FakeStep(ViewSource::Detector, new StepResult($answer))], $default, true)
            ->resolve(Request::create('/'), new DeviceViewContext());

        self::assertSame($expected, $resolved->view);
        self::assertSame($source, $resolved->source);
    }

    public function testATabletStaysATabletWithoutTabletAsMobile(): void
    {
        $resolved = new ViewResolver([new FakeStep(ViewSource::Detector, new StepResult(DeviceType::Tablet))], DeviceType::Desktop, false)
            ->resolve(Request::create('/'), new DeviceViewContext());

        self::assertSame(DeviceType::Tablet, $resolved->view);
    }

    public function testTheStepsCanBeAnyIterable(): void
    {
        $steps = (static function (): \Generator {
            yield new FakeStep(ViewSource::Detector, new StepResult(DeviceType::Tablet));
        })();

        self::assertSame(DeviceType::Tablet, new ViewResolver($steps)->resolve(Request::create('/'), new DeviceViewContext())->view);
    }
}
