#!/usr/bin/env python3
"""Utility script to train demo AI models for the Elements Supply AI service."""
from __future__ import annotations

import datetime as dt
import json
import math
import os
import random
import sys
from pathlib import Path
from typing import Dict

import numpy as np
import pandas as pd

ROOT_DIR = Path(__file__).resolve().parents[2]
if str(ROOT_DIR) not in sys.path:
    sys.path.insert(0, str(ROOT_DIR))

from ai_service import AISupplyService

RANDOM_SEED = int(os.getenv("AI_TRAIN_SEED", "1337"))
random.seed(RANDOM_SEED)
np.random.seed(RANDOM_SEED)


def clamp(value: float, lower: float = 0.0, upper: float = 1.0) -> float:
    return max(lower, min(upper, value))


def build_forecasting_frame(days: int = 210) -> pd.DataFrame:
    """Generate synthetic demand history for multiple parts."""
    today = dt.date.today()
    start = today - dt.timedelta(days=days)
    part_profiles = [
        {"part_id": 1001, "base": 18.0, "seasonal": 0.25, "trend": 0.015},
        {"part_id": 1002, "base": 12.0, "seasonal": 0.35, "trend": -0.005},
        {"part_id": 1003, "base": 25.0, "seasonal": 0.20, "trend": 0.0},
    ]
    rows: list[Dict[str, float | dt.date]] = []

    for profile in part_profiles:
        for offset in range(days):
            date = start + dt.timedelta(days=offset)
            trend_multiplier = 1 + profile["trend"] * (offset / max(1, days))
            seasonal_factor = 1 + profile["seasonal"] * math.sin(2 * math.pi * (offset % 30) / 30.0)
            noise = random.uniform(-1.5, 1.5)
            qty = max(0.0, profile["base"] * trend_multiplier * seasonal_factor + noise)
            rows.append({
                "part_id": profile["part_id"],
                "date": date,
                "quantity": round(qty, 3),
            })

    return pd.DataFrame(rows, columns=["part_id", "date", "quantity"])


def build_supplier_frame(count: int = 40) -> pd.DataFrame:
    """Generate supplier KPI rows with supervised risk grades."""
    rows: list[Dict[str, float | int | str]] = []
    for supplier_id in range(1, count + 1):
        maturity = supplier_id / (count + 1)
        on_time_rate = clamp(random.gauss(0.82 + 0.12 * maturity, 0.04))
        defect_rate = clamp(random.gauss(0.05 * (1 - maturity), 0.02))
        lead_time_variance = clamp(random.gauss(0.18 * (1 - maturity), 0.03))
        price_volatility = clamp(random.gauss(0.12 * (1 - maturity) + 0.05, 0.04))
        service_responsiveness = clamp(random.gauss(0.75 + 0.2 * maturity, 0.05))

        health_score = (
            0.5 * on_time_rate
            + 0.2 * (1 - defect_rate)
            + 0.15 * (1 - lead_time_variance)
            + 0.15 * service_responsiveness
            - 0.1 * price_volatility
        )
        health_score += random.uniform(-0.15, 0.15)
        if health_score >= 0.8:
            risk_grade = "low"
        elif health_score >= 0.6:
            risk_grade = "medium"
        else:
            risk_grade = "high"

        rows.append(
            {
                "supplier_id": supplier_id,
                "on_time_rate": round(on_time_rate, 4),
                "defect_rate": round(defect_rate, 4),
                "lead_time_variance": round(lead_time_variance, 4),
                "price_volatility": round(price_volatility, 4),
                "service_responsiveness": round(service_responsiveness, 4),
                "risk_grade": risk_grade,
            }
        )

    return pd.DataFrame(
        rows,
        columns=[
            "supplier_id",
            "on_time_rate",
            "defect_rate",
            "lead_time_variance",
            "price_volatility",
            "service_responsiveness",
            "risk_grade",
        ],
    )


def main() -> None:
    service = AISupplyService()

    forecast_df = build_forecasting_frame()
    forecast_horizon = int(os.getenv("AI_TRAIN_FORECAST_HORIZON", "21"))
    forecast_summary = service.train_forecasting_models(forecast_df, horizon=forecast_horizon)

    supplier_df = build_supplier_frame()
    _, risk_metrics = service.train_risk_model(supplier_df)

    artifact_target = os.getenv("AI_SERVICE_MODEL_PATH") or os.path.join(os.getcwd(), "storage", "ai_models.joblib")
    artifact_path = service.save_models(artifact_target)
    service.load_models(artifact_path)

    snapshot = service.readiness_snapshot()
    output = {
        "status": "trained",
        "artifact_path": artifact_path,
        "forecast_models": len(forecast_summary),
        "risk_model_metrics": risk_metrics,
        "models_loaded": snapshot.get("models_loaded"),
        "last_trained_at": snapshot.get("last_trained_at"),
    }
    print(json.dumps(output, indent=2))


if __name__ == "__main__":
    main()
