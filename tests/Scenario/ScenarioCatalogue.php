<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Scenario;

/**
 * The scenarios of docs/scenarios.md: their IDs, statuses and the lines they apply to.
 *
 * A scenario is a Markdown table row whose first cell is an ID (an area prefix, an optional
 * sub-area and a number, e.g. SW-03, CON-TAC-02 or CFG-100) and whose last cell is its status,
 * "planned" or "active", optionally followed by a Lines note in parentheses: "(6.x+)" for a
 * line and the lines after it, "(3.x-5.x)" for a range, "(main)" for one line. A row without a
 * Lines note applies to every line. Other rows and lines are ignored.
 */
final readonly class ScenarioCatalogue
{
    public const string ID_PATTERN = '[A-Z]{2,5}(?:-[A-Z]{2,5})?-(?:0[1-9]|[1-9]\d+)';

    /**
     * Anything shaped like an ID, valid or not: a malformed one is rejected rather than ignored.
     */
    public const string ID_LIKE_PATTERN = '[A-Z]{2,5}(?:-[A-Z]{2,5})?-\d+';

    public const string MALFORMED_ID_REASON = 'the number has at least two digits, and only numbers below 10 start with 0';

    /**
     * Every line of the bundle, lowest first.
     */
    public const array LINES = ['3.x', '4.x', '5.x', '6.x', '7.x', 'main'];

    /**
     * @param array<string, array{status: 'planned'|'active', lines: list<string>}> $scenarios scenario ID => status and lines, in catalogue order
     */
    private function __construct(
        private array $scenarios,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $markdown = is_file($path) ? file_get_contents($path) : false;
        if (false === $markdown) {
            throw new \UnexpectedValueException(\sprintf('Cannot read the scenario catalogue %s.', $path));
        }

        return self::parse($markdown);
    }

    public static function parse(string $markdown): self
    {
        $rows = preg_split('/\R/', $markdown);
        if (false === $rows) {
            throw new \UnexpectedValueException('Cannot split the scenario catalogue into lines.');
        }

        $scenarios = [];
        $lines = [];
        foreach ($rows as $index => $line) {
            $line = trim($line);
            if (1 !== preg_match('/^\|\s*('.self::ID_LIKE_PATTERN.')\s*\|/', $line, $id)) {
                continue;
            }

            $lineNumber = $index + 1;
            $id = $id[1];
            if (!self::isId($id)) {
                throw new \UnexpectedValueException(\sprintf('Scenario row on line %d has the malformed ID "%s": %s.', $lineNumber, $id, self::MALFORMED_ID_REASON));
            }

            if (1 !== preg_match('/\|\s*(planned|active)(?:\s+\(([^()|]+)\))?\s*\|$/', $line, $status)) {
                throw new \UnexpectedValueException(\sprintf('Scenario row on line %d must end with a "planned" or "active" status cell: %s', $lineNumber, $line));
            }

            if (isset($lines[$id])) {
                throw new \UnexpectedValueException(\sprintf('Scenario %s is defined twice (lines %d and %d).', $id, $lines[$id], $lineNumber));
            }

            $lines[$id] = $lineNumber;
            $scenarios[$id] = [
                'status' => 'active' === $status[1] ? 'active' : 'planned',
                'lines' => self::parseLines($status[2] ?? '', $id, $lineNumber),
            ];
        }

        if ([] === $scenarios) {
            throw new \UnexpectedValueException('The scenario catalogue defines no scenario.');
        }

        return new self($scenarios);
    }

    public static function isId(string $id): bool
    {
        return 1 === preg_match('/^'.self::ID_PATTERN.'$/', $id);
    }

    public static function isIdLike(string $id): bool
    {
        return 1 === preg_match('/^'.self::ID_LIKE_PATTERN.'$/', $id);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->scenarios);
    }

    /**
     * The scenarios that apply to the line, whatever their status.
     *
     * @return list<string>
     */
    public function idsFor(string $line): array
    {
        self::assertKnownLine($line);

        return array_keys(array_filter($this->scenarios, static fn (array $scenario): bool => \in_array($line, $scenario['lines'], true)));
    }

    /**
     * The active scenarios that apply to the line.
     *
     * @return list<string>
     */
    public function activeIdsFor(string $line): array
    {
        self::assertKnownLine($line);

        return array_keys(array_filter($this->scenarios, static fn (array $scenario): bool => 'active' === $scenario['status'] && \in_array($line, $scenario['lines'], true)));
    }

    /**
     * @return list<string>
     */
    private static function parseLines(string $note, string $id, int $lineNumber): array
    {
        $note = trim($note);
        if ('' === $note) {
            return self::LINES;
        }

        if (str_ends_with($note, '+')) {
            $from = substr($note, 0, -1);
            $to = 'main';
        } elseif (str_contains($note, '-')) {
            [$from, $to] = explode('-', $note, 2);
        } else {
            $from = $to = $note;
        }

        $first = self::lineIndex($from, $id, $lineNumber);
        $last = self::lineIndex($to, $id, $lineNumber);
        if ($first > $last) {
            throw new \UnexpectedValueException(\sprintf('Scenario %s on line %d has an empty line range "%s".', $id, $lineNumber, $note));
        }

        return \array_slice(self::LINES, $first, $last - $first + 1);
    }

    private static function lineIndex(string $line, string $id, int $lineNumber): int
    {
        $index = array_search(trim($line), self::LINES, true);
        if (!\is_int($index)) {
            throw new \UnexpectedValueException(\sprintf('Scenario %s on line %d names an unknown line "%s". Known lines: %s.', $id, $lineNumber, trim($line), implode(', ', self::LINES)));
        }

        return $index;
    }

    private static function assertKnownLine(string $line): void
    {
        if (!\in_array($line, self::LINES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown line "%s". Known lines: %s.', $line, implode(', ', self::LINES)));
        }
    }
}
