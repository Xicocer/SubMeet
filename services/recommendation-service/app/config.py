from __future__ import annotations

from functools import lru_cache
from pathlib import Path

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


CURRENT_FILE = Path(__file__).resolve()
SERVICE_DIR = CURRENT_FILE.parents[1]
REPO_ROOT = CURRENT_FILE.parents[3]
DEFAULT_ML_WORKSPACE = REPO_ROOT / "ml_recommended"


class Settings(BaseSettings):
    app_name: str = "SubMeet Recommendation Service"
    app_env: str = "local"
    api_prefix: str = "/api"
    host: str = "127.0.0.1"
    port: int = 8004
    cors_allowed_origins_raw: str = "http://127.0.0.1:5173,http://localhost:5173"
    cors_allowed_origin_regex: str | None = r"^https?://(localhost|127\.0\.0\.1)(:\d+)?$"

    data_source: str = "hybrid"
    auth_service_url: str = "http://127.0.0.1:8000/api"
    event_service_url: str = "http://127.0.0.1:8001/api"
    booking_service_url: str = "http://127.0.0.1:8003/api"
    internal_api_key: str = "submeet-internal-key"
    request_timeout_seconds: float = 10.0
    live_data_cache_seconds: int = 30
    persist_live_datasets: bool = True

    ml_workspace: Path = Field(default=DEFAULT_ML_WORKSPACE)
    model_relative_path: str = "models/matrix_model.pkl"
    events_relative_path: str = "data/events.csv"
    users_relative_path: str = "data/users.csv"
    interactions_relative_path: str = "data/interactions.csv"
    training_metrics_relative_path: str = "models/training_metrics.json"

    auto_train_if_missing: bool = True
    allow_synthetic_generation: bool = True
    default_limit: int = 10
    max_limit: int = 50
    log_level: str = "INFO"
    log_retention_days: int = 14
    log_service_name: str = "recommendation-service"
    log_relative_dir: str = "logs"
    log_file_name: str = "structured.json.log"

    model_config = SettingsConfigDict(
        env_file=SERVICE_DIR / ".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    @property
    def model_path(self) -> Path:
        return self.ml_workspace / self.model_relative_path

    @property
    def events_path(self) -> Path:
        return self.ml_workspace / self.events_relative_path

    @property
    def users_path(self) -> Path:
        return self.ml_workspace / self.users_relative_path

    @property
    def interactions_path(self) -> Path:
        return self.ml_workspace / self.interactions_relative_path

    @property
    def training_metrics_path(self) -> Path:
        return self.ml_workspace / self.training_metrics_relative_path

    @property
    def log_dir(self) -> Path:
        return SERVICE_DIR / self.log_relative_dir

    @property
    def log_path(self) -> Path:
        return self.log_dir / self.log_file_name

    @property
    def use_live_data(self) -> bool:
        return self.data_source in {"live", "hybrid"}

    @property
    def cors_allowed_origins(self) -> list[str]:
        return [
            origin.strip()
            for origin in self.cors_allowed_origins_raw.split(",")
            if origin.strip()
        ]


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    return Settings()
