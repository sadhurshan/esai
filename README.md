# Elements Supply AI

This monorepo hosts the multi-tenant procurement platform (Laravel + Inertia React) and supporting AI microservices. Consult `/docs/REQUIREMENTS_FULL.md` for the canonical product specification and `/deep-specs/*` for module-level constraints.

## AI training configuration

Feature retraining is centrally controlled via `config/ai_training.php`. Override the following environment variables to tune behaviour per environment:

| Variable | Description | Default |
| --- | --- | --- |
| `AI_TRAINING_ENABLED` | Master toggle that enables or disables super-admin training controls and job scheduling. | `true` |
| `AI_TRAINING_DEFAULT_FORECAST_WINDOW_MONTHS` | Number of months of demand history to request when no explicit window is provided. | `6` |
| `AI_TRAINING_MAX_RUNTIME_MINUTES` | Hard stop (in minutes) for RunModelTrainingJob workers before aborting the microservice poll loop. | `60` |
| `AI_TRAINING_ALLOWED_FILE_TYPES` | Comma separated whitelist of dataset upload extensions (validated before passing to virus scanning). | `csv,jsonl,zip` |

After changing any of these values, restart the queue workers plus the FastAPI training microservice to pick up the new configuration.
