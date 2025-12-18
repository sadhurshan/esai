import pandas as pd
from sqlalchemy import create_engine, text
from sqlalchemy.pool import StaticPool

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


def _build_sqlite_engine():
    return create_engine(
        "sqlite://",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
        future=True,
    )


def _seed_inventory_transactions(engine):
    with engine.begin() as conn:
        conn.execute(text("DROP TABLE IF EXISTS inventory_transactions"))
        conn.execute(
            text(
                """
                CREATE TABLE inventory_transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    part_id INTEGER NOT NULL,
                    transaction_date TEXT NOT NULL,
                    quantity REAL NOT NULL,
                    transaction_type TEXT NOT NULL,
                    company_id INTEGER NOT NULL
                )
                """
            )
        )
        rows = [
            {
                "part_id": 100,
                "transaction_date": "2025-01-01 08:00:00",
                "quantity": 5.5,
                "transaction_type": "issue",
                "company_id": 1,
            },
            {
                "part_id": 100,
                "transaction_date": "2025-01-03 09:00:00",
                "quantity": 4.0,
                "transaction_type": "return",
                "company_id": 1,
            },
            {
                "part_id": 200,
                "transaction_date": "2025-01-02 12:00:00",
                "quantity": 3.0,
                "transaction_type": "transfer",
                "company_id": 1,
            },
            {
                "part_id": 100,
                "transaction_date": "2025-01-02 10:00:00",
                "quantity": 2.0,
                "transaction_type": "adjustment",
                "company_id": 2,
            },
        ]
        conn.execute(
            text(
                """
                INSERT INTO inventory_transactions (part_id, transaction_date, quantity, transaction_type, company_id)
                VALUES (:part_id, :transaction_date, :quantity, :transaction_type, :company_id)
                """
            ),
            rows,
        )


def _seed_supplier_test_data(engine):
    tables = [
        "purchase_order_events",
        "goods_receipt_lines",
        "goods_receipt_notes",
        "po_lines",
        "purchase_orders",
    ]
    with engine.begin() as conn:
        for table in tables:
            conn.execute(text(f"DROP TABLE IF EXISTS {table}"))

        conn.execute(
            text(
                """
                CREATE TABLE purchase_orders (
                    id INTEGER PRIMARY KEY,
                    supplier_id INTEGER NOT NULL,
                    company_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL,
                    deleted_at TEXT
                )
                """
            )
        )
        conn.execute(
            text(
                """
                CREATE TABLE po_lines (
                    id INTEGER PRIMARY KEY,
                    purchase_order_id INTEGER NOT NULL,
                    unit_price REAL NOT NULL,
                    delivery_date TEXT,
                    created_at TEXT,
                    deleted_at TEXT
                )
                """
            )
        )
        conn.execute(
            text(
                """
                CREATE TABLE goods_receipt_notes (
                    id INTEGER PRIMARY KEY,
                    created_at TEXT,
                    inspected_at TEXT,
                    deleted_at TEXT
                )
                """
            )
        )
        conn.execute(
            text(
                """
                CREATE TABLE goods_receipt_lines (
                    id INTEGER PRIMARY KEY,
                    purchase_order_line_id INTEGER NOT NULL,
                    goods_receipt_note_id INTEGER NOT NULL,
                    received_qty REAL,
                    rejected_qty REAL,
                    deleted_at TEXT
                )
                """
            )
        )
        conn.execute(
            text(
                """
                CREATE TABLE purchase_order_events (
                    id INTEGER PRIMARY KEY,
                    purchase_order_id INTEGER NOT NULL,
                    event_type TEXT NOT NULL,
                    occurred_at TEXT NOT NULL
                )
                """
            )
        )

        purchase_orders = [
            {"id": 1001, "supplier_id": 10, "company_id": 1, "created_at": "2024-12-28 08:00:00"},
            {"id": 1002, "supplier_id": 10, "company_id": 1, "created_at": "2025-01-10 09:00:00"},
            {"id": 2001, "supplier_id": 20, "company_id": 1, "created_at": "2024-12-30 07:30:00"},
            {"id": 2002, "supplier_id": 20, "company_id": 1, "created_at": "2025-01-12 11:00:00"},
        ]
        conn.execute(
            text(
                """
                INSERT INTO purchase_orders (id, supplier_id, company_id, created_at, deleted_at)
                VALUES (:id, :supplier_id, :company_id, :created_at, NULL)
                """
            ),
            purchase_orders,
        )

        po_lines = [
            {
                "id": 5001,
                "purchase_order_id": 1001,
                "unit_price": 10.0,
                "delivery_date": "2025-01-05 00:00:00",
                "created_at": "2024-12-28 08:15:00",
            },
            {
                "id": 5002,
                "purchase_order_id": 1002,
                "unit_price": 12.0,
                "delivery_date": "2025-01-18 00:00:00",
                "created_at": "2025-01-10 09:15:00",
            },
            {
                "id": 6001,
                "purchase_order_id": 2001,
                "unit_price": 20.0,
                "delivery_date": "2025-01-08 00:00:00",
                "created_at": "2024-12-30 07:45:00",
            },
            {
                "id": 6002,
                "purchase_order_id": 2002,
                "unit_price": 21.0,
                "delivery_date": "2025-01-16 00:00:00",
                "created_at": "2025-01-12 11:15:00",
            },
        ]
        conn.execute(
            text(
                """
                INSERT INTO po_lines (id, purchase_order_id, unit_price, delivery_date, created_at, deleted_at)
                VALUES (:id, :purchase_order_id, :unit_price, :delivery_date, :created_at, NULL)
                """
            ),
            po_lines,
        )

        goods_receipt_notes = [
            {"id": 7001, "created_at": "2025-01-05 12:00:00", "inspected_at": "2025-01-05 12:00:00"},
            {"id": 7002, "created_at": "2025-01-19 14:00:00", "inspected_at": "2025-01-19 14:00:00"},
            {"id": 8001, "created_at": "2025-01-08 10:00:00", "inspected_at": "2025-01-08 10:00:00"},
            {"id": 8002, "created_at": "2025-01-15 16:00:00", "inspected_at": "2025-01-15 16:00:00"},
        ]
        conn.execute(
            text(
                """
                INSERT INTO goods_receipt_notes (id, created_at, inspected_at, deleted_at)
                VALUES (:id, :created_at, :inspected_at, NULL)
                """
            ),
            goods_receipt_notes,
        )

        goods_receipt_lines = [
            {
                "id": 9001,
                "purchase_order_line_id": 5001,
                "goods_receipt_note_id": 7001,
                "received_qty": 10,
                "rejected_qty": 1,
            },
            {
                "id": 9002,
                "purchase_order_line_id": 5002,
                "goods_receipt_note_id": 7002,
                "received_qty": 20,
                "rejected_qty": 0,
            },
            {
                "id": 9003,
                "purchase_order_line_id": 6001,
                "goods_receipt_note_id": 8001,
                "received_qty": 15,
                "rejected_qty": 0,
            },
            {
                "id": 9004,
                "purchase_order_line_id": 6002,
                "goods_receipt_note_id": 8002,
                "received_qty": 25,
                "rejected_qty": 2,
            },
        ]
        conn.execute(
            text(
                """
                INSERT INTO goods_receipt_lines (
                    id, purchase_order_line_id, goods_receipt_note_id, received_qty, rejected_qty, deleted_at
                )
                VALUES (:id, :purchase_order_line_id, :goods_receipt_note_id, :received_qty, :rejected_qty, NULL)
                """
            ),
            goods_receipt_lines,
        )

        purchase_order_events = [
            {"id": 10001, "purchase_order_id": 1001, "event_type": "po_sent", "occurred_at": "2024-12-28 08:05:00"},
            {
                "id": 10002,
                "purchase_order_id": 1001,
                "event_type": "supplier_acknowledged",
                "occurred_at": "2024-12-28 09:00:00",
            },
            {"id": 10003, "purchase_order_id": 1002, "event_type": "po_sent", "occurred_at": "2025-01-10 09:05:00"},
            {
                "id": 10004,
                "purchase_order_id": 1002,
                "event_type": "supplier_acknowledged",
                "occurred_at": "2025-01-11 16:00:00",
            },
            {"id": 10005, "purchase_order_id": 2001, "event_type": "po_sent", "occurred_at": "2024-12-30 07:35:00"},
            {
                "id": 10006,
                "purchase_order_id": 2001,
                "event_type": "supplier_acknowledged",
                "occurred_at": "2024-12-30 12:00:00",
            },
            {"id": 10007, "purchase_order_id": 2002, "event_type": "po_sent", "occurred_at": "2025-01-12 11:10:00"},
            {
                "id": 10008,
                "purchase_order_id": 2002,
                "event_type": "supplier_acknowledged",
                "occurred_at": "2025-01-13 08:00:00",
            },
        ]
        conn.execute(
            text(
                """
                INSERT INTO purchase_order_events (id, purchase_order_id, event_type, occurred_at)
                VALUES (:id, :purchase_order_id, :event_type, :occurred_at)
                """
            ),
            purchase_order_events,
        )


def test_load_inventory_data_returns_continuous_daily_series():
    engine = _build_sqlite_engine()
    _seed_inventory_transactions(engine)
    service = AISupplyService(db_url="sqlite://")
    service._engine = engine

    df = service.load_inventory_data(start_date="2025-01-01", end_date="2025-01-03", company_id=1)

    assert list(df.columns) == ["part_id", "date", "quantity"]
    assert pd.api.types.is_float_dtype(df["quantity"])  # quantities are coerced to float

    part_100 = df[df["part_id"] == 100].reset_index(drop=True)
    expected_dates = list(pd.date_range("2025-01-01", "2025-01-03", freq="D"))
    assert part_100["date"].tolist() == expected_dates
    assert part_100["quantity"].iloc[0] == 5.5
    assert part_100["quantity"].iloc[1] == 0.0  # missing day is zero-filled
    assert part_100["quantity"].iloc[2] == 4.0


def test_load_supplier_data_returns_complete_feature_columns():
    engine = _build_sqlite_engine()
    _seed_supplier_test_data(engine)
    service = AISupplyService(db_url="sqlite://")
    service._engine = engine

    df = service.load_supplier_data(start_date="2024-12-01", end_date="2025-02-01", company_id=1)

    expected_columns = {
        "supplier_id",
        "on_time_rate",
        "defect_rate",
        "lead_time_variance",
        "price_volatility",
        "service_responsiveness",
    }
    assert expected_columns.issubset(df.columns)
    assert len(df) == 2

    df = df.sort_values("supplier_id").reset_index(drop=True)
    assert df["supplier_id"].tolist() == [10, 20]

    for column in expected_columns - {"supplier_id"}:
        assert pd.api.types.is_numeric_dtype(df[column])
        assert df[column].notna().all()


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
