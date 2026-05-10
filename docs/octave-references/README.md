# Octave reference scripts

Canonical Octave sources for the two simulations. **Reference only — not loaded at runtime.**

The runtime scripts are rendered from Blade templates in `resources/views/octave/` with parameter substitution; this directory holds the original hand-written scripts the simulations are derived from. If a Blade template diverges, the file here is the contract — update the Blade template, do not the other way around without an ADR.

| File | Simulation | Phase that ships the runtime template |
|---|---|---|
| `kyvadlo.m` | Inverted pendulum | 06 |
| `gulicka.m` | Ball on beam | 07 |

Used to capture golden-file fixtures for `app/Support/Octave/TrajectoryParser.php` tests under `tests/Fixtures/octave/`.
