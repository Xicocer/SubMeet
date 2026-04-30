from __future__ import annotations

from datetime import datetime
from typing import Literal

from pydantic import BaseModel, Field, field_validator


class HealthResponse(BaseModel):
    status: Literal["ok"]
    app: str
    model_loaded: bool
    workspace: str
    datasets: dict[str, int]
    data_source: str


class UserContext(BaseModel):
    preferred_categories: list[str] = Field(default_factory=list)
    preferred_tags: list[str] = Field(default_factory=list)
    city: str | None = None
    max_age_rating: int | None = Field(default=None, ge=0)


class RecommendationItem(BaseModel):
    id: int
    title: str
    category: str
    category_name: str | None = None
    tags: str
    description: str
    poster_url: str | None = None
    price: int
    age_rating: int
    event_date: str
    score: float
    matrix_score: float | None = None
    content_score: float
    popularity_score: float
    freshness_score: float
    source: str
    city: str | None = None
    status: str | None = None
    available_tickets: int | None = None
    venue_address: str | None = None
    hall_id: int | None = None
    hall_name: str | None = None
    next_session_id: int | None = None


class RecommendationResponse(BaseModel):
    user_id: int
    limit: int
    items: list[RecommendationItem]
    used_context: UserContext


class RecommendationPreviewRequest(BaseModel):
    user_id: int | None = None
    limit: int = Field(default=10, ge=1, le=50)
    user_context: UserContext = Field(default_factory=UserContext)
    reference_date: datetime | None = None


class InteractionCreateRequest(BaseModel):
    user_id: int = Field(ge=1)
    event_id: int = Field(ge=1)
    action: Literal["view", "favorite", "booking", "purchase", "review"]
    rating: float | None = Field(default=None, ge=1, le=5)
    created_at: datetime | None = None

    @field_validator("rating")
    @classmethod
    def validate_rating(cls, value: float | None, info):  # type: ignore[override]
        action = info.data.get("action")

        if action == "review" and value is None:
            raise ValueError("rating is required for review interactions")

        return value


class InteractionCreateResponse(BaseModel):
    message: str
    interaction_id: int
    total_interactions: int


class TrainRequest(BaseModel):
    regenerate_data: bool = False


class TrainResponse(BaseModel):
    message: str
    best_params: dict[str, object] | None = None
    best_metrics: dict[str, object] | None = None


class MetricsResponse(BaseModel):
    best_params: dict[str, object] | None = None
    best_metrics: dict[str, object] | None = None
