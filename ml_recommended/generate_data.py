import random
from datetime import datetime, timedelta

import pandas as pd


random.seed(42)


CATEGORIES = {
    "concert": ["music", "rock", "pop", "jazz", "electronic", "evening"],
    "theater": ["drama", "classic", "culture", "evening"],
    "cinema": ["movie", "horror", "comedy", "drama", "night"],
    "standup": ["humor", "comedy", "evening"],
    "lecture": ["science", "education", "technology"],
    "festival": ["music", "food", "outdoor", "weekend"],
}

EVENT_STATUS_PROBABILITIES = {
    "published": 0.72,
    "draft": 0.18,
    "cancelled": 0.10,
}

ACTION_PROBABILITIES = {
    "view": 0.45,
    "favorite": 0.20,
    "booking": 0.15,
    "purchase": 0.12,
    "review": 0.08,
}

CITIES = [
    "Moscow",
    "Saint Petersburg",
    "Kazan",
    "Nizhny Novgorod",
    "Yekaterinburg",
]


def weighted_choice(items_with_probabilities):
    items = list(items_with_probabilities.keys())
    weights = list(items_with_probabilities.values())
    return random.choices(items, weights=weights, k=1)[0]


def generate_events(count=120):
    events = []
    now = datetime.now()

    for event_id in range(1, count + 1):
        category = random.choice(list(CATEGORIES.keys()))
        tags = random.sample(
            CATEGORIES[category],
            k=random.randint(2, min(4, len(CATEGORIES[category]))),
        )

        event_date = now + timedelta(days=random.randint(-30, 120))
        status = weighted_choice(EVENT_STATUS_PROBABILITIES)
        base_capacity = random.randint(120, 1800)
        available_tickets = random.randint(0, base_capacity)

        if status != "published":
            available_tickets = 0

        events.append(
            {
                "id": event_id,
                "title": f"Event #{event_id}",
                "category": category,
                "tags": " ".join(tags),
                "description": f"{category} {' '.join(tags)}",
                "age_rating": random.choice([0, 6, 12, 16, 18]),
                "price": random.randint(300, 3000),
                "event_date": event_date.strftime("%Y-%m-%d"),
                "status": status,
                "city": random.choice(CITIES),
                "available_tickets": available_tickets,
            }
        )

    return pd.DataFrame(events)


def generate_users(count=60):
    users = []

    for user_id in range(1, count + 1):
        preferred_categories = random.sample(
            list(CATEGORIES.keys()),
            k=random.randint(1, 3),
        )

        users.append(
            {
                "id": user_id,
                "preferred_categories": " ".join(preferred_categories),
                "city": random.choice(CITIES),
                "max_age_rating": random.choice([6, 12, 16, 18]),
            }
        )

    return pd.DataFrame(users)


def generate_interactions(users, events, min_interactions=15, max_interactions=40):
    interactions = []
    interaction_id = 1
    now = datetime.now()

    published_events = events[events["status"] == "published"]

    for _, user in users.iterrows():
        user_id = int(user["id"])
        preferred_categories = user["preferred_categories"].split()
        user_city = user["city"]
        max_age_rating = int(user["max_age_rating"])

        interactions_count = random.randint(min_interactions, max_interactions)

        for _ in range(interactions_count):
            if random.random() < 0.7:
                candidate_events = published_events[
                    published_events["category"].isin(preferred_categories)
                ]
            else:
                candidate_events = published_events

            if random.random() < 0.55:
                candidate_events = candidate_events[
                    candidate_events["city"] == user_city
                ]

            candidate_events = candidate_events[
                candidate_events["age_rating"] <= max_age_rating
            ]

            if candidate_events.empty:
                candidate_events = published_events

            event = candidate_events.sample(1).iloc[0]
            action = weighted_choice(ACTION_PROBABILITIES)

            rating = ""
            if action == "review":
                if event["category"] in preferred_categories:
                    rating = random.choices(
                        [3, 4, 5],
                        weights=[0.15, 0.35, 0.50],
                        k=1,
                    )[0]
                else:
                    rating = random.choices(
                        [1, 2, 3, 4],
                        weights=[0.35, 0.30, 0.25, 0.10],
                        k=1,
                    )[0]

            event_date = datetime.strptime(event["event_date"], "%Y-%m-%d")
            latest_interaction_time = min(event_date, now)
            earliest_interaction_time = latest_interaction_time - timedelta(days=120)
            interaction_time = earliest_interaction_time + timedelta(
                seconds=random.randint(
                    0,
                    max(int((latest_interaction_time - earliest_interaction_time).total_seconds()), 1),
                )
            )

            interactions.append(
                {
                    "id": interaction_id,
                    "user_id": user_id,
                    "event_id": int(event["id"]),
                    "action": action,
                    "rating": rating,
                    "created_at": interaction_time.strftime("%Y-%m-%d %H:%M:%S"),
                }
            )

            interaction_id += 1

    return pd.DataFrame(interactions)


def main():
    events = generate_events()
    users = generate_users()
    interactions = generate_interactions(users, events)

    events.to_csv("data/events.csv", index=False, encoding="utf-8")
    users.to_csv("data/users.csv", index=False, encoding="utf-8")
    interactions.to_csv("data/interactions.csv", index=False, encoding="utf-8")

    print("Generated datasets:")
    print(f"events: {len(events)}")
    print(f"users: {len(users)}")
    print(f"interactions: {len(interactions)}")


if __name__ == "__main__":
    main()
