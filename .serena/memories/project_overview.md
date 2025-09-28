## Project Purpose
- Laravel 12 + Inertia/React application that powers AI agents which summarize trending OSS repositories.

## Tech Stack
- Backend: PHP 8.2+, Laravel 12, LarAgent tooling.
- Frontend: React 19 via Inertia, Tailwind CSS v4, Vite build system.
- Tooling: Pest for tests, Laravel Pint for formatting, ESLint/Prettier/TypeScript for frontend.

## Structure Highlights
- `app/AiAgents` contains base agent logic and tool implementations.
- `app/Services`, `app/Http`, `app/Models` follow typical Laravel layering.
- Frontend assets live under `resources/` with Tailwind + Inertia components.
- Configuration under `config/`, routes in `routes/`, database migrations in `database/`.
