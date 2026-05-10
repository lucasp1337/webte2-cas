<?php

declare(strict_types=1);

use App\Rules\ValidBallBeamParameters;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Build a valid ball-beam parameter array.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bbRuleValidParams(array $overrides = []): array
{
    return array_merge([
        'ball_mass' => 0.11,
        'ball_radius' => 0.015,
        'inertia' => 0.00000999,
        'beam_length' => 1.0,
        'gravity' => 9.81,
        'reference_position' => 0.25,
        'initial_position' => 0.0,
        'initial_velocity' => 0.0,
        'initial_angle' => 0.0,
        'duration_seconds' => 5.0,
        'step_size' => 0.05,
    ], $overrides);
}

/**
 * Run the rule and return the collected failure messages.
 *
 * @param  array<string, mixed>  $value
 */
function runBallBeamRule(array $value): MessageBag
{
    return Validator::make(
        ['parameters' => $value],
        ['parameters' => ['array', new ValidBallBeamParameters]],
    )->errors();
}

describe('ValidBallBeamParameters rule', function (): void {
    it('accepts a fully valid parameter set', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams());

        expect($errors->isEmpty())->toBeTrue();
    });

    it('accepts boundary: duration_seconds=30, step_size=0.001', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['duration_seconds' => 30.0, 'step_size' => 0.001]));

        expect($errors->isEmpty())->toBeTrue();
    });

    it('accepts boundary: step_size=0.5', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['step_size' => 0.5]));

        expect($errors->isEmpty())->toBeTrue();
    });

    // -------------------------------------------------------------------------
    // ball_mass ∈ (0, 100)
    // -------------------------------------------------------------------------

    it('rejects ball_mass equal to zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_mass' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('ball_mass');
    });

    it('rejects ball_mass equal to 100', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_mass' => 100]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('ball_mass');
    });

    it('rejects ball_mass above 100', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_mass' => 200]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects ball_mass below zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_mass' => -1.0]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects NaN for ball_mass', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_mass' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('ball_mass');
    });

    it('rejects positive infinity for ball_mass', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_mass' => INF]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('ball_mass');
    });

    // -------------------------------------------------------------------------
    // ball_radius ∈ (0, 1)
    // -------------------------------------------------------------------------

    it('rejects ball_radius equal to zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_radius' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('ball_radius');
    });

    it('rejects ball_radius equal to 1', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_radius' => 1]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('ball_radius');
    });

    it('rejects ball_radius above 1', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_radius' => 2]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects NaN for ball_radius', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['ball_radius' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('ball_radius');
    });

    // -------------------------------------------------------------------------
    // inertia ∈ (0, 1)
    // -------------------------------------------------------------------------

    it('rejects inertia equal to zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['inertia' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('inertia');
    });

    it('rejects inertia equal to 1', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['inertia' => 1]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('inertia');
    });

    it('rejects inertia above 1', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['inertia' => 2]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects NaN for inertia', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['inertia' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('inertia');
    });

    // -------------------------------------------------------------------------
    // beam_length ∈ (0, 10)
    // -------------------------------------------------------------------------

    it('rejects beam_length equal to zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['beam_length' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('beam_length');
    });

    it('rejects beam_length equal to 10', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['beam_length' => 10]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('beam_length');
    });

    it('rejects beam_length above 10', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['beam_length' => 11]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects NaN for beam_length', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['beam_length' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('beam_length');
    });

    // -------------------------------------------------------------------------
    // gravity ∈ (0, 30)
    // -------------------------------------------------------------------------

    it('rejects gravity equal to zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['gravity' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('gravity');
    });

    it('rejects gravity equal to 30', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['gravity' => 30]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('gravity');
    });

    it('rejects gravity above 30', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['gravity' => 31]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects gravity below zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['gravity' => -9.81]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects NaN for gravity', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['gravity' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('gravity');
    });

    it('rejects positive infinity for gravity', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['gravity' => INF]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('gravity');
    });

    // -------------------------------------------------------------------------
    // duration_seconds ∈ (0, 30]
    // -------------------------------------------------------------------------

    it('rejects duration_seconds equal to zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['duration_seconds' => 0]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('duration_seconds');
    });

    it('rejects duration_seconds above 30', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['duration_seconds' => 31]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('duration_seconds');
    });

    it('rejects duration_seconds below zero', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['duration_seconds' => -5.0]));

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects NaN for duration_seconds', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['duration_seconds' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('duration_seconds');
    });

    // -------------------------------------------------------------------------
    // step_size ∈ [0.001, 0.5]
    // -------------------------------------------------------------------------

    it('rejects step_size below 0.001', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['step_size' => 0.0005]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('step_size');
    });

    it('rejects step_size above 0.5', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['step_size' => 0.6]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('step_size');
    });

    it('rejects NaN for step_size', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['step_size' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('step_size');
    });

    // -------------------------------------------------------------------------
    // Finite checks on unconstrained position / velocity / angle fields
    // -------------------------------------------------------------------------

    it('rejects positive infinity for reference_position', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['reference_position' => INF]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('reference_position');
    });

    it('rejects negative infinity for initial_position', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['initial_position' => -INF]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('initial_position');
    });

    it('rejects NaN for initial_velocity', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['initial_velocity' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('initial_velocity');
    });

    it('rejects NaN for initial_angle', function (): void {
        $errors = runBallBeamRule(bbRuleValidParams(['initial_angle' => NAN]));

        expect($errors->isNotEmpty())->toBeTrue();
        expect($errors->first('parameters'))->toContain('initial_angle');
    });

    // -------------------------------------------------------------------------
    // Structural rejections
    // -------------------------------------------------------------------------

    it('rejects a non-array input', function (): void {
        $errors = Validator::make(
            ['parameters' => 'not-an-array'],
            ['parameters' => [new ValidBallBeamParameters]],
        )->errors();

        expect($errors->isNotEmpty())->toBeTrue();
    });

    it('rejects parameters with a missing required field', function (): void {
        $incomplete = bbRuleValidParams();
        unset($incomplete['ball_mass']);

        $errors = runBallBeamRule($incomplete);

        expect($errors->isNotEmpty())->toBeTrue();
    });
});
