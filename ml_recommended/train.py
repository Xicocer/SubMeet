import json
import os

import joblib
import pandas as pd

from matrix_factorization import MatrixFactorizationRecommender


def main():
    events = pd.read_csv("data/events.csv")
    interactions = pd.read_csv("data/interactions.csv")

    model = MatrixFactorizationRecommender()
    model.auto_fit(interactions=interactions, events=events)

    os.makedirs("models", exist_ok=True)

    joblib.dump(model, "models/matrix_model.pkl")

    with open("models/training_metrics.json", "w", encoding="utf-8") as file:
        json.dump(
            {
                "best_params": model.best_params,
                "best_metrics": model.best_metrics,
            },
            file,
            ensure_ascii=False,
            indent=2,
        )

    print("\nModel trained and saved to models/matrix_model.pkl")
    print("Metrics saved to models/training_metrics.json")


if __name__ == "__main__":
    main()
