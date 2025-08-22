CRUSH: Code Runbook for Hot OSS Agent

Build/Lint/Test commands:
- PHP tests: php artisan test
- Run a single test: php artisan test --filter "YourTestClass::testYourMethod"
- Pest single test: ./vendor/bin/pest --filter "YourTestClass::testYourMethod"
- Run specific test file: php artisan test --filter "YourTestFileName"
- PHP lint: php -l path/to/YourFile.php
- Frontend build: npm run build && npm run dev
- Seed/migrate: php artisan migrate --seed

Code style guidelines:
- PHP: PHP 8 constructor promotion; explicit return types; type hints
- Imports: organize use statements; no unused imports
- Formatting: PSR-12; PHPDoc where needed
- Naming: PascalCase for classes; camelCase for methods; descriptive names
- Errors: throw exceptions for errors; propagate; use try/catch for IO-bound code
- Testing: Pest preferred; data-driven tests; clear test names
- ORM/DB: eager loading; avoid N+1; use query builder
- Security: avoid logging secrets; read env via config

Cursor & Copilot rules:
- Cursor rules: none detected in .cursor or .cursorrules
- Copilot rules: see .github/copilot-instructions.md for guidance

Misc:
- Add .crush directory to .gitignore if not present
- Document any new tooling usage here