from __future__ import annotations

import sys
from pathlib import Path


def _prepend_path(path: Path) -> None:
    resolved = str(path.resolve())
    if resolved not in sys.path:
        sys.path.insert(0, resolved)


TESTS_DIR = Path(__file__).resolve().parent
MICROSERVICE_DIR = TESTS_DIR.parent
REPO_ROOT = MICROSERVICE_DIR.parent

# Supports imports like `from ai_microservice import app` and `from ai_service import ...`.
_prepend_path(REPO_ROOT)

# Supports legacy tests importing modules directly, e.g. `import intent_planner`.
_prepend_path(MICROSERVICE_DIR)