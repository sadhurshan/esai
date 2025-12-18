import pandas as pd

from ai_service import AISupplyService


REQUIRED_FEATURES = {
    "on_time_rate": [0.9, 0.7, 0.4, 0.95, 0.6, 0.5],
    "defect_rate": [0.02, 0.05, 0.12, 0.01, 0.08, 0.15],
    "lead_time_variance": [0.05, 0.1, 0.2, 0.03, 0.15, 0.25],
    "price_volatility": [0.04, 0.06, 0.2, 0.03, 0.07, 0.25],
    "service_responsiveness": [0.9, 0.6, 0.5, 0.95, 0.55, 0.4],
}


def _build_suppliers(risk_grades: list[str]) -> pd.DataFrame:
    frame = {"supplier_id": list(range(1, len(risk_grades) + 1)), **REQUIRED_FEATURES.copy()}
    frame["risk_grade"] = risk_grades
    return pd.DataFrame(frame)


def test_train_risk_model_and_predict_returns_metrics_and_explanation():
    service = AISupplyService()
    df = _build_suppliers(["low", "medium", "high", "low", "medium", "high"])

    model, metrics = service.train_risk_model(df)

    assert model is not None
    assert metrics
    assert set(metrics.keys()).issuperset({"accuracy", "macro_f1"})

    supplier_result = service.predict_supplier_risk(df.iloc[0])

    assert supplier_result["risk_category"] in {"Low", "Medium", "High"}
    assert supplier_result["risk_score"] >= 0
    explanation = supplier_result["explanation"].lower()
    assert any(term in explanation for term in ["defect", "price", "time", "service"])


def test_train_risk_model_falls_back_to_regression_when_single_grade():
    service = AISupplyService()
    df = _build_suppliers(["low"] * 6)

    model, metrics = service.train_risk_model(df)

    assert model is not None
    assert metrics
    assert service._risk_model_type == "regression"

    supplier_result = service.predict_supplier_risk(df.iloc[0])
    assert supplier_result["risk_category"] in {"Low", "Medium", "High"}
    assert supplier_result["risk_score"] >= 0
    assert supplier_result["explanation"]
