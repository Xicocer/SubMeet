import itertools
from collections import defaultdict

import numpy as np
import pandas as pd


class MatrixFactorizationRecommender:
    def __init__(
        self,
        factors_count=12,
        learning_rate=0.01,
        regularization=0.02,
        epochs=80,
        random_state=42,
        content_weight=0.18,
        popularity_weight=0.12,
        recency_half_life_days=60,
    ):
        self.factors_count = factors_count
        self.learning_rate = learning_rate
        self.regularization = regularization
        self.epochs = epochs
        self.random_state = random_state
        self.content_weight = content_weight
        self.popularity_weight = popularity_weight
        self.recency_half_life_days = recency_half_life_days

        self.user_vectors = None
        self.event_vectors = None

        self.user_biases = None
        self.event_biases = None

        self.user_id_to_index = {}
        self.event_id_to_index = {}
        self.index_to_event_id = {}

        self.global_mean = 0.0
        self.event_popularity_scores = {}
        self.category_popularity_scores = {}
        self.best_params = None
        self.best_metrics = None

    def fit(self, interactions, events=None, verbose=True):
        interactions = interactions.copy()
        np.random.seed(self.random_state)

        user_ids = sorted(interactions["user_id"].unique())
        event_ids = sorted(interactions["event_id"].unique())

        self.user_id_to_index = {
            user_id: index for index, user_id in enumerate(user_ids)
        }
        self.event_id_to_index = {
            event_id: index for index, event_id in enumerate(event_ids)
        }
        self.index_to_event_id = {
            index: event_id
            for event_id, index in self.event_id_to_index.items()
        }

        self.user_vectors = np.random.normal(
            scale=0.1,
            size=(len(user_ids), self.factors_count),
        )
        self.event_vectors = np.random.normal(
            scale=0.1,
            size=(len(event_ids), self.factors_count),
        )

        self.user_biases = np.zeros(len(user_ids))
        self.event_biases = np.zeros(len(event_ids))

        training_data = self._prepare_training_data(interactions)
        if len(training_data) == 0:
            raise ValueError("No interactions available for model training")

        self.global_mean = float(np.mean([row[2] for row in training_data]))
        self.event_popularity_scores = self._build_event_popularity_scores(
            interactions
        )

        if events is not None:
            self.category_popularity_scores = self._build_category_popularity_scores(
                interactions,
                events,
            )

        for epoch in range(1, self.epochs + 1):
            np.random.shuffle(training_data)
            total_error = 0.0

            for user_index, event_index, target_score in training_data:
                predicted_score = self._predict_by_indexes(
                    user_index,
                    event_index,
                )

                error = target_score - predicted_score
                total_error += error ** 2

                user_bias = self.user_biases[user_index]
                event_bias = self.event_biases[event_index]

                self.user_biases[user_index] += self.learning_rate * (
                    error - self.regularization * user_bias
                )
                self.event_biases[event_index] += self.learning_rate * (
                    error - self.regularization * event_bias
                )

                user_vector = self.user_vectors[user_index].copy()
                event_vector = self.event_vectors[event_index].copy()

                self.user_vectors[user_index] += self.learning_rate * (
                    error * event_vector
                    - self.regularization * user_vector
                )
                self.event_vectors[event_index] += self.learning_rate * (
                    error * user_vector
                    - self.regularization * event_vector
                )

            rmse = np.sqrt(total_error / len(training_data))
            if verbose and (epoch % 10 == 0 or epoch == 1):
                print(f"Epoch {epoch}/{self.epochs} | train RMSE: {rmse:.4f}")

        return self

    def auto_fit(
        self,
        interactions,
        events=None,
        param_grid=None,
        test_size=0.2,
        ranking_k=10,
    ):
        if param_grid is None:
            param_grid = {
                "factors_count": [8, 12],
                "learning_rate": [0.005, 0.01],
                "regularization": [0.02, 0.05],
                "epochs": [80],
                "content_weight": [0.12, 0.18],
                "popularity_weight": [0.10, 0.15],
            }

        train_interactions, test_interactions = self._split_interactions(
            interactions=interactions,
            test_size=test_size,
        )

        best_model = None
        best_metrics = None
        best_params = None

        keys = list(param_grid.keys())
        values = list(param_grid.values())

        print("Starting hyperparameter search...\n")

        for combination in itertools.product(*values):
            params = dict(zip(keys, combination))

            model = MatrixFactorizationRecommender(
                factors_count=params["factors_count"],
                learning_rate=params["learning_rate"],
                regularization=params["regularization"],
                epochs=params["epochs"],
                random_state=self.random_state,
                content_weight=params["content_weight"],
                popularity_weight=params["popularity_weight"],
                recency_half_life_days=self.recency_half_life_days,
            )

            model.fit(train_interactions, events=events, verbose=False)
            regression_metrics = model.evaluate(test_interactions)

            metrics = regression_metrics.copy()
            if events is not None:
                ranking_metrics = model.evaluate_ranking(
                    train_interactions=train_interactions,
                    test_interactions=test_interactions,
                    events=events,
                    k=ranking_k,
                )
                metrics.update(ranking_metrics)

            ndcg = metrics.get(f"ndcg@{ranking_k}")
            ndcg_text = "n/a" if ndcg is None else f"{ndcg:.4f}"

            print(
                f"RMSE: {metrics['rmse']:.4f} | "
                f"MAE: {metrics['mae']:.4f} | "
                f"NDCG@{ranking_k}: {ndcg_text} | "
                f"params: {params}"
            )

            if self._is_better_candidate(metrics, best_metrics, ranking_k):
                best_model = model
                best_metrics = metrics
                best_params = params

        self.__dict__.update(best_model.__dict__)
        self.best_params = best_params
        self.best_metrics = best_metrics

        print("\n==============================")
        print("BEST MODEL")
        print("==============================")
        print(f"params: {best_params}")
        print(f"metrics: {best_metrics}")

        return self

    def build_user_content_profile(self, user_id, events, interactions):
        profile = defaultdict(float)
        user_interactions = interactions[interactions["user_id"] == user_id]

        if user_interactions.empty:
            return {}

        for _, interaction in user_interactions.iterrows():
            event_rows = events[events["id"] == interaction["event_id"]]
            if event_rows.empty:
                continue

            event = event_rows.iloc[0]
            weight = self._interaction_to_score(interaction)
            weight *= self._interaction_recency_weight(
                interaction=interaction,
                reference_timestamp=self._reference_timestamp(interactions),
            )

            for feature in self._extract_event_features(event):
                profile[feature] += weight

        if not profile:
            return {}

        max_weight = max(profile.values())
        if max_weight <= 0:
            return {}

        return {
            feature: float(weight / max_weight)
            for feature, weight in profile.items()
        }

    def calculate_content_score(self, event, user_profile):
        if not user_profile:
            return 0.0

        features = self._extract_event_features(event)
        if not features:
            return 0.0

        matched_weights = [user_profile.get(feature, 0.0) for feature in features]
        return float(np.clip(np.mean(matched_weights), 0.0, 1.0))

    def recommend(
        self,
        user_id,
        events,
        interactions,
        limit=10,
        user_context=None,
        reference_date=None,
    ):
        candidate_events = self._filter_candidate_events(
            events=events,
            reference_date=reference_date,
            user_context=user_context,
        )

        if candidate_events.empty:
            return []

        if user_id not in self.user_id_to_index:
            return self._recommend_for_cold_start_user(
                events=candidate_events,
                limit=limit,
                user_context=user_context,
                reference_date=reference_date,
            )

        user_index = self.user_id_to_index[user_id]
        user_profile = self.build_user_content_profile(
            user_id=user_id,
            events=events,
            interactions=interactions,
        )
        interacted_event_ids = set(
            interactions[interactions["user_id"] == user_id]["event_id"]
        )

        recommendations = []

        for _, event in candidate_events.iterrows():
            event_id = int(event["id"])
            if event_id in interacted_event_ids:
                continue

            matrix_score = None
            if event_id in self.event_id_to_index:
                event_index = self.event_id_to_index[event_id]
                matrix_score = self._clip_score(
                    self._predict_by_indexes(user_index, event_index)
                )

            popularity_score = self._get_popularity_score(event_id)
            freshness_score = self._calculate_freshness_score(
                event=event,
                reference_date=reference_date,
            )
            content_score = self.calculate_content_score(
                event=event,
                user_profile=user_profile,
            )

            base_score = matrix_score if matrix_score is not None else popularity_score
            base_score = (
                (1 - self.popularity_weight) * base_score
                + self.popularity_weight * popularity_score
            )

            content_scaled = 1.0 + 4.0 * content_score
            score = (
                (1 - self.content_weight) * base_score
                + self.content_weight * content_scaled
            )
            score += (freshness_score - 3.0) * 0.08
            score = self._clip_score(score)

            recommendations.append(
                self._serialize_recommendation(
                    event=event,
                    score=score,
                    matrix_score=matrix_score,
                    content_score=content_score,
                    popularity_score=popularity_score,
                    freshness_score=freshness_score,
                    source="hybrid",
                )
            )

        recommendations.sort(key=lambda item: item["score"], reverse=True)
        return recommendations[:limit]

    def evaluate(self, interactions):
        training_data = self._prepare_training_data(interactions)
        if len(training_data) == 0:
            return {"mae": None, "rmse": None}

        absolute_errors = []
        squared_errors = []

        for user_index, event_index, target_score in training_data:
            predicted_score = self._clip_score(
                self._predict_by_indexes(user_index, event_index)
            )
            error = target_score - predicted_score
            absolute_errors.append(abs(error))
            squared_errors.append(error ** 2)

        return {
            "mae": float(np.mean(absolute_errors)),
            "rmse": float(np.sqrt(np.mean(squared_errors))),
        }

    def evaluate_ranking(self, train_interactions, test_interactions, events, k=10):
        positive_test = test_interactions[
            test_interactions.apply(self._is_positive_interaction, axis=1)
        ]

        if positive_test.empty:
            return {
                f"precision@{k}": None,
                f"recall@{k}": None,
                f"ndcg@{k}": None,
            }

        precision_scores = []
        recall_scores = []
        ndcg_scores = []

        for user_id, user_rows in positive_test.groupby("user_id"):
            actual_event_ids = {
                int(event_id) for event_id in user_rows["event_id"].tolist()
            }
            if not actual_event_ids:
                continue

            recommendations = self.recommend(
                user_id=int(user_id),
                events=events,
                interactions=train_interactions,
                limit=k,
            )

            recommended_event_ids = [int(item["id"]) for item in recommendations]
            if not recommended_event_ids:
                precision_scores.append(0.0)
                recall_scores.append(0.0)
                ndcg_scores.append(0.0)
                continue

            hits = [
                event_id for event_id in recommended_event_ids
                if event_id in actual_event_ids
            ]

            precision_scores.append(len(hits) / k)
            recall_scores.append(len(hits) / len(actual_event_ids))
            ndcg_scores.append(
                self._ndcg_at_k(recommended_event_ids, actual_event_ids, k)
            )

        if not precision_scores:
            return {
                f"precision@{k}": None,
                f"recall@{k}": None,
                f"ndcg@{k}": None,
            }

        return {
            f"precision@{k}": float(np.mean(precision_scores)),
            f"recall@{k}": float(np.mean(recall_scores)),
            f"ndcg@{k}": float(np.mean(ndcg_scores)),
        }

    def _prepare_training_data(self, interactions):
        training_data = []

        for _, interaction in interactions.iterrows():
            user_id = interaction["user_id"]
            event_id = interaction["event_id"]

            if user_id not in self.user_id_to_index:
                continue
            if event_id not in self.event_id_to_index:
                continue

            user_index = self.user_id_to_index[user_id]
            event_index = self.event_id_to_index[event_id]
            target_score = self._interaction_to_score(interaction)

            training_data.append([user_index, event_index, target_score])

        return training_data

    def _interaction_to_score(self, interaction):
        action = interaction["action"]
        action_scores = {
            "view": 1.0,
            "favorite": 2.5,
            "booking": 3.5,
            "purchase": 5.0,
        }

        if action == "review":
            rating = interaction["rating"]
            if rating == "" or pd.isna(rating):
                return 3.0
            return float(rating)

        return action_scores.get(action, 1.0)

    def _predict_by_indexes(self, user_index, event_index):
        return (
            self.global_mean
            + self.user_biases[user_index]
            + self.event_biases[event_index]
            + np.dot(
                self.user_vectors[user_index],
                self.event_vectors[event_index],
            )
        )

    def _clip_score(self, score):
        return float(np.clip(score, 1.0, 5.0))

    def _build_event_popularity_scores(self, interactions):
        score_totals = defaultdict(float)
        counts = defaultdict(int)

        for _, interaction in interactions.iterrows():
            event_id = int(interaction["event_id"])
            score_totals[event_id] += self._interaction_to_score(interaction)
            counts[event_id] += 1

        popularity_scores = {}
        for event_id, total_score in score_totals.items():
            count = counts[event_id]
            average_score = total_score / count
            volume_score = min(np.log1p(count) / np.log(15), 1.0)
            popularity = 0.75 * average_score + 0.25 * (1.0 + 4.0 * volume_score)
            popularity_scores[event_id] = self._clip_score(popularity)

        return popularity_scores

    def _build_category_popularity_scores(self, interactions, events):
        event_categories = {}
        if "category" not in events.columns:
            return {}

        for _, event in events.iterrows():
            event_categories[int(event["id"])] = str(event["category"]).strip().lower()

        category_scores = defaultdict(list)
        for _, interaction in interactions.iterrows():
            event_id = int(interaction["event_id"])
            category = event_categories.get(event_id)
            if not category:
                continue

            category_scores[category].append(self._interaction_to_score(interaction))

        return {
            category: float(np.mean(scores))
            for category, scores in category_scores.items()
            if scores
        }

    def _recommend_for_cold_start_user(
        self,
        events,
        limit,
        user_context=None,
        reference_date=None,
    ):
        user_profile = self._build_user_context_profile(user_context)
        preferred_categories = set(
            category.lower()
            for category in self._ensure_list(
                None if user_context is None else user_context.get("preferred_categories")
            )
        )

        recommendations = []

        for _, event in events.iterrows():
            event_id = int(event["id"])
            popularity_score = self._get_popularity_score(event_id)
            freshness_score = self._calculate_freshness_score(
                event=event,
                reference_date=reference_date,
            )
            content_score = self.calculate_content_score(
                event=event,
                user_profile=user_profile,
            )

            category = str(event.get("category", "")).strip().lower()
            category_bias = 0.20 if category in preferred_categories else 0.0

            content_scaled = 1.0 + 4.0 * min(content_score + category_bias, 1.0)
            score = 0.75 * popularity_score + 0.25 * content_scaled
            score += (freshness_score - 3.0) * 0.10
            score = self._clip_score(score)

            recommendations.append(
                self._serialize_recommendation(
                    event=event,
                    score=score,
                    matrix_score=None,
                    content_score=content_score,
                    popularity_score=popularity_score,
                    freshness_score=freshness_score,
                    source="cold_start",
                )
            )

        recommendations.sort(key=lambda item: item["score"], reverse=True)
        return recommendations[:limit]

    def _filter_candidate_events(self, events, reference_date=None, user_context=None):
        filtered = events.copy()

        if "status" in filtered.columns:
            filtered = filtered[
                filtered["status"].astype(str).str.lower() == "published"
            ]

        if "event_date" in filtered.columns:
            parsed_dates = pd.to_datetime(
                filtered["event_date"],
                errors="coerce",
            )
            current_date = self._resolve_reference_date(
                reference_date=reference_date,
                events=events,
            )
            filtered = filtered[parsed_dates.notna() & (parsed_dates.dt.normalize() >= current_date)]

        if "available_tickets" in filtered.columns:
            available_tickets = pd.to_numeric(
                filtered["available_tickets"],
                errors="coerce",
            )
            filtered = filtered[
                available_tickets.isna() | (available_tickets > 0)
            ]
        elif "available_seats" in filtered.columns:
            available_seats = pd.to_numeric(
                filtered["available_seats"],
                errors="coerce",
            )
            filtered = filtered[
                available_seats.isna() | (available_seats > 0)
            ]
        elif "is_available" in filtered.columns:
            filtered = filtered[filtered["is_available"].fillna(False)]

        if user_context:
            if "city" in filtered.columns and user_context.get("city"):
                city = str(user_context["city"]).strip().lower()
                filtered = filtered[
                    filtered["city"].astype(str).str.lower() == city
                ]

            if "age_rating" in filtered.columns and user_context.get("max_age_rating") is not None:
                filtered = filtered[
                    filtered["age_rating"].fillna(99) <= int(user_context["max_age_rating"])
                ]

        return filtered

    def _resolve_reference_date(self, reference_date=None, events=None, interactions=None):
        if reference_date is not None:
            return pd.Timestamp(reference_date).normalize()

        event_dates = []
        if events is not None and "event_date" in events.columns:
            event_dates.append(pd.to_datetime(events["event_date"], errors="coerce"))

        interaction_dates = []
        if interactions is not None and "created_at" in interactions.columns:
            interaction_dates.append(
                pd.to_datetime(interactions["created_at"], errors="coerce")
            )

        valid_event_dates = [
            series.dropna().max()
            for series in event_dates
            if not series.dropna().empty
        ]
        valid_interaction_dates = [
            series.dropna().max()
            for series in interaction_dates
            if not series.dropna().empty
        ]

        all_candidates = valid_event_dates + valid_interaction_dates
        if all_candidates:
            latest_timestamp = max(all_candidates)
            today = pd.Timestamp.today().normalize()
            return min(latest_timestamp.normalize(), today)

        return pd.Timestamp.today().normalize()

    def _reference_timestamp(self, interactions):
        if "created_at" not in interactions.columns:
            return None

        timestamps = pd.to_datetime(interactions["created_at"], errors="coerce").dropna()
        if timestamps.empty:
            return None

        return timestamps.max()

    def _interaction_recency_weight(self, interaction, reference_timestamp):
        if reference_timestamp is None or "created_at" not in interaction:
            return 1.0

        timestamp = pd.to_datetime(interaction["created_at"], errors="coerce")
        if pd.isna(timestamp):
            return 1.0

        age_days = max((reference_timestamp - timestamp).days, 0)
        if self.recency_half_life_days <= 0:
            return 1.0

        return float(0.5 ** (age_days / self.recency_half_life_days))

    def _extract_event_features(self, event):
        features = []

        category = str(event.get("category", "")).strip().lower()
        if category:
            features.append(f"category:{category}")

        for tag in str(event.get("tags", "")).split():
            normalized_tag = tag.strip().lower()
            if normalized_tag:
                features.append(f"tag:{normalized_tag}")

        city = str(event.get("city", "")).strip().lower()
        if city:
            features.append(f"city:{city}")

        return features

    def _build_user_context_profile(self, user_context):
        if not user_context:
            return {}

        profile = {}

        for category in self._ensure_list(user_context.get("preferred_categories")):
            normalized_category = str(category).strip().lower()
            if normalized_category:
                profile[f"category:{normalized_category}"] = 1.0

        for tag in self._ensure_list(user_context.get("preferred_tags")):
            normalized_tag = str(tag).strip().lower()
            if normalized_tag:
                profile[f"tag:{normalized_tag}"] = 0.8

        city = str(user_context.get("city", "")).strip().lower()
        if city:
            profile[f"city:{city}"] = 0.6

        return profile

    def _ensure_list(self, value):
        if value is None:
            return []
        if isinstance(value, (list, tuple, set)):
            return list(value)
        return [value]

    def _get_popularity_score(self, event_id):
        return self.event_popularity_scores.get(int(event_id), self._clip_score(self.global_mean))

    def _calculate_freshness_score(self, event, reference_date=None):
        if "event_date" not in event:
            return 3.0

        event_date = pd.to_datetime(event["event_date"], errors="coerce")
        if pd.isna(event_date):
            return 3.0

        current_date = self._resolve_reference_date(reference_date=reference_date)
        days_until_event = max((event_date.normalize() - current_date).days, 0)
        freshness_ratio = 1.0 - min(days_until_event, 180) / 180
        return 1.0 + 4.0 * freshness_ratio

    def _serialize_recommendation(
        self,
        event,
        score,
        matrix_score,
        content_score,
        popularity_score,
        freshness_score,
        source,
    ):
        payload = {
            "id": int(event["id"]),
            "title": str(event.get("title", "")),
            "category": str(event.get("category", "")),
            "tags": str(event.get("tags", "")),
            "description": str(event.get("description", "")),
            "price": int(event.get("price", 0)),
            "age_rating": int(event.get("age_rating", 0)),
            "event_date": str(event.get("event_date", "")),
            "score": float(score),
            "matrix_score": None if matrix_score is None else float(matrix_score),
            "content_score": float(content_score),
            "popularity_score": float(popularity_score),
            "freshness_score": float(freshness_score),
            "source": source,
        }

        for key in (
            "city",
            "status",
            "available_tickets",
            "poster_url",
            "category_name",
            "venue_address",
            "hall_id",
            "hall_name",
            "next_session_id",
        ):
            if key in event.index:
                value = event[key]
                payload[key] = None if pd.isna(value) else value

        return payload

    def _split_interactions(self, interactions, test_size):
        rng = np.random.default_rng(self.random_state)
        train_parts = []
        test_parts = []

        use_time_split = "created_at" in interactions.columns

        for _, user_rows in interactions.groupby("user_id"):
            user_rows = user_rows.copy()
            if len(user_rows) <= 1:
                train_parts.append(user_rows)
                continue

            holdout_count = max(1, int(round(len(user_rows) * test_size)))
            holdout_count = min(holdout_count, len(user_rows) - 1)

            if use_time_split:
                user_rows["__created_at"] = pd.to_datetime(
                    user_rows["created_at"],
                    errors="coerce",
                )
                user_rows = user_rows.sort_values(
                    by=["__created_at", "id"],
                    kind="stable",
                )
                test_rows = user_rows.tail(holdout_count).drop(columns="__created_at")
                train_rows = user_rows.head(len(user_rows) - holdout_count).drop(
                    columns="__created_at"
                )
            else:
                shuffled_indexes = rng.permutation(user_rows.index.to_numpy())
                test_indexes = shuffled_indexes[:holdout_count]
                train_indexes = shuffled_indexes[holdout_count:]
                train_rows = user_rows.loc[train_indexes]
                test_rows = user_rows.loc[test_indexes]

            train_parts.append(train_rows)
            test_parts.append(test_rows)

        train_interactions = pd.concat(train_parts).reset_index(drop=True)
        test_interactions = pd.concat(test_parts).reset_index(drop=True)

        if test_interactions.empty:
            return interactions.copy(), interactions.iloc[0:0].copy()

        return train_interactions, test_interactions

    def _is_better_candidate(self, metrics, current_best_metrics, ranking_k):
        if current_best_metrics is None:
            return True

        ndcg_key = f"ndcg@{ranking_k}"
        current_ndcg = metrics.get(ndcg_key)
        best_ndcg = current_best_metrics.get(ndcg_key)

        if current_ndcg is not None and best_ndcg is not None:
            if current_ndcg > best_ndcg:
                return True
            if current_ndcg < best_ndcg:
                return False

        return metrics["rmse"] < current_best_metrics["rmse"]

    def _is_positive_interaction(self, interaction):
        action = interaction["action"]
        if action in {"favorite", "booking", "purchase"}:
            return True

        if action == "review":
            rating = interaction["rating"]
            if rating == "" or pd.isna(rating):
                return False
            return float(rating) >= 4.0

        return False

    def _ndcg_at_k(self, recommended_event_ids, actual_event_ids, k):
        dcg = 0.0
        for rank, event_id in enumerate(recommended_event_ids[:k], start=1):
            if event_id in actual_event_ids:
                dcg += 1.0 / np.log2(rank + 1)

        ideal_hits = min(len(actual_event_ids), k)
        if ideal_hits == 0:
            return 0.0

        idcg = sum(1.0 / np.log2(rank + 1) for rank in range(1, ideal_hits + 1))
        return dcg / idcg if idcg > 0 else 0.0
