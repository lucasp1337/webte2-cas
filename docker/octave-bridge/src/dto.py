"""Data transfer objects for the Octave bridge HTTP API."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class ExecResult:
    """Immutable result returned by the runner after an Octave invocation."""

    request_id: str
    stdout: str
    stderr: str
    exit_code: int
    duration_ms: int

    def to_dict(self) -> dict[str, str | int]:
        return {
            "request_id": self.request_id,
            "stdout": self.stdout,
            "stderr": self.stderr,
            "exit_code": self.exit_code,
            "duration_ms": self.duration_ms,
        }
