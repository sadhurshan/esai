"""Core AI/ML service definition for the Elements Supply AI microservice."""
from __future__ import annotations

import datetime as dt
import logging
import math
import os
from typing import Any, Dict, Optional, Tuple

import numpy as np
import pandas as pd
from pandas import DataFrame
from sklearn.ensemble import (
    GradientBoostingClassifier,
    GradientBoostingRegressor,
    RandomForestRegressor,
)
from sklearn.metrics import accuracy_score, f1_score, mean_absolute_error, r2_score
from sklearn.model_selection import train_test_split
from sqlalchemy import bindparam, create_engine, text
from sqlalchemy.engine import Engine
from sqlalchemy.exc import SQLAlchemyError
from statsmodels.tsa.holtwinters import ExponentialSmoothing


LOGGER = logging.getLogger(__name__)


class AISupplyService:
    """Encapsulates demand forecasting and supplier risk logic for the microservice."""

    _DEFAULT_TRANSACTION_TYPES = ("issue", "return", "transfer", "adjustment")

    def __init__(self, *, db_url: Optional[str] = None) -> None:
        self._db_url = db_url or os.getenv("AI_SERVICE_DATABASE_URL") or os.getenv("DATABASE_URL")
        self._engine: Optional[Engine] = None
        self._inventory_table = os.getenv("AI_SERVICE_INVENTORY_TABLE", "inventory_transactions")
        self._po_send_events = self._parse_event_types(
            os.getenv("AI_SERVICE_PO_SEND_EVENTS"),
            ("po_sent", "purchase_order_sent"),
        )
        self._po_response_events = self._parse_event_types(
            os.getenv("AI_SERVICE_PO_RESPONSE_EVENTS"),
            ("supplier_acknowledged", "supplier_comment", "supplier_status_update"),
        )
        self._response_sla_hours = float(os.getenv("AI_SERVICE_RESPONSE_SLA_HOURS", "24"))
        self._forecast_registry: Dict[int, Dict[str, Any]] = {}
        self._default_lead_time_days = int(os.getenv("AI_SERVICE_DEFAULT_LEAD_TIME_DAYS", "7"))
        self._risk_model: Optional[Any] = None
        self._risk_model_type: Optional[str] = None
        self._risk_model_features: list[str] = []
        self._risk_feature_stats: Dict[str, Dict[str, float]] = {}
        self._risk_thresholds = self._parse_risk_thresholds(os.getenv("AI_SERVICE_RISK_THRESHOLDS", "0.4,0.7"))
        self._risk_label_map = {"low": 0, "medium": 1, "high": 2}
        self._risk_label_inverse = {value: key for key, value in self._risk_label_map.items()}

    def load_inventory_data(
        self,
        start_date: Optional[str] = None,
        end_date: Optional[str] = None,
        company_id: Optional[int] = None,
    ) -> pd.DataFrame:
        """Load and aggregate historical inventory transactions.

        Args:
            start_date: Inclusive UTC date string (YYYY-MM-DD) to limit the history range. Defaults to 12 months
                prior to ``end_date`` when omitted.
            end_date: Inclusive UTC date string (YYYY-MM-DD) indicating the final day of history to ingest. Defaults
                to today when omitted.
            company_id: Tenant identifier used to scope queries. Required in multi-tenant production deployments.

        Returns:
            A pandas DataFrame with the schema ``[part_id:int, date:datetime64, quantity:float]`` where ``quantity``
            represents the net movement for that day (issues, returns, transfers, adjustments). Missing calendar days
            must be zero-filled per part to maintain continuous daily series.
        """
        engine = self._get_engine()
        table_name = self._sanitize_table_name(self._inventory_table)
        end_dt = self._coerce_date(end_date) or dt.date.today()
        start_dt = self._coerce_date(start_date) or (end_dt - dt.timedelta(days=365))

        if start_dt > end_dt:
            raise ValueError("start_date must be earlier than or equal to end_date")

        base_sql = f"""
            SELECT part_id,
                   DATE(transaction_date) AS txn_date,
                   SUM(quantity) AS quantity
            FROM {table_name}
            WHERE transaction_date BETWEEN :start_date AND :end_date
              AND transaction_type IN :transaction_types
        """

        params = {
            "start_date": start_dt,
            "end_date": end_dt,
            "transaction_types": self._DEFAULT_TRANSACTION_TYPES,
        }

        if company_id is not None:
            base_sql += " AND company_id = :company_id"
            params["company_id"] = company_id

        base_sql += """
            GROUP BY part_id, txn_date
            ORDER BY part_id, txn_date
        """

        statement = text(base_sql).bindparams(bindparam("transaction_types", expanding=True))

        try:
            df: DataFrame = pd.read_sql_query(statement, engine, params=params, parse_dates=["txn_date"])
        except SQLAlchemyError as exc:  # pragma: no cover - surfaced to caller for retry logic
            LOGGER.exception("Failed to load inventory data from %s", table_name)
            raise

        if df.empty:
            return pd.DataFrame(columns=["part_id", "date", "quantity"])

        df = df.rename(columns={"txn_date": "date"})
        df["date"] = pd.to_datetime(df["date"], utc=False).dt.normalize()
        df["quantity"] = df["quantity"].astype(float)

        parts = df["part_id"].unique()
        full_range = pd.date_range(start=start_dt, end=end_dt, freq="D")
        full_index = pd.MultiIndex.from_product((parts, full_range), names=("part_id", "date"))

        # Ensure every part_id has a continuous daily series so downstream models can resample safely.
        filled = (
            df.set_index(["part_id", "date"])
            .reindex(full_index, fill_value=0.0)
            .reset_index()
            .rename(columns={"level_0": "part_id", "level_1": "date"})
        )

        return filled[["part_id", "date", "quantity"]]

    def train_forecasting_models(self, data: pd.DataFrame, horizon: int) -> Dict[int, Dict[str, Dict[str, float]]]:
        """Train statistical and ML-based demand forecasting models for each part.

        Args:
            data: A tidy DataFrame with at least ``part_id``, ``date`` and ``quantity`` columns representing daily
                demand history.
            horizon: Number of future days each model should forecast.

        Returns:
            A dictionary keyed by ``part_id`` whose values contain metadata about the fitted models (e.g. serialized
            model handles and evaluation metrics such as MAE and MAPE). Exact structure is defined by downstream API
            consumers and should include enough detail to select the best-performing model when forecasting.
        """
        if horizon <= 0:
            raise ValueError("horizon must be a positive integer")

        required_columns = {"part_id", "date", "quantity"}
        missing = required_columns.difference(data.columns)
        if missing:
            raise ValueError(f"Missing required columns for forecasting: {', '.join(sorted(missing))}")

        normalized = data.copy()
        normalized["date"] = pd.to_datetime(normalized["date"], errors="coerce").dt.normalize()
        normalized = normalized.dropna(subset=["date"]).astype({"quantity": float})

        results: Dict[int, Dict[str, Dict[str, float]]] = {}
        for part_id, part_df in normalized.groupby("part_id"):
            series = (
                part_df.sort_values("date")
                .set_index("date")["quantity"]
                .resample("D")
                .sum()
                .astype(float)
            )
            if series.empty:
                continue

            validation_window = self._determine_validation_window(len(series), horizon)
            if len(series) <= validation_window + 5:
                LOGGER.warning(
                    "Skipping forecasting for part %s due to insufficient history (observations=%s)",
                    part_id,
                    len(series),
                )
                continue

            train_series = series.iloc[:-validation_window]
            val_series = series.iloc[-validation_window:]

            model_cards: Dict[str, Dict[str, Any]] = {}
            exp_result = self._evaluate_exponential_model(train_series, val_series, validation_window)
            if exp_result is not None:
                model_cards["exp_smoothing"] = exp_result

            rf_result = self._evaluate_random_forest_model(train_series, val_series, validation_window)
            if rf_result is not None:
                model_cards["random_forest"] = rf_result

            if not model_cards:
                LOGGER.warning("No forecasting models converged for part %s", part_id)
                continue

            best_model = self._select_best_model(model_cards)
            registry_entry = {
                "best_model": best_model,
                "models": model_cards,
                "trained_at": dt.datetime.utcnow().isoformat(),
                "horizon": horizon,
            }
            self._forecast_registry[int(part_id)] = registry_entry
            results[int(part_id)] = {
                "best_model": best_model,
                "metrics": {name: card["metrics"] for name, card in model_cards.items()},
            }

        return results

    def predict_demand(self, part_id: int, history: pd.Series, horizon: int) -> Dict[str, float]:
        """Forecast forward demand for a specific part.

        Args:
            part_id: Unique identifier of the inventory part to forecast.
            history: A pandas Series indexed by date with historical quantities used for inference.
            horizon: Number of days to project into the future.

        Returns:
            A dictionary payload that includes ``demand_qty`` (total expected units), ``avg_daily_demand``,
            ``reorder_point``, ``safety_stock`` and ``order_by_date`` (ISO date string). The consumer writes these
            fields into Laravel forecast snapshots.
        """
        if horizon <= 0:
            raise ValueError("horizon must be a positive integer")

        series = self._prepare_history_series(history)
        if series.empty:
            raise ValueError("history must contain at least one observation")

        registry_entry = self._forecast_registry.get(part_id, {})
        best_model = registry_entry.get("best_model", "exp_smoothing")
        model_config = registry_entry.get("models", {}).get(best_model, {}).get("config", {})

        forecast_series = self._forecast_with_model(best_model, series, horizon, model_config)
        fallback_model = False
        if forecast_series is None:
            forecast_series = self._naive_average_forecast(series, horizon)
            best_model = "moving_average"
            fallback_model = True

        demand_qty = float(forecast_series.sum())
        avg_daily_demand = demand_qty / float(horizon)
        safety_stock = self._compute_safety_stock(series)
        lead_time_days = int(model_config.get("lead_time_days", self._default_lead_time_days))
        lead_time_days = max(1, lead_time_days)
        reorder_point = avg_daily_demand * lead_time_days + safety_stock

        last_date = series.index.max().to_pydatetime().date()
        order_by_date = (last_date + dt.timedelta(days=max(1, lead_time_days - 1))).isoformat()

        if fallback_model:
            LOGGER.warning("Falling back to moving average forecast for part %s", part_id)

        return {
            "model": best_model,
            "demand_qty": demand_qty,
            "avg_daily_demand": avg_daily_demand,
            "reorder_point": reorder_point,
            "safety_stock": safety_stock,
            "order_by_date": order_by_date,
        }

    def load_supplier_data(
        self,
        start_date: Optional[str] = None,
        end_date: Optional[str] = None,
        company_id: Optional[int] = None,
    ) -> pd.DataFrame:
        """Assemble supplier performance features required for risk scoring.

        Args:
            start_date: Inclusive UTC date string for the first record to consider when computing metrics.
            end_date: Inclusive UTC date string for the final record window.
            company_id: Tenant scope identifier to prevent cross-company leakage.

        Returns:
            A DataFrame keyed by ``supplier_id`` with feature columns such as ``on_time_rate``, ``defect_rate``,
            ``lead_time_variance``, ``price_volatility`` and ``service_responsiveness``. Each column should be scaled
            and cleansed according to the deep spec so that downstream models receive normalized inputs.
        """
        engine = self._get_engine()
        end_dt = self._coerce_date(end_date) or dt.date.today()
        start_dt = self._coerce_date(start_date) or (end_dt - dt.timedelta(days=365))

        if start_dt > end_dt:
            raise ValueError("start_date must be earlier than or equal to end_date")

        deliveries_df = self._fetch_supplier_deliveries(engine, start_dt, end_dt, company_id)
        price_df = self._fetch_supplier_prices(engine, start_dt, end_dt, company_id)
        events_df = self._fetch_supplier_events(engine, start_dt, end_dt, company_id)

        if deliveries_df.empty and price_df.empty and events_df.empty:
            columns = [
                "supplier_id",
                "on_time_rate",
                "defect_rate",
                "lead_time_variance",
                "price_volatility",
                "service_responsiveness",
            ]
            return pd.DataFrame(columns=columns)

        supplier_ids: set[int] = set()
        if "supplier_id" in deliveries_df:
            supplier_ids.update(deliveries_df["supplier_id"].dropna().unique())
        if "supplier_id" in price_df:
            supplier_ids.update(price_df["supplier_id"].dropna().unique())
        if "supplier_id" in events_df:
            supplier_ids.update(events_df["supplier_id"].dropna().unique())
        supplier_ids = sorted(int(s) for s in supplier_ids if pd.notna(s))

        feature_rows: list[Dict[str, Optional[float]]] = []
        for supplier_id in supplier_ids:
            supplier_deliveries = deliveries_df[deliveries_df["supplier_id"] == supplier_id]
            supplier_prices = price_df[price_df["supplier_id"] == supplier_id]
            supplier_events = events_df[events_df["supplier_id"] == supplier_id]
            feature_rows.append(
                self._build_supplier_feature_row(
                    supplier_id,
                    supplier_deliveries,
                    supplier_prices,
                    supplier_events,
                )
            )

        return pd.DataFrame(feature_rows)

    def train_risk_model(self, supplier_df: pd.DataFrame) -> Tuple[object, Dict[str, float]]:
        """Fit and evaluate a supplier risk model.

        Args:
            supplier_df: Feature matrix containing supplier KPIs and the supervised label/score (if available).

        Returns:
            A tuple of ``(fitted_model, metrics)`` where ``fitted_model`` is the serialized estimator (e.g. gradient
            boosting classifier) and ``metrics`` is a dictionary of evaluation statistics such as ROC-AUC, accuracy and
            calibration values captured on a validation split.
        """
        if supplier_df.empty:
            raise ValueError("supplier_df cannot be empty")

        feature_candidates = [
            "on_time_rate",
            "defect_rate",
            "lead_time_variance",
            "price_volatility",
            "service_responsiveness",
        ]
        feature_columns = [col for col in feature_candidates if col in supplier_df]
        if not feature_columns:
            raise ValueError("supplier_df must include at least one KPI feature column")

        label_mode: Optional[str] = None
        label_column: Optional[str] = None
        if "risk_grade" in supplier_df:
            label_mode = "classification"
            label_column = "risk_grade"
        elif "overall_score" in supplier_df:
            label_mode = "regression"
            label_column = "overall_score"
        else:
            raise ValueError("supplier_df must include 'risk_grade' or 'overall_score' for supervision")

        feature_frame = supplier_df[feature_columns].apply(pd.to_numeric, errors="coerce")
        impute_values = feature_frame.median(numeric_only=True).to_dict()
        for column in feature_columns:
            fill_value = float(impute_values.get(column, 0.0))
            feature_frame[column] = feature_frame[column].fillna(fill_value)

        if label_mode == "classification":
            label_series = supplier_df[label_column].astype(str).str.lower()
            y = label_series.map(self._risk_label_map).to_numpy(dtype=float)
            valid_mask = ~np.isnan(y)
            y = y[valid_mask].astype(int)
        else:
            y = pd.to_numeric(supplier_df[label_column], errors="coerce").to_numpy(dtype=float)
            valid_mask = ~np.isnan(y)
            y = y[valid_mask]

        feature_frame = feature_frame.iloc[valid_mask]
        X = feature_frame.to_numpy(dtype=float)

        if X.size == 0 or y.size == 0:
            raise ValueError("Supplier supervision columns produced no valid rows")

        if label_mode == "classification" and len(np.unique(y)) <= 1:
            LOGGER.warning("Only one risk grade present; falling back to regression-style risk model")
            label_mode = "regression"
            y = y.astype(float) / max(1, max(self._risk_label_map.values()))

        n_samples = len(X)
        n_classes = len(np.unique(y)) if label_mode == "classification" else 1
        min_samples_for_validation = max(5, n_classes * 2)

        if n_samples >= min_samples_for_validation:
            stratify_labels: Optional[np.ndarray]
            if label_mode == "classification" and n_classes > 1:
                stratify_labels = y.copy()
                _, class_counts = np.unique(stratify_labels, return_counts=True)
                if class_counts.min() < 2:
                    LOGGER.warning(
                        "Insufficient samples per risk grade for stratified split; using unstratified data"
                    )
                    stratify_labels = None
            else:
                stratify_labels = None

            test_size = 0.2
            if label_mode == "classification":
                min_test_fraction = n_classes / n_samples
                test_size = min(0.5, max(0.2, min_test_fraction))

            try:
                X_train, X_val, y_train, y_val = train_test_split(
                    X,
                    y,
                    test_size=test_size,
                    random_state=42,
                    stratify=stratify_labels,
                )
            except ValueError:
                LOGGER.warning("Risk split fallback triggered; disabling stratification")
                X_train, X_val, y_train, y_val = train_test_split(
                    X,
                    y,
                    test_size=test_size,
                    random_state=42,
                    stratify=None,
                )
        else:
            X_train, X_val, y_train, y_val = X, X, y, y

        if label_mode == "classification":
            model: Any = GradientBoostingClassifier(random_state=42)
        else:
            model = GradientBoostingRegressor(random_state=42)

        model.fit(X_train, y_train)

        metrics: Dict[str, float]
        if X_val.size and len(X_val) > 0:
            y_pred = model.predict(X_val)
            if label_mode == "classification":
                metrics = {
                    "accuracy": float(accuracy_score(y_val, y_pred)),
                    "macro_f1": float(f1_score(y_val, y_pred, average="macro")),
                }
            else:
                metrics = {
                    "mae": float(mean_absolute_error(y_val, y_pred)),
                    "r2": float(r2_score(y_val, y_pred)),
                }
        else:
            metrics = {"accuracy": 1.0, "macro_f1": 1.0} if label_mode == "classification" else {"mae": 0.0, "r2": 1.0}

        self._risk_model = model
        self._risk_model_type = label_mode
        self._risk_model_features = feature_columns
        self._risk_feature_stats = {
            "impute_values": {col: float(impute_values.get(col, 0.0)) for col in feature_columns},
            "means": {col: float(feature_frame[col].mean()) for col in feature_columns},
        }

        return model, metrics

    def predict_supplier_risk(self, supplier_row: pd.Series) -> Dict[str, str]:
        """Score a single supplier and explain the driving factors.

        Args:
            supplier_row: A Series containing the feature vector expected by ``train_risk_model``.

        Returns:
            A dictionary with the predicted ``risk_category`` (``High``, ``Medium`` or ``Low``) and a human-readable
            ``explanation`` string describing the top contributing KPIs (e.g. late deliveries, volatility spikes,
            quality issues).
        """
        if self._risk_model is None or not self._risk_model_features:
            raise RuntimeError("Risk model not trained. Call train_risk_model() first.")

        supplier_series = supplier_row if isinstance(supplier_row, pd.Series) else pd.Series(supplier_row)
        feature_vector = self._prepare_supplier_feature_vector(supplier_series)

        if self._risk_model_type == "classification":
            prediction = int(self._risk_model.predict(feature_vector)[0])
            risk_category = self._risk_label_inverse.get(prediction, "medium").title()
            risk_score = self._classification_risk_score(feature_vector)
        else:
            score = float(self._risk_model.predict(feature_vector)[0])
            risk_score = score
            risk_category = self._score_to_category(score)

        explanation = self._build_risk_explanation(feature_vector)
        return {
            "risk_category": risk_category,
            "risk_score": float(risk_score),
            "explanation": explanation,
        }

    @staticmethod
    def _parse_event_types(config: Optional[str], default: Tuple[str, ...]) -> Tuple[str, ...]:
        if config:
            tokens = tuple(token.strip().lower() for token in config.split(",") if token.strip())
            if tokens:
                return tokens
        return tuple(value.lower() for value in default)

    @staticmethod
    def _parse_risk_thresholds(config: str) -> Tuple[float, float]:
        try:
            values = [float(part.strip()) for part in config.split(",") if part.strip()]
        except ValueError:
            values = []
        if len(values) < 2:
            return 0.4, 0.7
        low, high = values[:2]
        if low > high:
            low, high = high, low
        if low == high:
            high = low + 0.1
        return low, high

    def _prepare_supplier_feature_vector(self, supplier_row: pd.Series) -> np.ndarray:
        impute_values = self._risk_feature_stats.get("impute_values", {})
        values: list[float] = []
        for column in self._risk_model_features:
            raw_value = supplier_row.get(column)
            try:
                numeric_value = float(raw_value)
            except (TypeError, ValueError):
                numeric_value = np.nan
            if np.isnan(numeric_value):
                numeric_value = float(impute_values.get(column, 0.0))
            values.append(numeric_value)
        return np.array(values, dtype=float).reshape(1, -1)

    def _classification_risk_score(self, feature_vector: np.ndarray) -> float:
        model = self._risk_model
        if model is None:
            return 0.0
        if hasattr(model, "predict_proba"):
            probabilities = model.predict_proba(feature_vector)
            high_index = self._risk_label_map["high"]
            if probabilities.shape[1] > high_index:
                return float(probabilities[0][high_index])
            return float(probabilities[0].max())
        prediction = int(model.predict(feature_vector)[0])
        label_keys = list(self._risk_label_inverse.keys())
        max_label = max(label_keys) if label_keys else 1
        return float(prediction / max(1, max_label))

    def _build_risk_explanation(self, feature_vector: np.ndarray) -> str:
        model = self._risk_model
        if model is None:
            return "Risk score generated from supplier KPIs."
        importances = getattr(model, "feature_importances_", None)
        if importances is None or len(importances) == 0:
            return "Risk score generated from supplier KPIs."
        ranked = np.argsort(importances)[::-1][: min(3, len(importances))]
        means = self._risk_feature_stats.get("means", {})
        fragments = []
        for idx in ranked:
            column = self._risk_model_features[idx]
            value = float(feature_vector[0][idx])
            mean_value = float(means.get(column, 0.0))
            direction = "higher" if value >= mean_value else "lower"
            label = self._format_feature_label(column)
            fragments.append(f"{label} {direction} than avg ({value:.2f} vs {mean_value:.2f})")
        return "; ".join(fragments) or "Risk score generated from supplier KPIs."

    def _score_to_category(self, score: float) -> str:
        low, high = self._risk_thresholds
        if score <= low:
            return "Low"
        if score <= high:
            return "Medium"
        return "High"

    @staticmethod
    def _format_feature_label(column: str) -> str:
        return column.replace("_", " ").title()

    def compute_weekly_mape(self, actual: pd.Series, forecast: pd.Series) -> float:
        """Aggregate actual vs forecast demand weekly and compute MAPE."""
        actual_series = self._prepare_history_series(actual)
        forecast_series = self._prepare_history_series(forecast)

        if actual_series.empty or forecast_series.empty:
            return 0.0

        frame = (
            pd.DataFrame({"actual": actual_series, "forecast": forecast_series})
            .fillna(0.0)
            .resample("W")
            .sum()
        )

        if frame.empty:
            return 0.0

        denominator = frame["actual"].replace(0, 1e-6)
        mape = (frame["actual"] - frame["forecast"]).abs().div(denominator).mean()
        return float(mape)

    def compute_risk_distribution_drift(
        self,
        baseline: pd.Series,
        current: pd.Series,
        bins: int = 5,
    ) -> Dict[str, float]:
        """Compare supplier risk score distributions to detect drift."""
        baseline_scores = self._coerce_risk_scores(baseline)
        current_scores = self._coerce_risk_scores(current)

        if baseline_scores.size == 0 or current_scores.size == 0:
            return {"psi": 0.0, "mean_diff": 0.0, "std_diff": 0.0}

        psi = self._population_stability_index(baseline_scores, current_scores, bins=bins)
        mean_diff = float(current_scores.mean() - baseline_scores.mean())
        std_diff = float(current_scores.std(ddof=0) - baseline_scores.std(ddof=0))
        return {"psi": psi, "mean_diff": mean_diff, "std_diff": std_diff}

    def _coerce_risk_scores(self, data: pd.Series) -> np.ndarray:
        if not isinstance(data, pd.Series):
            data = pd.Series(data)
        series = data.copy()
        if series.dtype == object:
            series = series.astype(str).str.lower().map(self._risk_label_map).astype(float)
        else:
            series = pd.to_numeric(series, errors="coerce")
        series = series.dropna().astype(float)
        return series.to_numpy(dtype=float)

    @staticmethod
    def _population_stability_index(
        baseline: np.ndarray,
        current: np.ndarray,
        *,
        bins: int = 5,
    ) -> float:
        if baseline.size == 0 or current.size == 0:
            return 0.0

        combined = np.concatenate([baseline, current])
        min_value = float(np.min(combined))
        max_value = float(np.max(combined))

        if math.isclose(min_value, max_value, rel_tol=1e-9):
            return 0.0

        edges = np.linspace(min_value, max_value, bins + 1)
        baseline_hist, _ = np.histogram(baseline, bins=edges)
        current_hist, _ = np.histogram(current, bins=edges)

        baseline_pct = baseline_hist / baseline_hist.sum() if baseline_hist.sum() else baseline_hist
        current_pct = current_hist / current_hist.sum() if current_hist.sum() else current_hist

        epsilon = 1e-6
        psi = np.sum((current_pct - baseline_pct) * np.log((current_pct + epsilon) / (baseline_pct + epsilon)))
        return float(psi)

    def _build_supplier_feature_row(
        self,
        supplier_id: int,
        deliveries: DataFrame,
        prices: DataFrame,
        events: DataFrame,
    ) -> Dict[str, Optional[float]]:
        return {
            "supplier_id": int(supplier_id),
            "on_time_rate": self._compute_on_time_rate(deliveries),
            "defect_rate": self._compute_defect_rate(deliveries),
            "lead_time_variance": self._compute_lead_time_variance(deliveries),
            "price_volatility": self._compute_price_volatility(prices),
            "service_responsiveness": self._compute_service_responsiveness(events),
        }

    @staticmethod
    def _compute_on_time_rate(deliveries: DataFrame) -> Optional[float]:
        if deliveries.empty:
            return None
        subset = deliveries.dropna(subset=["promised_date", "received_at"])
        if subset.empty:
            return None
        on_time = (subset["received_at"] <= subset["promised_date"]).astype(float)
        return float(on_time.mean())

    @staticmethod
    def _compute_defect_rate(deliveries: DataFrame) -> Optional[float]:
        if deliveries.empty:
            return None
        received = float(deliveries["received_qty"].sum())
        rejected = float(deliveries["rejected_qty"].sum())
        if received <= 0:
            return None
        return rejected / received

    @staticmethod
    def _compute_lead_time_variance(deliveries: DataFrame) -> Optional[float]:
        if deliveries.empty:
            return None
        lead_days = (deliveries["received_at"] - deliveries["po_created_at"]).dt.days.dropna()
        if lead_days.empty:
            return None
        if len(lead_days) == 1:
            return 0.0
        return float(lead_days.std(ddof=0))

    @staticmethod
    def _compute_price_volatility(prices: DataFrame) -> Optional[float]:
        if prices.empty:
            return None
        price_series = prices.sort_values("priced_at")["unit_price"].astype(float)
        if price_series.count() <= 1:
            return 0.0
        pct_changes = price_series.pct_change().dropna()
        if pct_changes.empty:
            mean_price = price_series.mean()
            if mean_price == 0:
                return 0.0
            return float(price_series.std(ddof=0) / mean_price)
        return float(pct_changes.std(ddof=0))

    def _compute_service_responsiveness(self, events: DataFrame) -> Optional[float]:
        if events.empty:
            return None
        response_durations: list[float] = []
        events = events.sort_values("occurred_at")
        for _, po_group in events.groupby("purchase_order_id"):
            send_times = po_group[po_group["event_type"].isin(self._po_send_events)]["occurred_at"].tolist()
            response_times = po_group[po_group["event_type"].isin(self._po_response_events)]["occurred_at"].tolist()
            if not send_times or not response_times:
                continue
            for send_time in send_times:
                candidates = [ts for ts in response_times if ts >= send_time]
                if not candidates:
                    continue
                delta_hours = (min(candidates) - send_time).total_seconds() / 3600.0
                if delta_hours >= 0:
                    response_durations.append(delta_hours)
        if not response_durations:
            return None
        within_sla = sum(1 for value in response_durations if value <= self._response_sla_hours)
        return float(within_sla / len(response_durations))

    def _fetch_supplier_deliveries(
        self,
        engine: Engine,
        start_dt: dt.date,
        end_dt: dt.date,
        company_id: Optional[int],
    ) -> DataFrame:
        sql = """
            SELECT
                po.supplier_id,
                po.id AS purchase_order_id,
                po.created_at AS po_created_at,
                pol.delivery_date AS promised_date,
                pol.created_at AS line_created_at,
                COALESCE(grn.inspected_at, grn.created_at) AS received_at,
                grl.received_qty,
                grl.rejected_qty
            FROM po_lines AS pol
            INNER JOIN purchase_orders AS po ON po.id = pol.purchase_order_id
            INNER JOIN goods_receipt_lines AS grl ON grl.purchase_order_line_id = pol.id
            INNER JOIN goods_receipt_notes AS grn ON grn.id = grl.goods_receipt_note_id
            WHERE COALESCE(grn.inspected_at, grn.created_at) BETWEEN :start_date AND :end_date
              AND grl.deleted_at IS NULL
              AND grn.deleted_at IS NULL
              AND po.deleted_at IS NULL
        """
        params: Dict[str, object] = {
            "start_date": start_dt,
            "end_date": end_dt + dt.timedelta(days=1),
        }
        if company_id is not None:
            sql += " AND po.company_id = :company_id"
            params["company_id"] = company_id

        statement = text(sql)
        try:
            deliveries = pd.read_sql_query(
                statement,
                engine,
                params=params,
                parse_dates=["po_created_at", "promised_date", "line_created_at", "received_at"],
            )
        except SQLAlchemyError:
            LOGGER.exception("Failed to load supplier delivery data")
            raise

        if deliveries.empty:
            return deliveries

        deliveries["promised_date"] = pd.to_datetime(deliveries["promised_date"], errors="coerce").dt.normalize()
        deliveries["received_at"] = (
            pd.to_datetime(deliveries["received_at"], errors="coerce").dt.tz_localize(None)
        )
        deliveries["po_created_at"] = (
            pd.to_datetime(deliveries["po_created_at"], errors="coerce").dt.tz_localize(None)
        )
        deliveries["received_qty"] = deliveries["received_qty"].fillna(0).astype(float)
        deliveries["rejected_qty"] = deliveries["rejected_qty"].fillna(0).astype(float)
        return deliveries

    def _fetch_supplier_prices(
        self,
        engine: Engine,
        start_dt: dt.date,
        end_dt: dt.date,
        company_id: Optional[int],
    ) -> DataFrame:
        sql = """
            SELECT
                po.supplier_id,
                po.id AS purchase_order_id,
                pol.unit_price,
                pol.created_at AS priced_at
            FROM po_lines AS pol
            INNER JOIN purchase_orders AS po ON po.id = pol.purchase_order_id
            WHERE pol.created_at BETWEEN :start_date AND :end_date
              AND po.deleted_at IS NULL
        """
        params: Dict[str, object] = {
            "start_date": start_dt,
            "end_date": end_dt + dt.timedelta(days=1),
        }
        if company_id is not None:
            sql += " AND po.company_id = :company_id"
            params["company_id"] = company_id

        statement = text(sql)
        try:
            prices = pd.read_sql_query(statement, engine, params=params, parse_dates=["priced_at"])
        except SQLAlchemyError:
            LOGGER.exception("Failed to load supplier pricing data")
            raise

        if prices.empty:
            return prices

        prices["unit_price"] = prices["unit_price"].astype(float)
        prices["priced_at"] = pd.to_datetime(prices["priced_at"], errors="coerce").dt.tz_localize(None)
        return prices

    def _fetch_supplier_events(
        self,
        engine: Engine,
        start_dt: dt.date,
        end_dt: dt.date,
        company_id: Optional[int],
    ) -> DataFrame:
        sql = """
            SELECT
                po.supplier_id,
                events.purchase_order_id,
                events.event_type,
                events.occurred_at
            FROM purchase_order_events AS events
            INNER JOIN purchase_orders AS po ON po.id = events.purchase_order_id
            WHERE events.occurred_at IS NOT NULL
              AND events.occurred_at BETWEEN :start_date AND :end_date
              AND po.deleted_at IS NULL
        """
        params: Dict[str, object] = {
            "start_date": start_dt,
            "end_date": end_dt + dt.timedelta(days=1),
        }
        if company_id is not None:
            sql += " AND po.company_id = :company_id"
            params["company_id"] = company_id

        statement = text(sql)
        try:
            events = pd.read_sql_query(statement, engine, params=params, parse_dates=["occurred_at"])
        except SQLAlchemyError:
            LOGGER.exception("Failed to load supplier event data")
            raise

        if events.empty:
            return events

        events["event_type"] = events["event_type"].astype(str).str.lower()
        events["occurred_at"] = (
            pd.to_datetime(events["occurred_at"], errors="coerce").dt.tz_localize(None)
        )
        return events

    @staticmethod
    def _determine_validation_window(history_len: int, horizon: int) -> int:
        baseline = max(horizon, 14)
        window = min(baseline, max(1, history_len // 5))
        if window >= history_len:
            window = max(1, history_len // 2)
        return max(1, window)

    def _evaluate_exponential_model(
        self,
        train_series: pd.Series,
        val_series: pd.Series,
        steps: int,
    ) -> Optional[Dict[str, Any]]:
        if train_series.empty:
            return None
        seasonal_periods = min(14, max(2, len(train_series) // 4))
        seasonal_component = "add" if seasonal_periods >= 2 else None
        trend_component = "add" if len(train_series) >= 4 else None
        try:
            model = ExponentialSmoothing(
                train_series,
                trend=trend_component,
                seasonal=seasonal_component,
                seasonal_periods=seasonal_periods if seasonal_component else None,
            ).fit()
            raw_forecast = model.forecast(steps)
        except (ValueError, RuntimeError) as exc:
            LOGGER.debug("Exponential smoothing failed: %s", exc)
            return None

        forecast_series = self._series_from_forecast(raw_forecast, val_series.index)
        metrics = self._compute_forecast_metrics(val_series, forecast_series)
        return {
            "config": {
                "trend": trend_component,
                "seasonal": seasonal_component,
                "seasonal_periods": seasonal_periods if seasonal_component else None,
            },
            "metrics": metrics,
        }

    def _evaluate_random_forest_model(
        self,
        train_series: pd.Series,
        val_series: pd.Series,
        steps: int,
    ) -> Optional[Dict[str, Any]]:
        if train_series.empty:
            return None
        max_lag = min(21, max(3, len(train_series) // 10))
        max_lag = min(max_lag, len(train_series) - 1)
        if max_lag < 3:
            return None

        lag_matrix = self._build_lag_matrix(train_series, max_lag)
        if lag_matrix is None:
            return None
        X_train, y_train = lag_matrix

        model = RandomForestRegressor(
            n_estimators=200,
            random_state=42,
        )
        model.fit(X_train, y_train)

        forecast_series = self._recursive_random_forest_forecast(model, train_series, steps, max_lag)
        if forecast_series is None:
            return None

        forecast_series = self._series_from_forecast(forecast_series.values, val_series.index)
        metrics = self._compute_forecast_metrics(val_series, forecast_series)
        return {
            "config": {
                "max_lag": max_lag,
                "n_estimators": 200,
            },
            "metrics": metrics,
        }

    @staticmethod
    def _select_best_model(model_cards: Dict[str, Dict[str, Any]]) -> str:
        def score(card: Dict[str, Any]) -> Tuple[float, float]:
            metrics = card.get("metrics", {})
            return (
                float(metrics.get("mape", float("inf"))),
                float(metrics.get("mae", float("inf"))),
            )

        return min(model_cards.items(), key=lambda item: score(item[1]))[0]

    def _prepare_history_series(self, history: pd.Series) -> pd.Series:
        series = history if isinstance(history, pd.Series) else pd.Series(history)
        if not isinstance(series.index, pd.DatetimeIndex):
            series.index = pd.to_datetime(series.index, errors="coerce")
        series = series.dropna()
        if series.empty:
            return series
        series = series.sort_index()
        series = series.resample("D").sum().astype(float)
        return series

    def _forecast_with_model(
        self,
        model_name: str,
        series: pd.Series,
        horizon: int,
        config: Dict[str, Any],
    ) -> Optional[pd.Series]:
        if model_name == "random_forest":
            return self._forecast_random_forest(series, horizon, config)
        return self._forecast_exponential(series, horizon, config)

    def _forecast_exponential(
        self,
        series: pd.Series,
        horizon: int,
        config: Dict[str, Any],
    ) -> Optional[pd.Series]:
        if series.empty:
            return None
        trend = config.get("trend")
        seasonal = config.get("seasonal")
        seasonal_periods = config.get("seasonal_periods")
        if seasonal_periods is None:
            seasonal_periods = min(14, max(2, len(series) // 4))
            if seasonal_periods < 2:
                seasonal = None
        try:
            model = ExponentialSmoothing(
                series,
                trend=trend,
                seasonal=seasonal,
                seasonal_periods=seasonal_periods if seasonal else None,
            ).fit()
            raw_forecast = model.forecast(horizon)
        except (ValueError, RuntimeError) as exc:
            LOGGER.debug("Exponential smoothing forecast failed: %s", exc)
            return None

        future_index = self._future_index(series.index, horizon)
        return self._series_from_forecast(raw_forecast, future_index)

    def _forecast_random_forest(
        self,
        series: pd.Series,
        horizon: int,
        config: Dict[str, Any],
    ) -> Optional[pd.Series]:
        if series.empty:
            return None
        max_lag = int(config.get("max_lag", min(14, len(series) - 1)))
        max_lag = min(max_lag, len(series) - 1)
        max_lag = max(2, max_lag)

        lag_matrix = self._build_lag_matrix(series, max_lag)
        if lag_matrix is None:
            return None
        X_train, y_train = lag_matrix
        model = RandomForestRegressor(
            n_estimators=int(config.get("n_estimators", 200)),
            random_state=42,
        )
        model.fit(X_train, y_train)

        forecast_series = self._recursive_random_forest_forecast(model, series, horizon, max_lag)
        return forecast_series

    def _naive_average_forecast(self, series: pd.Series, horizon: int) -> pd.Series:
        if series.empty:
            future_index = pd.date_range(start=dt.date.today(), periods=horizon, freq="D")
            return self._series_from_forecast([0.0] * horizon, future_index)
        window = series.tail(min(30, len(series)))
        avg = float(window.mean()) if not window.empty else 0.0
        future_index = self._future_index(series.index, horizon)
        return self._series_from_forecast([avg] * horizon, future_index)

    @staticmethod
    def _compute_safety_stock(series: pd.Series) -> float:
        if series.empty:
            return 0.0
        window = series.tail(min(30, len(series)))
        std_dev = float(window.std(ddof=0)) if not window.empty else 0.0
        if np.isnan(std_dev):
            return 0.0
        return max(0.0, std_dev * 1.65)

    @staticmethod
    def _future_index(index: pd.DatetimeIndex, horizon: int) -> pd.DatetimeIndex:
        start = index.max() + pd.Timedelta(days=1)
        return pd.date_range(start=start, periods=horizon, freq="D")

    @staticmethod
    def _series_from_forecast(values: Any, index: pd.Index) -> pd.Series:
        array = np.asarray(values, dtype=float)
        if array.size == 0:
            array = np.zeros(len(index), dtype=float)
        if array.size < len(index):
            pad_value = array[-1] if array.size else 0.0
            pad_length = len(index) - array.size
            array = np.concatenate([array, np.full(pad_length, pad_value, dtype=float)])
        array = array[: len(index)]
        array = np.clip(array, a_min=0.0, a_max=None)
        return pd.Series(array, index=index)

    @staticmethod
    def _build_lag_matrix(series: pd.Series, max_lag: int) -> Optional[Tuple[np.ndarray, np.ndarray]]:
        if len(series) <= max_lag:
            return None
        frame = pd.DataFrame({"target": series})
        for lag in range(1, max_lag + 1):
            frame[f"lag_{lag}"] = series.shift(lag)
        frame = frame.dropna()
        if frame.empty:
            return None
        feature_cols = [f"lag_{lag}" for lag in range(1, max_lag + 1)]
        X = frame[feature_cols].to_numpy(dtype=float)
        y = frame["target"].to_numpy(dtype=float)
        return X, y

    def _recursive_random_forest_forecast(
        self,
        model: RandomForestRegressor,
        series: pd.Series,
        steps: int,
        max_lag: int,
    ) -> Optional[pd.Series]:
        if steps <= 0:
            return None
        history = list(series.astype(float).values)
        if not history:
            return None
        predictions: list[float] = []
        for _ in range(steps):
            if len(history) < max_lag:
                pad_value = history[-1]
                padded = [pad_value] * (max_lag - len(history)) + history
            else:
                padded = history[-max_lag:]
            feature_values = [padded[-i] for i in range(1, max_lag + 1)]
            feature_vector = np.array(feature_values, dtype=float).reshape(1, -1)
            prediction = float(model.predict(feature_vector)[0])
            prediction = max(0.0, prediction)
            predictions.append(prediction)
            history.append(prediction)
        future_index = self._future_index(series.index, steps)
        return pd.Series(predictions, index=future_index)

    @staticmethod
    def _compute_forecast_metrics(actual: pd.Series, forecast: pd.Series) -> Dict[str, float]:
        actual_values = actual.astype(float).to_numpy()
        forecast_values = forecast.astype(float).to_numpy()
        if actual_values.size == 0:
            return {"mae": 0.0, "mape": 0.0}
        mae = float(np.mean(np.abs(actual_values - forecast_values)))
        denominator = np.where(actual_values == 0, 1e-6, actual_values)
        mape = float(np.mean(np.abs((actual_values - forecast_values) / denominator)))
        return {"mae": mae, "mape": mape}

    def _get_engine(self) -> Engine:
        if not self._db_url:
            raise RuntimeError(
                "Database URL not configured. Set AI_SERVICE_DATABASE_URL or pass db_url to AISupplyService."
            )
        if self._engine is None:
            self._engine = create_engine(self._db_url, pool_pre_ping=True, future=True)
        return self._engine

    @staticmethod
    def _coerce_date(value: Optional[str]) -> Optional[dt.date]:
        if value is None:
            return None
        return dt.date.fromisoformat(value)

    @staticmethod
    def _sanitize_table_name(table_name: str) -> str:
        candidate = table_name.strip()
        if not candidate.replace("_", "").isalnum():  # basic guardrail against SQL injection via env vars
            raise ValueError("Invalid inventory table name configured")
        return candidate
