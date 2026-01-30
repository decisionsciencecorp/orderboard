# Ghost Kitchen Order Board – Test Suite

## Overview

- **Unit**: PHPUnit tests for pure functions, config, CSRF, auth (no HTTP).
- **Integration**: PHPUnit tests for DB/API flow and optional HTTP API (requires server).
- **E2E**: Playwright tests for admin login, display page, and API from browser.

## Prerequisites

- PHP 7.4+ with SQLite3
- Composer (for PHPUnit)
- Node 18+ and npm (for Playwright E2E)

## Setup

```bash
# PHP deps (PHPUnit)
composer install

# Node deps (Playwright + browsers)
npm install
npx playwright install
```

## Run tests

### Unit + Integration (PHPUnit)

Uses a test DB under `tests/_env/` (created automatically).

```bash
composer test
# or
./vendor/bin/phpunit
```

Unit only:

```bash
composer test -- --testsuite Unit
```

Integration only (DB flow; HTTP tests skip if server not running):

```bash
composer test -- --testsuite Integration
```

With coverage:

```bash
composer test -- --coverage-text
# HTML report: tests/coverage/html/
composer test -- --coverage-html tests/coverage/html
```

### HTTP API integration

To run the API HTTP tests (in `ApiHttpTest`), start the app first:

```bash
cd public && php -S localhost:8000
```

Then in another terminal:

```bash
composer test -- --testsuite Integration --filter ApiHttpTest
```

Or set `BASE_URL` if your server is on another host/port:

```bash
set BASE_URL=http://localhost:8000
composer test -- --testsuite Integration
```

### E2E (Playwright)

Starts the PHP server automatically unless `CI` is set.

```bash
npm run e2e
```

Headed (see browser):

```bash
npm run e2e:headed
```

With UI:

```bash
npm run e2e:ui
```

To use an already-running server, set `BASE_URL` (e.g. `http://localhost:8000`).

## Coverage goal

- Unit: `public/includes` (config, auth, csrf, functions) and admin/API scripts.
- Integration: Full order lifecycle and optional HTTP API.
- E2E: Admin login/logout, dashboard, display page, API auth behavior.

Run `composer test -- --coverage-text` to see line/branch coverage for PHP.
