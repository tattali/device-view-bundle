<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use Psr\Log\AbstractLogger;

/**
 * Keeps the log records in memory, so tests can assert them.
 */
final class TestLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string}> */
    public private(set) array $records = [];

    /**
     * The message has no native type, so the class works with psr/log 1 (untyped), 2 and 3.
     *
     * @param string|\Stringable $message
     */
    #[\Override]
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message];
    }

    /**
     * @return list<string> the messages logged at the level
     */
    public function messages(string $level): array
    {
        return array_values(array_map(
            static fn (array $record): string => $record['message'],
            array_filter($this->records, static fn (array $record): bool => $level === $record['level']),
        ));
    }
}
