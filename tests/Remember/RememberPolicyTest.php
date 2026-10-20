<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Remember;

use DeviceViewBundle\Remember\RememberPolicy;
use DeviceViewBundle\Remember\RememberTrigger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The decision table of design.md section 7.
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
        yield 'detected, detected_view true, no provider, consent granted' => [$noSwitch, RememberTrigger::Detected, true, 3600];
        yield 'detected, detected_view true, no provider, consent denied' => [$noSwitch, RememberTrigger::Detected, false, 60];

        $provider = new RememberPolicy(onSwitch: true, detectedView: true, lifetime: 60, consentedLifetime: 3600, consentProvider: true);
        yield 'switch, provider, consent granted' => [$provider, RememberTrigger::Switch, true, 3600];
        yield 'switch, provider, consent denied' => [$provider, RememberTrigger::Switch, false, 60];
        yield 'switch, provider, consent unknown' => [$provider, RememberTrigger::Switch, null, 60];
        yield 'detected, provider, consent granted' => [$provider, RememberTrigger::Detected, true, 3600];
        yield 'detected, provider, consent denied' => [$provider, RememberTrigger::Detected, false, null];
        yield 'detected, provider, consent unknown' => [$provider, RememberTrigger::Detected, null, null];

        $providerOff = new RememberPolicy(onSwitch: false, detectedView: false, lifetime: 60, consentedLifetime: 3600, consentProvider: true);
        yield 'switch, provider, on_switch false' => [$providerOff, RememberTrigger::Switch, true, null];
        yield 'detected, provider, detected_view false' => [$providerOff, RememberTrigger::Detected, true, null];
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
        self::assertTrue($policy->remembers(RememberTrigger::Switch));
        self::assertFalse($policy->remembers(RememberTrigger::Detected));
    }

    /**
     * @return iterable<string, array{RememberPolicy, RememberTrigger, bool}>
     */
    public static function remembers(): iterable
    {
        yield 'switch, on_switch true' => [new RememberPolicy(onSwitch: true, detectedView: false), RememberTrigger::Switch, true];
        yield 'switch, on_switch false' => [new RememberPolicy(onSwitch: false, detectedView: true), RememberTrigger::Switch, false];
        yield 'detected, detected_view true' => [new RememberPolicy(onSwitch: false, detectedView: true), RememberTrigger::Detected, true];
        yield 'detected, detected_view false' => [new RememberPolicy(onSwitch: true, detectedView: false), RememberTrigger::Detected, false];
    }

    /**
     * Whether a trigger may write at all, whatever the consent: the remember listener asks the
     * consent checker only when it does.
     */
    #[DataProvider('remembers')]
    public function testRemembers(RememberPolicy $policy, RememberTrigger $trigger, bool $remembers): void
    {
        self::assertSame($remembers, $policy->remembers($trigger));
    }
}
