from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import httpx

from .config import Settings


class UpstreamServiceError(RuntimeError):
    """Raised when an upstream API cannot provide recommendation data."""


@dataclass(slots=True)
class BaseServiceClient:
    settings: Settings

    def _build_client(self) -> httpx.Client:
        return httpx.Client(
            timeout=self.settings.request_timeout_seconds,
            headers={"Accept": "application/json"},
        )


class EventServiceClient(BaseServiceClient):
    def fetch_recommendation_events(self) -> list[dict[str, Any]]:
        url = f"{self.settings.event_service_url.rstrip('/')}/internal/recommendations/events"

        with self._build_client() as client:
            response = client.get(
                url,
                headers={"X-Internal-Api-Key": self.settings.internal_api_key},
            )

        if response.status_code >= 400:
            raise UpstreamServiceError(
                f"event-service returned {response.status_code} for {url}"
            )

        payload = response.json()
        if not isinstance(payload, list):
            raise UpstreamServiceError("event-service returned an unexpected payload.")

        return payload

    def fetch_recommendation_interactions(self) -> list[dict[str, Any]]:
        url = f"{self.settings.event_service_url.rstrip('/')}/internal/recommendations/interactions"

        with self._build_client() as client:
            response = client.get(
                url,
                headers={"X-Internal-Api-Key": self.settings.internal_api_key},
            )

        if response.status_code >= 400:
            raise UpstreamServiceError(
                f"event-service returned {response.status_code} for {url}"
            )

        payload = response.json()
        if not isinstance(payload, list):
            raise UpstreamServiceError(
                "event-service returned an unexpected interactions payload."
            )

        return payload


class BookingServiceClient(BaseServiceClient):
    def fetch_recommendation_interactions(self) -> list[dict[str, Any]]:
        url = f"{self.settings.booking_service_url.rstrip('/')}/internal/recommendations/interactions"

        with self._build_client() as client:
            response = client.get(
                url,
                headers={"X-Internal-Api-Key": self.settings.internal_api_key},
            )

        if response.status_code >= 400:
            raise UpstreamServiceError(
                f"booking-service returned {response.status_code} for {url}"
            )

        payload = response.json()
        if not isinstance(payload, list):
            raise UpstreamServiceError(
                "booking-service returned an unexpected payload."
            )

        return payload


class AuthServiceClient(BaseServiceClient):
    def get_current_user(self, authorization_header: str | None) -> dict[str, Any] | None:
        if not authorization_header:
            return None

        url = f"{self.settings.auth_service_url.rstrip('/')}/me"

        with self._build_client() as client:
            response = client.get(
                url,
                headers={"Authorization": authorization_header},
            )

        if response.status_code in (401, 403):
            return None

        if response.status_code >= 400:
            raise UpstreamServiceError(
                f"auth-service returned {response.status_code} for {url}"
            )

        payload = response.json()
        user = payload.get("user")

        return user if isinstance(user, dict) else None
