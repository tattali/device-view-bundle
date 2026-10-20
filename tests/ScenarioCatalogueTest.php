<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests;

use DeviceViewBundle\Tests\Fixtures\ScenarioCitation\CitingClass;
use DeviceViewBundle\Tests\Fixtures\ScenarioCitation\MalformedCitingClass;
use DeviceViewBundle\Tests\Fixtures\ScenarioCitation\ThreeDigitCitingClass;
use DeviceViewBundle\Tests\Scenario\CitedScenarios;
use DeviceViewBundle\Tests\Scenario\ScenarioCatalogue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps docs/scenarios.md and the tests in step: every active scenario is cited by a test,
 * and every cited scenario exists.
 *
 * A test cites a scenario with a PHPUnit group named after its ID: #[Group('SW-03')].
 */
final class ScenarioCatalogueTest extends TestCase
{
    /**
     * The line this branch implements, as written in the Lines notes of docs/scenarios.md.
     */
    private const string LINE = 'main';

    public function testEveryActiveScenarioIsCitedByATest(): void
    {
        $catalogue = ScenarioCatalogue::fromFile(self::cataloguePath());
        $cited = CitedScenarios::inDirectory(__DIR__, __NAMESPACE__);

        $uncited = array_values(array_diff($catalogue->activeIdsFor(self::LINE), array_keys($cited)));

        self::assertSame([], $uncited, 'Active scenarios that no test cites. Cite each one with #[Group(\'<ID>\')] or set it back to planned.');
    }

    public function testEveryCitedScenarioIsInTheCatalogue(): void
    {
        $catalogue = ScenarioCatalogue::fromFile(self::cataloguePath());
        $cited = CitedScenarios::inDirectory(__DIR__, __NAMESPACE__);

        $unknown = array_values(array_diff(array_keys($cited), $catalogue->idsFor(self::LINE)));

        self::assertSame([], $unknown, 'Tests cite scenario IDs that docs/scenarios.md does not define, or that do not apply to this line.');
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
            | CFG-100 | config | planned |

            | Not an ID | text | active |
            MD);

        self::assertSame(['SW-01', 'CON-TAC-02', 'RED-10', 'CFG-100'], $catalogue->ids());
        self::assertSame(['CON-TAC-02', 'RED-10'], $catalogue->activeIdsFor('main'));
    }

    public function testALinesNoteLimitsAScenarioToSomeLines(): void
    {
        $catalogue = ScenarioCatalogue::parse(<<<'MD'
            | ID | Scenario | Status |
            |---|---|---|
            | SW-01 | every line | active |
            | DET-MD-06 | from 6.x on | active (6.x+) |
            | CFG-13 | from 6.x on, not done | planned (6.x+) |
            | REG-01 | PHP 7 lines only | active (3.x-5.x) |
            | REG-02 | one line | active (main) |
            MD);

        self::assertSame(['SW-01', 'DET-MD-06', 'CFG-13', 'REG-01', 'REG-02'], $catalogue->ids());
        self::assertSame(['SW-01', 'DET-MD-06', 'CFG-13', 'REG-02'], $catalogue->idsFor('main'));
        self::assertSame(['SW-01', 'DET-MD-06', 'REG-02'], $catalogue->activeIdsFor('main'));
        self::assertSame(['SW-01', 'DET-MD-06', 'CFG-13'], $catalogue->idsFor('6.x'));
        self::assertSame(['SW-01', 'REG-01'], $catalogue->idsFor('5.x'));
        self::assertSame(['SW-01', 'REG-01'], $catalogue->activeIdsFor('3.x'));
        self::assertSame(['SW-01', 'REG-01'], $catalogue->idsFor('4.x'));
    }

    public function testAnUnknownLineInALinesNoteIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Scenario DET-MD-06 on line 1 names an unknown line "2.x". Known lines: 3.x, 4.x, 5.x, 6.x, 7.x, main.');

        ScenarioCatalogue::parse("| DET-MD-06 | cache | planned (2.x+) |\n");
    }

    public function testAnEmptyLineRangeIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Scenario REG-01 on line 1 has an empty line range "6.x-5.x".');

        ScenarioCatalogue::parse("| REG-01 | x | planned (6.x-5.x) |\n");
    }

    public function testAnUnknownLineIsRejectedWhenAsked(): void
    {
        $catalogue = ScenarioCatalogue::parse("| SW-01 | x | planned |\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown line "8.x". Known lines: 3.x, 4.x, 5.x, 6.x, 7.x, main.');

        $catalogue->idsFor('8.x');
    }

    public function testAnIdShapedRowWithAMalformedNumberIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Scenario row on line 2 has the malformed ID "SW-1": the number has at least two digits, and only numbers below 10 start with 0.');

        ScenarioCatalogue::parse("| SW-01 | a | planned |\n| SW-1 | b | planned |\n");
    }

    public function testAThreeDigitIdWithALeadingZeroIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('malformed ID "CFG-001"');

        ScenarioCatalogue::parse("| CFG-001 | a | planned |\n");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNearlyIdFirstCells(): iterable
    {
        yield 'lower-case letters' => ['Sw-03'];
        yield 'a suffix after the number' => ['SW-03a'];
        yield 'bold' => ['**SW-03**'];
        yield 'code' => ['`SW-03`'];
        yield 'empty' => [''];
    }

    #[DataProvider('provideNearlyIdFirstCells')]
    public function testARowOfAScenarioTableMustStartWithAnId(string $firstCell): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(\sprintf('Row on line 4 of a scenario table has the first cell "%s", which is not a scenario ID.', $firstCell));

        ScenarioCatalogue::parse(<<<MD
            | ID | Scenario | Status |
            |---|---|---|
            | SW-01 | a | planned |
            | {$firstCell} | b | planned |
            MD);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIdHeaderCells(): iterable
    {
        yield 'upper case' => ['ID'];
        yield 'title case' => ['Id'];
        yield 'lower case' => ['id'];
        yield 'plural' => ['IDs'];
        yield 'longer word' => ['Identifier'];
    }

    #[DataProvider('provideIdHeaderCells')]
    public function testAHeaderCellStartingWithIdInAnyCaseMakesAScenarioTable(string $headerCell): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Row on line 4 of a scenario table has the first cell "Sw-03", which is not a scenario ID.');

        ScenarioCatalogue::parse(<<<MD
            | {$headerCell} | Scenario | Status |
            |---|---|---|
            | SW-01 | a | planned |
            | Sw-03 | b | planned |
            MD);
    }

    public function testOnlyTheHeaderRowOfATableCanMakeItAScenarioTable(): void
    {
        $catalogue = ScenarioCatalogue::parse(<<<'MD'
            | Name | Value |
            |---|---|
            | ID | a data row, not a header |
            | idle | another data row |
            | Sw-02 | text in a table that is not a scenario table | planned |

            | ID | Scenario | Status |
            |---|---|---|
            | SW-01 | a | planned |
            MD);

        self::assertSame(['SW-01'], $catalogue->ids());
    }

    public function testRowsOfOtherTablesAreNotScenarios(): void
    {
        $catalogue = ScenarioCatalogue::parse(<<<'MD'
            | Route | Path | Behaviour |
            |---|---|---|
            | `home` | `/` | Body |

            | ID | Scenario | Status |
            | :-- | --- | --: |
            | SW-01 | a | planned |

            | Sw-02 | text after the table ended | planned |
            MD);

        self::assertSame(['SW-01'], $catalogue->ids());
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

    public function testAThreeDigitCitationIsRead(): void
    {
        self::assertSame(['CFG-100' => [ThreeDigitCitingClass::class.'::testOne']], CitedScenarios::inClasses([ThreeDigitCitingClass::class]));
    }

    public function testAnIdShapedCitationWithAMalformedNumberIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(MalformedCitingClass::class.'::testOne cites the malformed scenario ID "SW-1": the number has at least two digits, and only numbers below 10 start with 0.');

        CitedScenarios::inClasses([MalformedCitingClass::class]);
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
