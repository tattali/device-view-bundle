<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests;

use DeviceViewBundle\Tests\Fixtures\ScenarioCitation\CitingClass;
use DeviceViewBundle\Tests\Scenario\CitedScenarios;
use DeviceViewBundle\Tests\Scenario\ScenarioCatalogue;
use PHPUnit\Framework\TestCase;

/**
 * Keeps docs/scenarios.md and the tests in step: every active scenario is cited by a test,
 * and every cited scenario exists.
 *
 * A test cites a scenario with a PHPUnit group named after its ID: #[Group('SW-03')].
 */
final class ScenarioCatalogueTest extends TestCase
{
    public function testEveryActiveScenarioIsCitedByATest(): void
    {
        $catalogue = ScenarioCatalogue::fromFile(self::cataloguePath());
        $cited = CitedScenarios::inDirectory(__DIR__, __NAMESPACE__);

        $uncited = array_values(array_diff($catalogue->activeIds(), array_keys($cited)));

        self::assertSame([], $uncited, 'Active scenarios that no test cites. Cite each one with #[Group(\'<ID>\')] or set it back to planned.');
    }

    public function testEveryCitedScenarioIsInTheCatalogue(): void
    {
        $catalogue = ScenarioCatalogue::fromFile(self::cataloguePath());
        $cited = CitedScenarios::inDirectory(__DIR__, __NAMESPACE__);

        $unknown = array_values(array_diff(array_keys($cited), $catalogue->ids()));

        self::assertSame([], $unknown, 'Tests cite scenario IDs that docs/scenarios.md does not define.');
    }

    public function testTheCatalogueReadsTheIdAndStatusOfEveryScenarioRow(): void
    {
        $catalogue = ScenarioCatalogue::parse(<<<'MD'
            # Title

            | ID | Scenario | Status |
            |---|---|---|
            | SW-01 | switch to `mobile\|tablet` | planned |
            | CON-TAC-02 | tarteaucitron | active |
            |  RED-10  | redirect |  active  |

            | Not an ID | text | active |
            MD);

        self::assertSame(['SW-01', 'CON-TAC-02', 'RED-10'], $catalogue->ids());
        self::assertSame(['CON-TAC-02', 'RED-10'], $catalogue->activeIds());
    }

    public function testARowWithoutAKnownStatusIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Scenario row on line 2 must end with a "planned" or "active" status cell: | SW-01 | switch | done |');

        ScenarioCatalogue::parse("| ID | Scenario | Status |\n| SW-01 | switch | done |\n");
    }

    public function testADuplicateIdIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Scenario SW-01 is defined twice (lines 1 and 3).');

        ScenarioCatalogue::parse("| SW-01 | a | planned |\n| SW-02 | b | planned |\n| SW-01 | c | active |\n");
    }

    public function testAnEmptyCatalogueIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The scenario catalogue defines no scenario.');

        ScenarioCatalogue::parse("# Nothing here\n");
    }

    public function testAMissingCatalogueFileIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot read the scenario catalogue /does/not/exist.md.');

        ScenarioCatalogue::fromFile('/does/not/exist.md');
    }

    public function testCitationsAreTheIdShapedGroupsOfAClassAndItsTestMethods(): void
    {
        self::assertSame(
            [
                'CFG-02' => [CitingClass::class],
                'SW-03' => [CitingClass::class.'::testOne', CitingClass::class.'::testTwo', CitingClass::class.'::itIsATestThroughItsAttribute'],
                'CON-TAC-04' => [CitingClass::class.'::testTwo'],
            ],
            CitedScenarios::inClasses([CitingClass::class]),
        );
    }

    public function testTheTestDirectoryIsSearchedForTestClasses(): void
    {
        $cited = CitedScenarios::inDirectory(__DIR__, __NAMESPACE__);

        self::assertArrayNotHasKey('SW-03', $cited, 'fixture classes are not test classes');
        self::assertContains(Functional\TestAppTest::class.'::testTheConfigurationRootIsDeviceView', $cited['CFG-01'] ?? []);
    }

    private static function cataloguePath(): string
    {
        return \dirname(__DIR__).'/docs/scenarios.md';
    }
}
