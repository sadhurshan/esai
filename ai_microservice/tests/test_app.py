"""Tests for the FastAPI microservice endpoints."""
from __future__ import annotations

from typing import Any, Dict

import pytest
from fastapi.testclient import TestClient

from ai_microservice import app as app_module


class _StubService:
    def __init__(self) -> None:
        self.last_forecast_args: Dict[str, Any] = {}
        self.supplier_payload: Dict[str, Any] = {}

    def predict_demand(self, part_id: int, history: Dict[str, float], horizon: int) -> Dict[str, Any]:
        self.last_forecast_args = {
            "part_id": part_id,
            "history": history,
            "horizon": horizon,
        }
        return {
            "model": "moving_average",
            "demand_qty": 42.0,
            "avg_daily_demand": 4.2,
            "reorder_point": 16.8,
            "safety_stock": 3.5,
            "order_by_date": "2024-01-15",
        }

    def predict_supplier_risk(self, supplier: Dict[str, Any]) -> Dict[str, Any]:
        self.supplier_payload = supplier
        return {
            "risk_category": "low",
            "score": 0.12,
            "explanation": "Stable delivery performance",
        }


@pytest.fixture
def stub_service(monkeypatch: pytest.MonkeyPatch) -> _StubService:
    stub = _StubService()
    monkeypatch.setattr(app_module, "service", stub)
    return stub


@pytest.fixture
def client(stub_service: _StubService) -> TestClient:
    with TestClient(app_module.app) as test_client:
        yield test_client


def test_forecast_endpoint_returns_expected_payload(client: TestClient, stub_service: _StubService) -> None:
    payload = {
        "part_id": 88,
        "horizon": 14,
        "history": [
            {"date": "2024-01-01", "quantity": 5.0},
            {"date": "2024-01-02", "quantity": 7.5},
        ],
    }

    response = client.post("/forecast", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    data = body["data"]
    assert {"model", "demand_qty", "avg_daily_demand", "reorder_point", "safety_stock", "order_by_date"}.issubset(data)
    assert stub_service.last_forecast_args == {
        "part_id": 88,
        "horizon": 14,
        "history": {
            "2024-01-01": 5.0,
            "2024-01-02": 7.5,
        },
    }


def test_supplier_risk_endpoint_returns_expected_fields(client: TestClient, stub_service: _StubService) -> None:
    payload = {
        "supplier": {
            "supplier_id": 501,
            "company_id": 10,
            "on_time_rate": 0.96,
        }
    }

    response = client.post("/supplier-risk", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    data = body["data"]
    assert data["risk_category"] == "low"
    assert "explanation" in data
    assert stub_service.supplier_payload == payload["supplier"]


def test_forecast_endpoint_returns_400_when_service_errors(
    client: TestClient,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    class _ErrorService:
        def predict_demand(self, *args: Any, **kwargs: Any) -> Dict[str, Any]:  # pragma: no cover - intentionally failing
            raise ValueError("Invalid history payload")

        def predict_supplier_risk(self, supplier: Dict[str, Any]) -> Dict[str, Any]:
            return {"risk_category": "low", "explanation": "n/a"}

    monkeypatch.setattr(app_module, "service", _ErrorService())

    payload = {
        "part_id": 1,
        "horizon": 7,
        "history": [
            {"date": "2024-01-01", "quantity": 5.0},
        ],
    }

    response = client.post("/forecast", json=payload)

    assert response.status_code == 400
    body = response.json()
    assert body["detail"] == "Invalid history payload"
