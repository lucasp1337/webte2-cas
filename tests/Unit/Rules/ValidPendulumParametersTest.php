<?php

declare(strict_types=1);

use App\Rules\ValidPendulumParameters;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Build a valid parameter array for the rule.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ruleValidParams(array $overrides = []): array
{
    return array_merge([
        'M' => 1.0,
        'm' => 0.2,
        'b' => 0.1,
        'I' => 0.006,
        'g' => 9.81,
        'l' => 0.5,
        'r' => 0.2,
        'init_position' => 0.0,
        'init_angle' => 0.15,
        't_end' => 10.0,
        'dt' => 0.02,
    ], $overrides);
}

/**
 * Run the rule and return the collected failure messages.
 *
 * @param  array<string, mixed>  $value
 */
function runPendulumRule(array $value): MessageBag
{
    return Validator::make(
        ['parameters' => $value],
        ['parameters' => ['array', new ValidPendulumParameters]],
    )->errors();
}

describe('ValidPendulumParameters rule', function (): void {
    it('accepts a fully valid parameter set', function (): void {
        $errors = runPendulumRule(ruleValidParams());

        expect($errors->isEmpty())->toBeTrue();
    });

    it('accepts boundary: t_end=30, dt=0.001, b=0', function (): void {
        $errors = runPendulumRule(ruleValidParams(['t_end' => 30, 'dt' => 0.001, 'b' => 0.0]));
        expect($errors->isEmpty())->toBeTrue();
    });

    it('accepts boundary: dt=0.5', function (): void {
        $errors = runPendulumRule(ruleValidParams(['dt' => 0.5]));
        expect($errors->isEmpty())->toBeTrue();
    });

    it('rejects cart_mass equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['M' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('M');
    });

    it('rejects cart_mass below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['M' => -1.0]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects pendulum_mass equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['m' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('m');
    });

    it('rejects pendulum_mass below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['m' => -0.5]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects friction below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['b' => -0.1]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('b');
    });

    it('rejects inertia equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['I' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('I');
    });

    it('rejects inertia below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['I' => -0.001]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects gravity equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['g' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('g');
    });

    it('rejects gravity below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['g' => -9.81]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects rod_length equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['l' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('l');
    });

    it('rejects rod_length below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['l' => -0.5]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects t_end above 30', function (): void {
        $errors = runPendulumRule(ruleValidParams(['t_end' => 31.0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('t_end');
    });

    it('rejects t_end equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['t_end' => 0.0]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects t_end below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['t_end' => -5.0]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects dt below 0.001', function (): void {
        $errors = runPendulumRule(ruleValidParams(['dt' => 0.0005]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('dt');
    });

    it('rejects dt above 0.5', function (): void {
        $errors = runPendulumRule(ruleValidParams(['dt' => 0.6]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects NaN for cart_mass', function (): void {
        $errors = runPendulumRule(ruleValidParams(['M' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('M');
    });

    it('rejects positive infinity for gravity', function (): void {
        $errors = runPendulumRule(ruleValidParams(['g' => INF]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('g');
    });

    it('rejects negative infinity for rod_length', function (): void {
        $errors = runPendulumRule(ruleValidParams(['l' => -INF]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('l');
    });

    it('rejects a non-array input', function (): void {
        $errors = Validator::make(
            ['parameters' => 'not-an-array'],
            ['parameters' => [new ValidPendulumParameters]],
        )->errors();

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects parameters with a missing required field', function (): void {
        // Remove 'M' entirely so PendulumParameters::from() cannot cast it.
        $incomplete = ruleValidParams();
        unset($incomplete['M']);

        $errors = runPendulumRule($incomplete);

        expect($errors->isNotEmpty())->toBeTrue();
    });
});
