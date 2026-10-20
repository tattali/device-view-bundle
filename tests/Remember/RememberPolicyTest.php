<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Remember;

use DeviceViewBundle\Remember\RememberPolicy;
use DeviceViewBundle\Remember\RememberTrigger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The decision table of design.md section 7, without a consent provider: the rows with a
 * provider come with the consent checker.
 */
final class RememberPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{RememberPolicy, RememberTrigger, bool|null, int|null}>
     */
    public static function rows(): iterable
    {
        $policy = new RememberPolicy(onSwitch: true, detectedView: false, lifetime: 60, consentedLifetime: 3600);
        yield 'switch, on_switch true, consent granted' => [$policy, RememberTrigger::Switch, true, 3600];
        yield 'switch, on_switch true, consent denied' => [$policy, RememberTrigger::Switch, false, 60];
        yield 'switch, on_switch true, consent unknown' => [$policy, RememberTrigger::Switch, null, 60];
        yield 'detected, detected_view false' => [$policy, RememberTrigger::Detected, true, null];

        $noSwitch = new RememberPolicy(onSwitch: false, detectedView: true, lifetime: 60, consentedLifetime: 3600);
        yield 'switch, on_switch false, consent granted' => [$noSwitch, RememberTrigger::Switch, true, null];
        yield 'switch, on_switch false, consent unknown' => [$noSwitch, RememberTrigger::Switch, null, null];
        yield 'detected, detected_view true, no provider' => [$noSwitch, RememberTrigger::Detected, null, 60];
    }

    #[DataProvider('rows')]
    public function testTheDecisionTable(RememberPolicy $policy, RememberTrigger $trigger, ?bool $consent, ?int $lifetime): void
    {
        self::assertSame($lifetime, $policy->lifetime($trigger, $consent));
    }

    public function testTheDefaults(): void
    {
        $policy = new RememberPolicy();

        self::assertSame(0, $policy->lifetime(RememberTrigger::Switch));
        self::assertSame(31536000, $policy->lifetime(RememberTrigger::Switch, true));
        self::assertNull($policy->lifetime(RememberTrigger::Detected));
    }
}
