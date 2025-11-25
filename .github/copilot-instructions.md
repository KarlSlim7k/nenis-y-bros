## Copilot instructions for Nenis y Bros (backend)

Purpose: Give an AI coding agent the minimal, actionable context to be productive in this PHP REST API codebase.

1) Big picture
- Single PHP backend API (no framework). Entry point: `backend/index.php`.
- Router: `backend/routes/Router.php` (simple regex-based router). Routes are declared in `backend/routes/api.php`.
- Controllers: `backend/controllers/*.php` (AuthController, UserController, AdminController). Controllers read JSON from `php://input` and use models + utils.
- Models: `backend/models/*` use `backend/config/database.php` (Singleton PDO wrapper). SQL is written inline in models using prepared statements.
- Utils: `backend/utils/Response.php`, `Security.php`, `Logger.php`, `Validator.php` — these standardize responses, security (JWT), logging and validation.

2) Request & data flow (how a request is handled)
- HTTP request -> `backend/index.php` (loads config, utils, models, controllers) -> `registerRoutes($router)` in `backend/routes/api.php` -> `Router->dispatch()` finds route -> callback calls controller method.
- Controllers use models (e.g. `Usuario`) which call `Database::getInstance()` and use `fetchOne`/`fetchAll`/`insert`.
- Responses must use `Response::success()/error()/validationError()` — these send JSON and `exit`.

3) Security & auth
- JWT is generated/verified in `backend/utils/Security.php`. Secret is `JWT_SECRET` set via `.env` or `backend/config/config.php`.
- Auth middleware: `backend/middleware/AuthMiddleware.php` extracts a Bearer token from `Authorization` header and calls `Security::verifyJWT()`.

4) Config & environment
- Main config loader: `backend/config/config.php` reads `.env` at project root (`__DIR__ . '/../../.env'`).
- Important constants: `API_PREFIX` (default `api`) and `API_VERSION` (default `v1`) — routes are mounted under `/api/v1`.
- DB credentials are in `.env` or fall back to defaults in `config.php`. Database schema and seed: `db/nyd_db.sql` and `db/test_data.sql`.

5) Developer workflows (what an agent may need to suggest/do)
- Run locally with XAMPP/Apache: place repo under www/ (already at `htdocs`) and start Apache+MySQL. Visit: `http://localhost/nenis_y_bros/api/v1/health`.
- Toggle debug: set `APP_DEBUG` in `.env` to `true`/`false`. When false, error details are hidden.
- To run DB migrations/import schema: import `db/nyd_db.sql` into MySQL (phpMyAdmin or `mysql` CLI).

6) Project-specific conventions and pitfalls
- No namespaces or autoloader: files are loaded with `require_once` in `backend/index.php`. When adding new classes, register them in `index.php` (or update autoload pattern).
- Controllers expect JSON body via `php://input` (not `$_POST`). Use `json_decode(file_get_contents('php://input'), true)`.
- Responses exit after sending JSON. Do not attempt to return values to the router — controllers must call `Response::*` to terminate.
- Routes use `{param}` placeholders (Router converts to regex). Example: `GET /users/{id}` will call the callback with `$id`.
- Database queries use positional parameters. Keep the same pattern to avoid SQL injection and to work with `Database->query` helper.

7) Where to change secrets/credentials
- `.env` at repository root (create if missing). Keys: `DB_*`, `JWT_SECRET`, `ENCRYPTION_KEY`, `APP_DEBUG`, `APP_URL`.

8) Useful file references (examples to open)
- `backend/index.php` — app bootstrap, error handlers
- `backend/routes/api.php` — all endpoints and example usage
- `backend/controllers/AuthController.php` — register/login, shows Validator + Security + Response patterns
- `backend/models/Usuario.php` — example of queries and pagination
- `backend/utils/Response.php` and `Security.php` — response format and JWT usage

9) Making changes: quick checklist for PRs
- Update `index.php` if you add new files that need to be required on bootstrap.
- Add/extend route in `routes/api.php` and implement controller method in `controllers/`.
- Use `Validator` for input rules and `Response::validationError()` for validation failures.
- Log important actions with `Logger::activity()` or `Logger::error()`.
- Update `db/nyd_db.sql` if you change schema and include a short migration note in the PR description.

10) If you need tests or CI
- There are no automated tests or build steps in this repo. Add tests and CI carefully; follow existing non-framework structure (plain PHP). Document any new test runner in README.

If anything here is unclear or you want the instructions in English or with more examples (curl requests, exact .env template), tell me which sections to expand.
