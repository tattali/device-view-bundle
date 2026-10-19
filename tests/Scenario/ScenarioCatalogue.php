<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Scenario;

/**
 * The scenarios of docs/scenarios.md: their IDs and statuses.
 *
 * A scenario is a Markdown table row whose first cell is an ID (an area prefix, an optional
 * sub-area and a two-digit number, e.g. SW-03 or CON-TAC-02) and whose last cell is its
 * status, "planned" or "active". Other rows and lines are ignored.
 */
final readonly class ScenarioCatalogue
{
    public const string ID_PATTERN = '[A-Z]{2,5}(?:-[A-Z]{2,5})?-\d{2}';

    /**
     * @param array<string, 'planned'|'active'> $statuses scenario ID => status, in catalogue order
     */
    private function __construct(
        private array $statuses,
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

        $statuses = [];
        $lines = [];
        foreach ($rows as $index => $line) {
            $line = trim($line);
            if (1 !== preg_match('/^\|\s*('.self::ID_PATTERN.')\s*\|/', $line, $id)) {
                continue;
            }

            $lineNumber = $index + 1;
            if (1 !== preg_match('/\|\s*(planned|active)\s*\|$/', $line, $status)) {
                throw new \UnexpectedValueException(\sprintf('Scenario row on line %d must end with a "planned" or "active" status cell: %s', $lineNumber, $line));
            }

            if (isset($lines[$id[1]])) {
                throw new \UnexpectedValueException(\sprintf('Scenario %s is defined twice (lines %d and %d).', $id[1], $lines[$id[1]], $lineNumber));
            }

            $lines[$id[1]] = $lineNumber;
            $statuses[$id[1]] = 'active' === $status[1] ? 'active' : 'planned';
        }

        if ([] === $statuses) {
            throw new \UnexpectedValueException('The scenario catalogue defines no scenario.');
        }

        return new self($statuses);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->statuses);
    }

    /**
     * @return list<string>
     */
    public function activeIds(): array
    {
        return array_keys(array_filter($this->statuses, static fn (string $status): bool => 'active' === $status));
    }
}
