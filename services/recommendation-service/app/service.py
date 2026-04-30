from __future__ import annotations

import importlib.util
import json
import os
import sys
from dataclasses import dataclass
from datetime import UTC, datetime
from pathlib import Path
from threading import RLock
from time import perf_counter
from typing import Any

import joblib
import pandas as pd

from .clients import (
    AuthServiceClient,
    BookingServiceClient,
    EventServiceClient,
    UpstreamServiceError,
)
from .config import Settings
from .schemas import UserContext


@dataclass
class DatasetBundle:
    events: pd.DataFrame
    users: pd.DataFrame
    interactions: pd.DataFrame


class RecommendationEngine:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self._lock = RLock()
        self._model: Any | None = None
        self._dataset_cache: dict[str, tuple[int, pd.DataFrame]] = {}
        self._live_dataset_bundle: DatasetBundle | None = None
        self._live_dataset_synced_at: datetime | None = None
        self._event_service_client = EventServiceClient(settings)
        self._booking_service_client = BookingServiceClient(settings)
        self._auth_service_client = AuthServiceClient(settings)

    def ensure_ready(self) -> None:
        with self._lock:
            self._ensure_workspace_exists()
            self._ensure_workspace_module("matrix_factorization")

            if not self.settings.use_live_data:
                if not self.settings.events_path.exists() or not self.settings.interactions_path.exists():
                    if not self.settings.allow_synthetic_generation:
                        raise FileNotFoundError("ML dataset files are missing.")

                    self.generate_synthetic_data()
            else:
                self._load_datasets(force=False, persist_live_snapshot=self.settings.persist_live_datasets)

            if not self.settings.model_path.exists():
                if not self.settings.auto_train_if_missing:
                    raise FileNotFoundError("Trained recommendation model is missing.")

                self.train(regenerate_data=False)
            else:
                self._load_model(force=True)

    def health_snapshot(self) -> dict[str, Any]:
        try:
            datasets = self._load_datasets()
            dataset_payload = {
                "events": len(datasets.events),
                "users": len(datasets.users),
                "interactions": len(datasets.interactions),
            }
        except (FileNotFoundError, UpstreamServiceError):
            dataset_payload = {
                "events": 0,
                "users": 0,
                "interactions": 0,
            }

        return {
            "status": "ok",
            "app": self.settings.app_name,
            "model_loaded": self._model is not None,
            "workspace": str(self.settings.ml_workspace),
            "datasets": dataset_payload,
            "data_source": self.settings.data_source,
        }

    def recommend(
        self,
        user_id: int,
        limit: int,
        user_context: UserContext | None = None,
        reference_date: datetime | None = None,
        authorization_header: str | None = None,
    ) -> dict[str, Any]:
        self.ensure_ready()

        with self._lock:
            model = self._load_model()
            datasets = self._load_datasets()
            normalized_limit = self._normalize_limit(limit)
            base_context = user_context or UserContext()
            resolved_context = self._resolve_runtime_user_context(
                user_id=user_id,
                base_context=base_context,
                authorization_header=authorization_header,
            )

            items = model.recommend(
                user_id=user_id,
                events=datasets.events,
                interactions=datasets.interactions,
                limit=normalized_limit,
                user_context=resolved_context.model_dump(),
                reference_date=reference_date.isoformat() if reference_date else None,
            )

            return {
                "user_id": user_id,
                "limit": normalized_limit,
                "items": items,
                "used_context": resolved_context.model_dump(),
            }

    def append_interaction(
        self,
        user_id: int,
        event_id: int,
        action: str,
        rating: float | None = None,
        created_at: datetime | None = None,
    ) -> dict[str, Any]:
        self.ensure_ready()

        with self._lock:
            interactions = self._read_csv(
                path=self.settings.interactions_path,
                cache_key="interactions",
            )
            next_id = int(interactions["id"].max()) + 1 if not interactions.empty else 1
            timestamp = (created_at or datetime.now(UTC)).strftime("%Y-%m-%d %H:%M:%S")

            next_row = pd.DataFrame(
                [
                    {
                        "id": next_id,
                        "user_id": user_id,
                        "event_id": event_id,
                        "action": action,
                        "rating": "" if rating is None else rating,
                        "created_at": timestamp,
                    }
                ]
            )

            updated = pd.concat([interactions, next_row], ignore_index=True)
            updated.to_csv(self.settings.interactions_path, index=False, encoding="utf-8")
            self._dataset_cache["interactions"] = (
                self.settings.interactions_path.stat().st_mtime_ns,
                updated,
            )

            if self.settings.use_live_data:
                message = (
                    "Interaction saved to the local workspace snapshot. "
                    "Live recommendation signals still come from booking-service."
                )
            else:
                message = "Interaction saved. Retrain the model when you want fresh recommendations."

            return {
                "message": message,
                "interaction_id": next_id,
                "total_interactions": len(updated),
            }

    def train(self, regenerate_data: bool = False) -> dict[str, Any]:
        with self._lock:
            self._ensure_workspace_exists()
            self._ensure_workspace_module("matrix_factorization")

            if regenerate_data and not self.settings.use_live_data:
                self.generate_synthetic_data()

            datasets = self._load_datasets(
                force=True,
                persist_live_snapshot=True,
            )
            model_class = self._get_model_class()
            model = model_class()

            started_at = perf_counter()
            model.auto_fit(
                interactions=datasets.interactions,
                events=datasets.events,
            )
            duration_seconds = round(perf_counter() - started_at, 2)

            self.settings.model_path.parent.mkdir(parents=True, exist_ok=True)
            joblib.dump(model, self.settings.model_path)
            self._model = model

            payload = {
                "best_params": getattr(model, "best_params", None),
                "best_metrics": getattr(model, "best_metrics", None),
            }

            self.settings.training_metrics_path.parent.mkdir(parents=True, exist_ok=True)
            self.settings.training_metrics_path.write_text(
                json.dumps(payload, ensure_ascii=False, indent=2),
                encoding="utf-8",
            )

            return {
                "message": f"Model trained successfully in {duration_seconds} seconds.",
                **payload,
            }

    def get_training_metrics(self) -> dict[str, Any]:
        if not self.settings.training_metrics_path.exists():
            return {
                "best_params": None,
                "best_metrics": None,
            }

        return json.loads(self.settings.training_metrics_path.read_text(encoding="utf-8"))

    def generate_synthetic_data(self) -> None:
        generate_data_module = self._ensure_workspace_module("generate_data")

        if not hasattr(generate_data_module, "main"):
            raise RuntimeError("generate_data.py does not expose a main() function.")

        previous_working_directory = Path.cwd()

        try:
            os.chdir(self.settings.ml_workspace)
            generate_data_module.main()
        finally:
            os.chdir(previous_working_directory)

        self._dataset_cache.clear()

    def _load_model(self, force: bool = False):
        if self._model is not None and not force:
            return self._model

        self._ensure_workspace_module("matrix_factorization")
        self._model = joblib.load(self.settings.model_path)
        return self._model

    def _load_datasets(
        self,
        force: bool = False,
        persist_live_snapshot: bool = False,
    ) -> DatasetBundle:
        if self.settings.use_live_data:
            try:
                return self._load_live_datasets(
                    force=force,
                    persist_snapshot=persist_live_snapshot or self.settings.persist_live_datasets,
                )
            except (FileNotFoundError, UpstreamServiceError):
                if self.settings.data_source == "live":
                    raise

        return self._load_local_datasets(force=force)

    def _load_local_datasets(self, force: bool = False) -> DatasetBundle:
        events = self._read_csv(
            path=self.settings.events_path,
            cache_key="events",
            force=force,
        )
        users = self._read_csv(
            path=self.settings.users_path,
            cache_key="users",
            force=force,
        ) if self.settings.users_path.exists() else pd.DataFrame(columns=["id"])
        interactions = self._read_csv(
            path=self.settings.interactions_path,
            cache_key="interactions",
            force=force,
        )

        return DatasetBundle(events=events, users=users, interactions=interactions)

    def _load_live_datasets(
        self,
        force: bool = False,
        persist_snapshot: bool = False,
    ) -> DatasetBundle:
        if not force and self._live_dataset_bundle is not None and self._live_dataset_synced_at is not None:
            age_seconds = (datetime.now(UTC) - self._live_dataset_synced_at).total_seconds()

            if age_seconds <= self.settings.live_data_cache_seconds:
                return self._live_dataset_bundle

        events_payload = self._event_service_client.fetch_recommendation_events()
        event_interactions_payload = self._event_service_client.fetch_recommendation_interactions()
        booking_interactions_payload = self._booking_service_client.fetch_recommendation_interactions()
        interactions_payload = self._merge_interaction_payloads(
            event_interactions_payload,
            booking_interactions_payload,
        )

        events = self._build_events_frame(events_payload)
        interactions = self._build_interactions_frame(interactions_payload)
        users = self._derive_users_frame(interactions)

        bundle = DatasetBundle(events=events, users=users, interactions=interactions)
        self._live_dataset_bundle = bundle
        self._live_dataset_synced_at = datetime.now(UTC)

        if persist_snapshot:
            self._persist_dataset_bundle(bundle)

        return bundle

    def _persist_dataset_bundle(self, bundle: DatasetBundle) -> None:
        self.settings.events_path.parent.mkdir(parents=True, exist_ok=True)
        self.settings.users_path.parent.mkdir(parents=True, exist_ok=True)
        self.settings.interactions_path.parent.mkdir(parents=True, exist_ok=True)

        bundle.events.to_csv(self.settings.events_path, index=False, encoding="utf-8")
        bundle.users.to_csv(self.settings.users_path, index=False, encoding="utf-8")
        bundle.interactions.to_csv(self.settings.interactions_path, index=False, encoding="utf-8")

        self._dataset_cache["events"] = (
            self.settings.events_path.stat().st_mtime_ns,
            bundle.events,
        )
        self._dataset_cache["users"] = (
            self.settings.users_path.stat().st_mtime_ns,
            bundle.users,
        )
        self._dataset_cache["interactions"] = (
            self.settings.interactions_path.stat().st_mtime_ns,
            bundle.interactions,
        )

    def _build_events_frame(self, payload: list[dict[str, Any]]) -> pd.DataFrame:
        rows = []

        for raw_event in payload:
            event_id = int(raw_event["id"])
            row = {
                "id": event_id,
                "title": str(raw_event.get("title", "")),
                "category": str(raw_event.get("category", "event")),
                "category_name": raw_event.get("category_name"),
                "tags": str(raw_event.get("tags", "")),
                "description": str(raw_event.get("description", "")),
                "poster_url": raw_event.get("poster_url"),
                "age_rating": int(raw_event.get("age_rating", 0) or 0),
                "price": int(raw_event.get("price", 0) or 0),
                "event_date": str(raw_event.get("event_date", "")),
                "status": str(raw_event.get("status", "published")),
                "city": raw_event.get("city"),
                "available_tickets": raw_event.get("available_tickets"),
                "next_session_id": raw_event.get("next_session_id"),
                "venue_address": raw_event.get("venue_address"),
                "hall_id": raw_event.get("hall_id"),
                "hall_name": raw_event.get("hall_name"),
            }
            rows.append(row)

        return pd.DataFrame(rows)

    def _build_interactions_frame(self, payload: list[dict[str, Any]]) -> pd.DataFrame:
        rows = []

        for raw_interaction in payload:
            rows.append(
                {
                    "id": int(raw_interaction["id"]),
                    "user_id": int(raw_interaction["user_id"]),
                    "event_id": int(raw_interaction["event_id"]),
                    "action": str(raw_interaction["action"]),
                    "rating": raw_interaction.get("rating", ""),
                    "created_at": str(raw_interaction.get("created_at", "")),
                }
            )

        return pd.DataFrame(rows)

    def _merge_interaction_payloads(
        self,
        *payload_groups: list[dict[str, Any]],
    ) -> list[dict[str, Any]]:
        merged: list[dict[str, Any]] = []

        for payload in payload_groups:
            for raw_interaction in payload:
                merged.append(
                    {
                        "id": raw_interaction.get("id"),
                        "user_id": raw_interaction.get("user_id"),
                        "event_id": raw_interaction.get("event_id"),
                        "action": raw_interaction.get("action"),
                        "rating": raw_interaction.get("rating", ""),
                        "created_at": raw_interaction.get("created_at", ""),
                    }
                )

        merged.sort(key=lambda item: str(item.get("created_at", "")))

        for index, item in enumerate(merged, start=1):
            item["id"] = index

        return merged

    def _derive_users_frame(self, interactions: pd.DataFrame) -> pd.DataFrame:
        if interactions.empty:
            return pd.DataFrame(columns=["id"])

        unique_user_ids = sorted({int(user_id) for user_id in interactions["user_id"].tolist()})

        return pd.DataFrame([{"id": user_id} for user_id in unique_user_ids])

    def _resolve_runtime_user_context(
        self,
        user_id: int,
        base_context: UserContext,
        authorization_header: str | None,
    ) -> UserContext:
        context = base_context.model_copy(deep=True)

        if not self.settings.use_live_data:
            return context

        try:
            auth_user = self._auth_service_client.get_current_user(authorization_header)
        except UpstreamServiceError:
            return context

        if auth_user is None:
            return context

        if int(auth_user.get("id", 0) or 0) != user_id:
            return context

        if context.max_age_rating is None:
            context.max_age_rating = self._resolve_max_age_rating(auth_user.get("birth_date"))

        return context

    def _resolve_max_age_rating(self, birth_date: Any) -> int | None:
        if not isinstance(birth_date, str) or birth_date.strip() == "":
            return None

        try:
            parsed_birth_date = datetime.fromisoformat(birth_date)
        except ValueError:
            try:
                parsed_birth_date = datetime.strptime(birth_date, "%Y-%m-%d")
            except ValueError:
                return None

        today = datetime.now(UTC).date()
        age = today.year - parsed_birth_date.date().year

        if (today.month, today.day) < (parsed_birth_date.date().month, parsed_birth_date.date().day):
            age -= 1

        for threshold in (18, 16, 12, 6, 0):
            if age >= threshold:
                return threshold

        return 0

    def _read_csv(self, path: Path, cache_key: str, force: bool = False) -> pd.DataFrame:
        if not path.exists():
            raise FileNotFoundError(f"Required dataset file is missing: {path}")

        modified_at = path.stat().st_mtime_ns

        if not force and cache_key in self._dataset_cache:
            cached_mtime, cached_frame = self._dataset_cache[cache_key]
            if cached_mtime == modified_at:
                return cached_frame.copy()

        frame = pd.read_csv(path)
        self._dataset_cache[cache_key] = (modified_at, frame)
        return frame.copy()

    def _ensure_workspace_exists(self) -> None:
        if not self.settings.ml_workspace.exists():
            raise FileNotFoundError(
                f"ML workspace does not exist: {self.settings.ml_workspace}"
            )

    def _ensure_workspace_module(self, module_name: str):
        module_path = self.settings.ml_workspace / f"{module_name}.py"

        if not module_path.exists():
            raise FileNotFoundError(f"Expected module file is missing: {module_path}")

        existing_module = sys.modules.get(module_name)
        if existing_module is not None:
            existing_file = getattr(existing_module, "__file__", None)
            if existing_file and Path(existing_file).resolve() == module_path.resolve():
                return existing_module

        spec = importlib.util.spec_from_file_location(module_name, module_path)
        if spec is None or spec.loader is None:
            raise ImportError(f"Could not create module spec for {module_path}")

        module = importlib.util.module_from_spec(spec)
        sys.modules[module_name] = module
        spec.loader.exec_module(module)
        return module

    def _get_model_class(self):
        module = self._ensure_workspace_module("matrix_factorization")

        if not hasattr(module, "MatrixFactorizationRecommender"):
            raise RuntimeError(
                "matrix_factorization.py does not expose MatrixFactorizationRecommender"
            )

        return module.MatrixFactorizationRecommender

    def _normalize_limit(self, limit: int) -> int:
        return max(1, min(limit, self.settings.max_limit))
