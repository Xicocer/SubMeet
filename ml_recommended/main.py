import joblib
import pandas as pd


def print_recommendations(title, recommendations):
    print(title)
    print("-" * len(title))

    if not recommendations:
        print("No recommendations.\n")
        return

    for index, event in enumerate(recommendations, start=1):
        print(f"{index}. {event['title']}")
        print(f"   event_id: {event['id']}")
        print(f"   category: {event['category']}")
        print(f"   city: {event.get('city', 'n/a')}")
        print(f"   date: {event['event_date']}")
        print(f"   price: {event['price']} rub.")
        print(f"   final score: {event['score']:.2f}/5")
        print(f"   source: {event['source']}")
        print(
            f"   matrix={event['matrix_score']}, "
            f"content={event['content_score']:.2f}, "
            f"popularity={event['popularity_score']:.2f}"
        )
        print()


def main():
    events = pd.read_csv("data/events.csv")
    interactions = pd.read_csv("data/interactions.csv")
    model = joblib.load("models/matrix_model.pkl")

    known_user_id = 1
    known_user_recommendations = model.recommend(
        user_id=known_user_id,
        events=events,
        interactions=interactions,
        limit=10,
    )

    cold_start_recommendations = model.recommend(
        user_id=999999,
        events=events,
        interactions=interactions,
        limit=10,
        user_context={
            "preferred_categories": ["concert", "festival"],
            "preferred_tags": ["music", "outdoor"],
            "city": "Moscow",
            "max_age_rating": 16,
        },
    )

    print_recommendations(
        title=f"Recommendations for known user #{known_user_id}",
        recommendations=known_user_recommendations,
    )
    print_recommendations(
        title="Recommendations for cold-start user",
        recommendations=cold_start_recommendations,
    )


if __name__ == "__main__":
    main()
