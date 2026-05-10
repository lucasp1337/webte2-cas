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
        'cart_mass' => 1.0,
        'pendulum_mass' => 0.2,
        'friction' => 0.1,
        'inertia' => 0.006,
        'gravity' => 9.81,
        'length' => 0.5,
        'reference_position' => 0.2,
        'initial_position' => 0.0,
        'initial_angle' => 0.15,
        'duration_seconds' => 10.0,
        'step_size' => 0.02,
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
        $errors = runPendulumRule(ruleValidParams(['duration_seconds' => 30, 'step_size' => 0.001, 'friction' => 0.0]));
        expect($errors->isEmpty())->toBeTrue();
    });

    it('accepts boundary: dt=0.5', function (): void {
        $errors = runPendulumRule(ruleValidParams(['step_size' => 0.5]));
        expect($errors->isEmpty())->toBeTrue();
    });

    it('rejects cart_mass equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['cart_mass' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('cart_mass');
    });

    it('rejects cart_mass below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['cart_mass' => -1.0]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects pendulum_mass equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['pendulum_mass' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('pendulum_mass');
    });

    it('rejects pendulum_mass below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['pendulum_mass' => -0.5]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects friction below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['friction' => -0.1]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('friction');
    });

    it('rejects inertia equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['inertia' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('inertia');
    });

    it('rejects inertia below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['inertia' => -0.001]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects gravity equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['gravity' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('gravity');
    });

    it('rejects gravity below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['gravity' => -9.81]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects rod_length equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['length' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('length');
    });

    it('rejects rod_length below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['length' => -0.5]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects t_end above 30', function (): void {
        $errors = runPendulumRule(ruleValidParams(['duration_seconds' => 31.0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('duration_seconds');
    });

    it('rejects t_end equal to zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['duration_seconds' => 0.0]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects t_end below zero', function (): void {
        $errors = runPendulumRule(ruleValidParams(['duration_seconds' => -5.0]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects dt below 0.001', function (): void {
        $errors = runPendulumRule(ruleValidParams(['step_size' => 0.0005]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('step_size');
    });

    it('rejects dt above 0.5', function (): void {
        $errors = runPendulumRule(ruleValidParams(['step_size' => 0.6]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects NaN for cart_mass', function (): void {
        $errors = runPendulumRule(ruleValidParams(['cart_mass' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('cart_mass');
    });

    it('rejects positive infinity for gravity', function (): void {
        $errors = runPendulumRule(ruleValidParams(['gravity' => INF]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('gravity');
    });

    it('rejects negative infinity for rod_length', function (): void {
        $errors = runPendulumRule(ruleValidParams(['length' => -INF]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('length');
    });

    it('rejects a non-array input', function (): void {
        $errors = Validator::make(
            ['parameters' => 'not-an-array'],
            ['parameters' => [new ValidPendulumParameters]],
        )->errors();

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects parameters with a missing required field', function (): void {
        // Remove 'cart_mass' entirely so PendulumParameters::from() cannot cast it.
        $incomplete = ruleValidParams();
        unset($incomplete['cart_mass']);

        $errors = runPendulumRule($incomplete);

        expect($errors->isNotEmpty())->toBeTrue();
    });
});
