import datetime as dt

import pandas as pd

from ai_service import AISupplyService


def _build_synthetic_forecast_frame() -> pd.DataFrame:
    dates = pd.date_range("2025-01-01", periods=120, freq="D")
    linear_trend = pd.Series(range(1, len(dates) + 1), dtype=float)
    seasonal_wave = (pd.Series(range(len(dates)), dtype=float) % 14) * 0.5

    part_one = pd.DataFrame(
        {
            "part_id": 101,
            "date": dates,
            "quantity": (linear_trend * 0.75 + seasonal_wave).astype(float),
        }
    )

    part_two = pd.DataFrame(
        {
            "part_id": 202,
            "date": dates,
            "quantity": (linear_trend * 0.4 + 5).astype(float),
        }
    )

    return pd.concat([part_one, part_two], ignore_index=True)


def test_train_forecasting_models_registers_metrics_per_part():
    service = AISupplyService()
    data = _build_synthetic_forecast_frame()

    results = service.train_forecasting_models(data, horizon=14)

    assert set(results.keys()) == {101, 202}
    for payload in results.values():
        assert payload["best_model"] in {"exp_smoothing", "random_forest"}
        assert payload["metrics"]
        for metrics in payload["metrics"].values():
            assert metrics["mae"] >= 0
            assert metrics["mape"] >= 0


def test_predict_demand_emits_reorder_fields():
    service = AISupplyService()
    data = _build_synthetic_forecast_frame()
    service.train_forecasting_models(data, horizon=7)

    history = data[data["part_id"] == 101].set_index("date")["quantity"]
    forecast = service.predict_demand(101, history, horizon=10)

    assert forecast["demand_qty"] >= 0
    assert forecast["avg_daily_demand"] >= 0
    assert forecast["safety_stock"] >= 0
    assert forecast["reorder_point"] >= forecast["avg_daily_demand"]

    order_by_date = dt.date.fromisoformat(forecast["order_by_date"])
    assert order_by_date > history.index.max().date()
