"""FastAPI application that exposes forecasting and supplier risk endpoints."""
from __future__ import annotations

import logging
import time
from typing import Any, Dict

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field, validator

from ai_service import AISupplyService


LOGGER = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO)

app = FastAPI(title="Elements Supply AI Microservice", version="0.1.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
service = AISupplyService()


class ForecastRequest(BaseModel):
    part_id: int = Field(..., ge=1)
    history: list[Dict[str, Any]] = Field(..., description="List of {date, quantity} records")
    horizon: int = Field(..., gt=0, le=90)

    @validator("history")
    def validate_history(cls, value: list[Dict[str, Any]]) -> list[Dict[str, Any]]:  # noqa: D417
        if not value:
            raise ValueError("history must contain at least one record")
        for entry in value:
            if "date" not in entry or "quantity" not in entry:
                raise ValueError("Each history entry must include date and quantity")
        return value


class SupplierRiskRequest(BaseModel):
    supplier: Dict[str, Any]

    @validator("supplier")
    def validate_supplier(cls, value: Dict[str, Any]) -> Dict[str, Any]:  # noqa: D417
        if not value:
            raise ValueError("supplier object cannot be empty")
        return value


@app.post("/forecast")
async def forecast(payload: ForecastRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    history_series = {entry["date"]: entry["quantity"] for entry in payload.history}
    try:
        response = service.predict_demand(payload.part_id, history_series, payload.horizon)
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.info(
            "forecast_success",
            extra={
                "part_id": payload.part_id,
                "horizon": payload.horizon,
                "history_points": len(payload.history),
                "duration_ms": round(duration_ms, 2),
                "demand_qty": float(response.get("demand_qty", 0.0)),
            },
        )
        return {"status": "ok", "data": response}
    except Exception as exc:  # pragma: no cover - logged for operators
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "forecast_failure",
            extra={
                "part_id": payload.part_id,
                "horizon": payload.horizon,
                "history_points": len(payload.history),
                "duration_ms": round(duration_ms, 2),
            },
        )
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/supplier-risk")
async def supplier_risk(payload: SupplierRiskRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    try:
        response = service.predict_supplier_risk(payload.supplier)
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.info(
            "supplier_risk_success",
            extra={
                "supplier_id": payload.supplier.get("supplier_id"),
                "company_id": payload.supplier.get("company_id"),
                "duration_ms": round(duration_ms, 2),
                "risk_category": response.get("risk_category"),
            },
        )
        return {"status": "ok", "data": response}
    except Exception as exc:  # pragma: no cover
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "supplier_risk_failure",
            extra={
                "supplier_id": payload.supplier.get("supplier_id"),
                "company_id": payload.supplier.get("company_id"),
                "duration_ms": round(duration_ms, 2),
            },
        )
        raise HTTPException(status_code=400, detail=str(exc)) from exc
