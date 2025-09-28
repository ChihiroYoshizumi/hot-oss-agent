## General Conventions
- Follow existing Laravel architecture; reuse existing services/components when possible.
- Use descriptive method/variable names; prefer PHP 8 constructor promotion.

## PHP
- Always declare return types and parameter types.
- Use curly braces for all control structures.
- Prefer collection helpers and early returns per existing code.
- Logging is centralized via Laravel `Log` facade in agents.

## Frontend
- Use Inertia patterns (`router.*`, `<Link>`), Tailwind v4 utility classes, React functional components.
- Support dark mode if surrounding code does.

## Tools/Agents
- Tools extend `LarAgent\Tool`, set `$name`, `$description`, `$properties`, `$required`, and implement `execute`.
- Agents extend `BaseAgent`, register tools via `$tools` array.
