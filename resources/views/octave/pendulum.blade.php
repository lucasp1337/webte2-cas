format long e
M = {{ sprintf('%.15e', $p->M) }};
m = {{ sprintf('%.15e', $p->m) }};
b = {{ sprintf('%.15e', $p->b) }};
I = {{ sprintf('%.15e', $p->I) }};
g = {{ sprintf('%.15e', $p->g) }};
l = {{ sprintf('%.15e', $p->l) }};
p_val = I*(M+m)+M*m*l^2;
A = [0 1 0 0; 0 -(I+m*l^2)*b/p_val (m^2*g*l^2)/p_val 0; 0 0 0 1; 0 -(m*l*b)/p_val m*g*l*(M+m)/p_val 0];
B = [ 0; (I+m*l^2)/p_val; 0; m*l/p_val];
C = [1 0 0 0; 0 0 1 0];
D = [0; 0];
K = lqr(A,B,C'*C,1);
Ac = (A-B*K);
N = -inv(C(1,:)*inv(A-B*K)*B);
sys = ss(Ac,B*N,C,D);
t = 0:{{ sprintf('%.15e', $p->dt) }}:{{ sprintf('%.15e', $p->t_end) }};
r = {{ sprintf('%.15e', $p->r) }};
@if (!empty($continueFrom))
init_state = [{{ sprintf('%.15e', $continueFrom[0]) }};{{ sprintf('%.15e', $continueFrom[1]) }};{{ sprintf('%.15e', $continueFrom[2]) }};{{ sprintf('%.15e', $continueFrom[3]) }}];
@else
init_state = [{{ sprintf('%.15e', $p->init_position) }};0;{{ sprintf('%.15e', $p->init_angle) }};0];
@endif
[y,t,x] = lsim(sys, r*ones(size(t)), t, init_state);
disp('---T---');
disp(t);
disp('---END-T---');
disp('---X---');
disp(x);
disp('---END-X---');
disp('---THETA---');
disp(y);
disp('---END-THETA---');
disp('---FINAL_STATE---');
disp(x(end,:));
disp('---END-FINAL_STATE---');
