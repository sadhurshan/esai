import pandas as pd

from ai_service import AISupplyService


def _build_forecast_dataframe() -> pd.DataFrame:
    dates = pd.date_range("2025-01-01", periods=90, freq="D")
    frames = []

    for part_id, multiplier in [(1, 1.0), (2, 0.5)]:
        frame = pd.DataFrame(
            {
                "part_id": part_id,
                "date": dates,
                "quantity": (pd.Series(range(1, len(dates) + 1)) * multiplier).astype(float),
            }
        )
        frames.append(frame)

    return pd.concat(frames, ignore_index=True)


def _build_supplier_dataframe() -> pd.DataFrame:
    return pd.DataFrame(
        {
            "supplier_id": [1, 2, 3, 4, 5, 6],
            "on_time_rate": [0.9, 0.7, 0.4, 0.95, 0.6, 0.5],
            "defect_rate": [0.02, 0.05, 0.12, 0.01, 0.08, 0.15],
            "lead_time_variance": [0.05, 0.1, 0.2, 0.03, 0.15, 0.25],
            "price_volatility": [0.04, 0.06, 0.2, 0.03, 0.07, 0.25],
            "service_responsiveness": [0.9, 0.6, 0.5, 0.95, 0.55, 0.4],
            "risk_grade": ["low", "medium", "high", "low", "medium", "high"],
        }
    )


def test_train_forecasting_models_and_predict():
    service = AISupplyService()
    data = _build_forecast_dataframe()

    results = service.train_forecasting_models(data, horizon=7)

    assert 1 in results
    assert 2 in results
    assert results[1]["best_model"] in {"exp_smoothing", "random_forest"}
    assert "metrics" in results[1]

    history_series = data[data["part_id"] == 1].set_index("date")["quantity"]
    prediction = service.predict_demand(1, history_series, horizon=7)

    assert prediction["demand_qty"] >= 0
    assert prediction["avg_daily_demand"] >= 0
    assert prediction["reorder_point"] >= prediction["avg_daily_demand"]


def test_train_risk_model_and_predict_supplier_risk():
    service = AISupplyService()
    supplier_df = _build_supplier_dataframe()

    model, metrics = service.train_risk_model(supplier_df)

    assert model is not None
    assert metrics

    prediction = service.predict_supplier_risk(supplier_df.iloc[0])

    assert prediction["risk_category"] in {"Low", "Medium", "High"}
    assert prediction["risk_score"] >= 0


def test_monitoring_helpers_compute_metrics():
    service = AISupplyService()
    dates = pd.date_range("2025-02-01", periods=30, freq="D")
    actual = pd.Series(10, index=dates, dtype=float)
    forecast = actual * 1.05

    weekly_mape = service.compute_weekly_mape(actual, forecast)
    assert weekly_mape >= 0

    baseline = pd.Series(["low", "medium", "high", "low", "medium", "high"])
    current = pd.Series(["medium", "medium", "high", "high", "medium", "high"])

    drift = service.compute_risk_distribution_drift(baseline, current)
    assert set(drift.keys()) == {"psi", "mean_diff", "std_diff"}
