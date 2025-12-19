# syntax=docker/dockerfile:1.6
FROM python:3.11-slim AS runtime

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    POETRY_VIRTUALENVS_CREATE=false

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential \
        gfortran \
        libatlas-base-dev \
        curl \
    && rm -rf /var/lib/apt/lists/*

COPY ai_microservice/requirements.txt ./requirements.txt
RUN pip install --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

COPY ai_service.py ./ai_service.py
COPY ai_microservice ./ai_microservice

RUN useradd --system --create-home --shell /usr/sbin/nologin microservice \
    && chown -R microservice:microservice /app
USER microservice

ENV AI_CACHE_TTL_SECONDS=300 \
    AI_SERVICE_MODEL_PATH=/app/storage/ai_models.joblib \
    PORT=8000

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:${PORT}/healthz || exit 1

CMD ["uvicorn", "ai_microservice.app:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "2"]
