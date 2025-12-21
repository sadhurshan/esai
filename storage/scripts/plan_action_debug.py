import asyncio
import os
from pathlib import Path

from ai_microservice.app import ActionPlanRequest, plan_action

ROOT = Path(__file__).resolve().parents[2]
os.environ.setdefault("PYTHONPATH", str(ROOT))

def load_env_values() -> None:
    env_path = ROOT / ".env"
    if not env_path.exists():
        return

    for line in env_path.read_text(encoding="utf-8").splitlines():
        if not line or line.strip().startswith("#"):
            continue
        if "=" not in line:
            continue
        key, value = line.split("=", 1)
        key = key.strip()
        if key in {"AI_LLM_PROVIDER", "AI_OPENAI_API_KEY", "AI_LLM_MODEL"} and key not in os.environ:
            os.environ[key] = value.strip()


load_env_values()

payload = ActionPlanRequest(
    company_id=8,
    action_type="rfq_draft",
    query="Draft an RFQ",
    inputs={
        "items": [
            {
                "part_id": "TBD-1",
                "description": "Widget",
                "quantity": 1,
                "target_date": "2025-12-31",
            }
        ]
    },
    user_context={},
    top_k=8,
)

async def main() -> None:
    try:
        result = await plan_action(payload)
        print(result)
    except Exception as exc:  # noqa: PERF203 - debugging helper
        import traceback

        traceback.print_exc()
        raise

if __name__ == "__main__":
    asyncio.run(main())
