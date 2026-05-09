"""Domain exceptions for the Octave bridge.

Each exception maps to a specific HTTP status code via error_middleware.
"""

from __future__ import annotations


class BridgeError(Exception):
    """Base class for all bridge domain errors."""


class CommandRejected(BridgeError):
    """Raised when the sanitiser blocks a command.

    Carries the human-readable reason describing which pattern matched.
    """

    reason: str

    def __init__(self, reason: str) -> None:
        super().__init__(reason)
        self.reason = reason


class OctaveTimeout(BridgeError):
    """Raised when the Octave subprocess exceeds its time budget."""

    reason: str

    def __init__(self, reason: str) -> None:
        super().__init__(reason)
        self.reason = reason


class OctaveBridgeUnavailable(BridgeError):
    """Raised when the Octave subprocess fails unexpectedly (generic 5xx fallback)."""

    reason: str

    def __init__(self, reason: str) -> None:
        super().__init__(reason)
        self.reason = reason
