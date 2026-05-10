format long e
M = {{ sprintf('%.15e', $p->cart_mass) }};
m = {{ sprintf('%.15e', $p->pendulum_mass) }};
b = {{ sprintf('%.15e', $p->friction) }};
I = {{ sprintf('%.15e', $p->inertia) }};
g = {{ sprintf('%.15e', $p->gravity) }};
l = {{ sprintf('%.15e', $p->length) }};
p_val = I*(M+m)+M*m*l^2;
A = [0 1 0 0; 0 -(I+m*l^2)*b/p_val (m^2*g*l^2)/p_val 0; 0 0 0 1; 0 -(m*l*b)/p_val m*g*l*(M+m)/p_val 0];
B = [ 0; (I+m*l^2)/p_val; 0; m*l/p_val];
C = [1 0 0 0; 0 0 1 0];
D = [0; 0];
K = lqr(A,B,C'*C,1);
Ac = (A-B*K);
N = -inv(C(1,:)*inv(A-B*K)*B);
sys = ss(Ac,B*N,C,D);
t = 0:{{ sprintf('%.15e', $p->step_size) }}:{{ sprintf('%.15e', $p->duration_seconds) }};
r = {{ sprintf('%.15e', $p->reference_position) }};
@if (!empty($continueFrom))
init_state = [{{ sprintf('%.15e', $continueFrom[0]) }};{{ sprintf('%.15e', $continueFrom[1]) }};{{ sprintf('%.15e', $continueFrom[2]) }};{{ sprintf('%.15e', $continueFrom[3]) }}];
@else
init_state = [{{ sprintf('%.15e', $p->initial_position) }};0;{{ sprintf('%.15e', $p->initial_angle) }};0];
@endif
[y,t,x] = lsim(sys, r*ones(size(t)), t, init_state);
% Emit each matrix as one row per line via printf so the trajectory parser
% sees a deterministic stream (disp() wraps wide matrices with
% "Columns N through M:" headers that the parser cannot tokenise).
disp('---T---');
printf('%.15e\n', t);
disp('---END-T---');
disp('---X---');
for __row = 1:rows(x)
    printf('%.15e %.15e %.15e %.15e\n', x(__row,1), x(__row,2), x(__row,3), x(__row,4));
endfor
disp('---END-X---');
disp('---THETA---');
for __row = 1:rows(y)
    printf('%.15e %.15e\n', y(__row,1), y(__row,2));
endfor
disp('---END-THETA---');
disp('---FINAL_STATE---');
printf('%.15e %.15e %.15e %.15e\n', x(end,1), x(end,2), x(end,3), x(end,4));
disp('---END-FINAL_STATE---');
