import type { ComponentType } from 'react';

export type AnimationName = 'pendulum' | 'ball-beam';

export type PendulumFrame = {
    t: number;
    x: number;
    theta: number;
};

export type BallBeamFrame = {
    t: number;
    position: number;
    beam_angle: number;
};

export type AnimationRendererProps<TFrame> = {
    frames: readonly TFrame[];
    cursorIndex: number;
    width: number;
    height: number;
};

export type AnimationRenderer<TFrame> = ComponentType<AnimationRendererProps<TFrame>>;
