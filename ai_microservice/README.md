# AI Microservice Packaging

This directory contains the FastAPI microservice that powers forecast and supplier-risk features. The service depends on `ai_service.py` for model logic and exposes `/forecast`, `/supplier-risk`, `/healthz`, and `/readyz` endpoints.

## Local Development

1. Create a virtual environment and install dependencies:
   ```bash
   python -m venv .venv && .venv\Scripts\activate
   pip install -r ai_microservice/requirements.txt
   ```
2. Export the environment variables you need (database URL, model path, etc.).
3. Start uvicorn:
   ```bash
   uvicorn ai_microservice.app:app --reload --host 0.0.0.0 --port 8000
   ```

## Container Image

Build and run the production image with Docker:

```bash
docker build -f ai_microservice/Dockerfile -t elements-ai-microservice .
docker run --rm -p 8000:8000 \
  -e AI_SERVICE_DATABASE_URL="mysql://user:pass@db:3306/elements" \
  -e AI_SERVICE_MODEL_PATH="/app/storage/ai_models.joblib" \
  elements-ai-microservice
```

The image installs all Python dependencies (FastAPI, pandas, scikit-learn, statsmodels, SQLAlchemy, etc.), exposes port `8000`, and declares a health check that pings `/healthz`.

## Docker Compose Stack

Use the dedicated compose file to run the stack with persistent model storage:

```bash
docker compose -f docker-compose.ai.yml up --build
```

This publishes port `8000`, mounts a named volume at `/app/storage`, and wires the health check so orchestrators can gate deployments on `/healthz`.

## Key Environment Variables

| Variable | Description |
| --- | --- |
| `AI_SERVICE_DATABASE_URL` | SQLAlchemy connection string for sourcing training/usage data. |
| `AI_SERVICE_MODEL_PATH` | Filesystem path where trained model artifacts are stored (`/app/storage/ai_models.joblib` inside the container). |
| `AI_SERVICE_DEFAULT_LEAD_TIME_DAYS` | Fallback lead time in days for reorder calculations. |
| `AI_SERVICE_RISK_THRESHOLDS` | CSV list of threshold cutoffs for supplier risk bands (low, medium, high). |
| `AI_CACHE_TTL_SECONDS` | TTL for in-memory response caching (defaults to 300 seconds). |
| `PORT` | HTTP port exposed by uvicorn (defaults to 8000). |

## Health and Readiness

- `GET /healthz` returns `{status:"ok", service:"ai_microservice"}` and is safe for Docker/Kubernetes health probes.
- `GET /readyz` returns `{status:"ok", models_loaded:bool, last_trained_at:str|null}` for readiness gates.

## Production Notes

- Mount a persistent volume at `/app/storage` so `ai_service.py` can reuse trained model artifacts between restarts.
- Inject tenant-safe credentials via secrets management; never bake them into the image.
- Pair the container with the Laravel application by hitting `/api/v1/ai/*` routes. Those routes already forward `X-Request-Id` so logs remain correlated end-to-end.
