from __future__ import annotations

import json
import os
import shutil
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import joblib
import pandas as pd
from fastapi.testclient import TestClient


ROOT_DIR = Path(__file__).resolve().parents[3]
SERVICE_DIR = ROOT_DIR / "services" / "recommendation-service"
ML_SOURCE_DIR = ROOT_DIR / "ml_recommended"

if str(SERVICE_DIR) not in sys.path:
    sys.path.insert(0, str(SERVICE_DIR))


class RecommendationApiTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp_dir = Path(tempfile.mkdtemp(prefix="recommendation-service-test-"))
        shutil.copy(ML_SOURCE_DIR / "matrix_factorization.py", self.temp_dir / "matrix_factorization.py")
        (self.temp_dir / "data").mkdir(parents=True, exist_ok=True)
        (self.temp_dir / "models").mkdir(parents=True, exist_ok=True)

        events = pd.DataFrame(
            [
                {
                    "id": 1,
                    "title": "Rock Arena",
                    "category": "concert",
                    "tags": "music rock evening",
                    "description": "concert music rock evening",
                    "age_rating": 16,
                    "price": 2200,
                    "event_date": "2026-06-20",
                    "status": "published",
                    "city": "Moscow",
                    "available_tickets": 120,
                },
                {
                    "id": 2,
                    "title": "Science Weekend",
                    "category": "lecture",
                    "tags": "science technology education",
                    "description": "lecture science technology education",
                    "age_rating": 12,
                    "price": 900,
                    "event_date": "2026-06-21",
                    "status": "published",
                    "city": "Moscow",
                    "available_tickets": 80,
                },
                {
                    "id": 3,
                    "title": "Night Cinema",
                    "category": "cinema",
                    "tags": "movie drama night",
                    "description": "cinema movie drama night",
                    "age_rating": 18,
                    "price": 1100,
                    "event_date": "2026-06-22",
                    "status": "published",
                    "city": "Saint Petersburg",
                    "available_tickets": 40,
                },
            ]
        )

        users = pd.DataFrame(
            [
                {
                    "id": 1,
                    "preferred_categories": "concert lecture",
                    "city": "Moscow",
                    "max_age_rating": 16,
                }
            ]
        )

        interactions = pd.DataFrame(
            [
                {
                    "id": 1,
                    "user_id": 1,
                    "event_id": 1,
                    "action": "purchase",
                    "rating": "",
                    "created_at": "2026-05-01 12:00:00",
                },
                {
                    "id": 2,
                    "user_id": 1,
                    "event_id": 2,
                    "action": "favorite",
                    "rating": "",
                    "created_at": "2026-05-02 12:00:00",
                },
                {
                    "id": 3,
                    "user_id": 2,
                    "event_id": 2,
                    "action": "purchase",
                    "rating": "",
                    "created_at": "2026-05-03 12:00:00",
                },
                {
                    "id": 4,
                    "user_id": 2,
                    "event_id": 3,
                    "action": "review",
                    "rating": 4,
                    "created_at": "2026-05-04 12:00:00",
                },
            ]
        )

        events.to_csv(self.temp_dir / "data" / "events.csv", index=False, encoding="utf-8")
        users.to_csv(self.temp_dir / "data" / "users.csv", index=False, encoding="utf-8")
        interactions.to_csv(self.temp_dir / "data" / "interactions.csv", index=False, encoding="utf-8")

        if str(self.temp_dir) not in sys.path:
            sys.path.insert(0, str(self.temp_dir))

        from matrix_factorization import MatrixFactorizationRecommender

        model = MatrixFactorizationRecommender(epochs=6, factors_count=4)
        model.fit(interactions=interactions, events=events, verbose=False)
        model.best_params = {"epochs": 6, "factors_count": 4}
        model.best_metrics = {"mae": 0.0, "rmse": 0.0}

        joblib.dump(model, self.temp_dir / "models" / "matrix_model.pkl")
        (self.temp_dir / "models" / "training_metrics.json").write_text(
            json.dumps(
                {
                    "best_params": model.best_params,
                    "best_metrics": model.best_metrics,
                },
                ensure_ascii=False,
                indent=2,
            ),
            encoding="utf-8",
        )

        os.environ["ML_WORKSPACE"] = str(self.temp_dir)
        os.environ["AUTO_TRAIN_IF_MISSING"] = "false"
        os.environ["ALLOW_SYNTHETIC_GENERATION"] = "false"
        os.environ["DATA_SOURCE"] = "csv"

        from app.config import get_settings
        from app.main import create_app

        get_settings.cache_clear()

        self.client_context = TestClient(create_app())
        self.client = self.client_context.__enter__()

    def tearDown(self) -> None:
        self.client_context.__exit__(None, None, None)

        from app.config import get_settings

        get_settings.cache_clear()

        for key in ("ML_WORKSPACE", "AUTO_TRAIN_IF_MISSING", "ALLOW_SYNTHETIC_GENERATION", "DATA_SOURCE"):
            os.environ.pop(key, None)

        shutil.rmtree(self.temp_dir, ignore_errors=True)

    def test_health_endpoint_reports_ready_service(self) -> None:
        response = self.client.get("/health")
        self.assertEqual(response.status_code, 200)
        payload = response.json()

        self.assertEqual(payload["status"], "ok")
        self.assertTrue(payload["model_loaded"])
        self.assertEqual(payload["datasets"]["events"], 3)

    def test_user_recommendations_endpoint_returns_ranked_items(self) -> None:
        response = self.client.get(
            "/api/recommendations/users/1",
            params={"limit": 5},
        )
        self.assertEqual(response.status_code, 200)
        payload = response.json()

        self.assertEqual(payload["user_id"], 1)
        self.assertGreaterEqual(len(payload["items"]), 1)
        self.assertIn("score", payload["items"][0])

    def test_preview_endpoint_supports_cold_start_context(self) -> None:
        response = self.client.post(
            "/api/recommendations/preview",
            json={
                "user_id": 99999,
                "limit": 3,
                "user_context": {
                    "preferred_categories": ["concert"],
                    "preferred_tags": ["music"],
                    "city": "Moscow",
                    "max_age_rating": 16,
                },
            },
        )
        self.assertEqual(response.status_code, 200)
        payload = response.json()

        self.assertEqual(payload["user_id"], 99999)
        self.assertLessEqual(len(payload["items"]), 3)
        self.assertEqual(payload["items"][0]["source"], "cold_start")

    def test_interaction_endpoint_appends_new_row(self) -> None:
        response = self.client.post(
            "/api/recommendations/interactions",
            json={
                "user_id": 1,
                "event_id": 2,
                "action": "view",
            },
        )
        self.assertEqual(response.status_code, 201)
        payload = response.json()

        self.assertEqual(payload["interaction_id"], 5)
        self.assertEqual(payload["total_interactions"], 5)

    def test_live_mode_uses_upstream_datasets(self) -> None:
        from app.config import get_settings
        from app.main import create_app

        os.environ["DATA_SOURCE"] = "live"
        get_settings.cache_clear()

        with patch(
            "app.service.EventServiceClient.fetch_recommendation_events",
            return_value=[
                {
                    "id": 10,
                    "title": "Live Rock Arena",
                    "category": "concert",
                    "tags": "music rock",
                    "description": "live concert music rock",
                    "age_rating": 16,
                    "price": 2500,
                    "event_date": "2026-07-20",
                    "status": "published",
                    "city": "Moscow",
                    "available_tickets": 200,
                    "next_session_id": 5010,
                    "venue_address": "Moscow, Tverskaya 10",
                    "hall_id": 55,
                    "hall_name": "Arena Hall",
                }
            ],
        ), patch(
            "app.service.EventServiceClient.fetch_recommendation_interactions",
            return_value=[
                {
                    "id": 1,
                    "user_id": 1,
                    "event_id": 10,
                    "action": "favorite",
                    "rating": "",
                    "created_at": "2026-04-30 12:00:00",
                }
            ],
        ), patch(
            "app.service.BookingServiceClient.fetch_recommendation_interactions",
            return_value=[
                {
                    "id": 1,
                    "user_id": 1,
                    "event_id": 10,
                    "action": "purchase",
                    "rating": "",
                    "created_at": "2026-05-01 12:00:00",
                }
            ],
        ), patch(
            "app.service.AuthServiceClient.get_current_user",
            return_value={
                "id": 1,
                "birth_date": "1995-07-14",
            },
        ):
            with TestClient(create_app()) as live_client:
                response = live_client.get(
                    "/api/recommendations/users/1",
                    params={"limit": 3},
                    headers={"Authorization": "Bearer test-token"},
                )

        self.assertEqual(response.status_code, 200)
        payload = response.json()
        self.assertEqual(payload["used_context"]["max_age_rating"], 18)
        self.assertEqual(payload["items"], [])


if __name__ == "__main__":
    unittest.main()
