from __future__ import annotations

from contextlib import asynccontextmanager
from typing import Annotated

from fastapi import Depends, FastAPI, HTTPException, Query, Request, status
from fastapi.middleware.cors import CORSMiddleware

from .clients import UpstreamServiceError
from .config import Settings, get_settings
from .schemas import (
    HealthResponse,
    InteractionCreateRequest,
    InteractionCreateResponse,
    MetricsResponse,
    RecommendationPreviewRequest,
    RecommendationResponse,
    TrainRequest,
    TrainResponse,
    UserContext,
)
from .service import RecommendationEngine


def get_engine(request: Request) -> RecommendationEngine:
    return request.app.state.recommendation_engine


@asynccontextmanager
async def lifespan(app: FastAPI):
    settings = get_settings()
    engine = RecommendationEngine(settings=settings)

    try:
        engine.ensure_ready()
    except FileNotFoundError as exception:
        # The service can still boot so that health and train endpoints stay reachable.
        app.state.startup_warning = str(exception)
    else:
        app.state.startup_warning = None

    app.state.recommendation_engine = engine
    yield


def create_app(settings: Settings | None = None) -> FastAPI:
    if settings is not None:
        get_settings.cache_clear()

    active_settings = settings or get_settings()

    app = FastAPI(
        title=active_settings.app_name,
        version="1.0.0",
        lifespan=lifespan,
    )

    app.add_middleware(
        CORSMiddleware,
        allow_origins=active_settings.cors_allowed_origins,
        allow_origin_regex=active_settings.cors_allowed_origin_regex,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    @app.get("/health", response_model=HealthResponse)
    def health(
        request: Request,
        engine: Annotated[RecommendationEngine, Depends(get_engine)],
    ) -> HealthResponse:
        snapshot = engine.health_snapshot()
        startup_warning = getattr(request.app.state, "startup_warning", None)

        if startup_warning:
            snapshot["status"] = "ok"

        return HealthResponse.model_validate(snapshot)

    @app.get(
        f"{active_settings.api_prefix}/recommendations/users/{{user_id}}",
        response_model=RecommendationResponse,
    )
    def get_user_recommendations(
        request: Request,
        user_id: int,
        engine: Annotated[RecommendationEngine, Depends(get_engine)],
        limit: int = Query(default=10, ge=1, le=50),
        city: str | None = None,
        max_age_rating: int | None = Query(default=None, ge=0),
        preferred_category: list[str] = Query(default_factory=list),
        preferred_tag: list[str] = Query(default_factory=list),
    ) -> RecommendationResponse:
        try:
            payload = engine.recommend(
                user_id=user_id,
                limit=limit,
                user_context=UserContext(
                    preferred_categories=preferred_category,
                    preferred_tags=preferred_tag,
                    city=city,
                    max_age_rating=max_age_rating,
                ),
                authorization_header=request.headers.get("Authorization"),
            )
        except FileNotFoundError as exception:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=str(exception),
            ) from exception
        except UpstreamServiceError as exception:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=str(exception),
            ) from exception

        return RecommendationResponse.model_validate(payload)

    @app.post(
        f"{active_settings.api_prefix}/recommendations/preview",
        response_model=RecommendationResponse,
    )
    def preview_recommendations(
        request: Request,
        request_body: RecommendationPreviewRequest,
        engine: Annotated[RecommendationEngine, Depends(get_engine)],
    ) -> RecommendationResponse:
        try:
            payload = engine.recommend(
                user_id=request_body.user_id if request_body.user_id is not None else -1,
                limit=request_body.limit,
                user_context=request_body.user_context,
                reference_date=request_body.reference_date,
                authorization_header=request.headers.get("Authorization"),
            )
        except FileNotFoundError as exception:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=str(exception),
            ) from exception
        except UpstreamServiceError as exception:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=str(exception),
            ) from exception

        return RecommendationResponse.model_validate(payload)

    @app.post(
        f"{active_settings.api_prefix}/recommendations/interactions",
        response_model=InteractionCreateResponse,
        status_code=status.HTTP_201_CREATED,
    )
    def create_interaction(
        request_body: InteractionCreateRequest,
        engine: Annotated[RecommendationEngine, Depends(get_engine)],
    ) -> InteractionCreateResponse:
        payload = engine.append_interaction(
            user_id=request_body.user_id,
            event_id=request_body.event_id,
            action=request_body.action,
            rating=request_body.rating,
            created_at=request_body.created_at,
        )

        return InteractionCreateResponse.model_validate(payload)

    @app.post(
        f"{active_settings.api_prefix}/recommendations/train",
        response_model=TrainResponse,
    )
    def train_model(
        request_body: TrainRequest,
        engine: Annotated[RecommendationEngine, Depends(get_engine)],
    ) -> TrainResponse:
        try:
            payload = engine.train(regenerate_data=request_body.regenerate_data)
        except FileNotFoundError as exception:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=str(exception),
            ) from exception
        except UpstreamServiceError as exception:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=str(exception),
            ) from exception

        return TrainResponse.model_validate(payload)

    @app.get(
        f"{active_settings.api_prefix}/recommendations/metrics",
        response_model=MetricsResponse,
    )
    def get_metrics(
        engine: Annotated[RecommendationEngine, Depends(get_engine)],
    ) -> MetricsResponse:
        payload = engine.get_training_metrics()
        return MetricsResponse.model_validate(payload)

    return app


app = create_app()
