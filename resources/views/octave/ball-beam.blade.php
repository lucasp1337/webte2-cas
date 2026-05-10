format long e
m = {{ sprintf('%.15e', $p->ball_mass) }};
R = {{ sprintf('%.15e', $p->ball_radius) }};
g = -{{ sprintf('%.15e', $p->gravity) }};
J = {{ sprintf('%.15e', $p->inertia) }};
H = -m*g/(J/(R^2)+m);
A = [0 1 0 0; 0 0 H 0; 0 0 0 1; 0 0 0 0];
B = [0;0;0;1];
C = [1 0 0 0];
D = [0];
K = place(A,B,[-2+2i,-2-2i,-20,-80]);
N = -inv(C*inv(A-B*K)*B);
sys = ss(A-B*K,B,C,D);
t = 0:{{ sprintf('%.15e', $p->step_size) }}:{{ sprintf('%.15e', $p->duration_seconds) }};
r = {{ sprintf('%.15e', $p->reference_position) }};
@if (!empty($continueFrom))
init_state = [{{ sprintf('%.15e', $continueFrom[0]) }};{{ sprintf('%.15e', $continueFrom[1]) }};{{ sprintf('%.15e', $continueFrom[2]) }};{{ sprintf('%.15e', $continueFrom[3]) }}];
@else
init_state = [{{ sprintf('%.15e', $p->initial_position) }};{{ sprintf('%.15e', $p->initial_velocity) }};{{ sprintf('%.15e', $p->initial_angle) }};0];
@endif
[y,t,x] = lsim(N*sys, r*ones(size(t)), t, init_state);
% Emit each vector via printf so the trajectory parser sees a deterministic
% stream (disp() wraps wide matrices with "Columns N through M:" headers that
% the parser cannot tokenise reliably).
disp('---T---');
printf('%.15e\n', t);
disp('---END-T---');
disp('---POSITION---');
printf('%.15e\n', y);
disp('---END-POSITION---');
disp('---ANGLE---');
printf('%.15e\n', x(:,3));
disp('---END-ANGLE---');
disp('---FINAL_STATE---');
printf('%.15e %.15e %.15e %.15e\n', x(end,1), x(end,2), x(end,3), x(end,4));
disp('---END-FINAL_STATE---');
