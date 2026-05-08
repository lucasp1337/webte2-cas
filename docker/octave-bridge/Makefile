.PHONY: qa fmt lint typecheck test sync

sync:
	uv sync

qa: lint typecheck test
	@echo "octave-bridge qa: ok"

lint:
	uv run ruff check .
	uv run ruff format --check .

fmt:
	uv run ruff format .
	uv run ruff check --fix .

typecheck:
	uv run mypy --strict .

test:
	uv run pytest
