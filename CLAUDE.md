# PackRelay — Development Guide

> Copyright 2026 MrDemonWolf, Inc. — GPL v2 or later

## Quick Reference

```bash
composer install          # Install dev dependencies
make test                 # Run PHPUnit tests
make zip                  # Build production ZIP
make clean                # Remove build artifacts
```

## Architecture

PackRelay is a WordPress plugin that adds REST API endpoints to WPForms for external form submissions with reCAPTCHA v3 protection.

### Class Structure

| Class | File | Purpose |
|-------|------|---------|
| `PackRelay` | `class-packrelay.php` | Singleton orchestrator — loads deps, wires hooks via loader |
| `PackRelay_Loader` | `class-packrelay-loader.php` | Collects actions/filters, registers them with WP on `run()` |
| `PackRelay_REST_API` | `class-packrelay-rest-api.php` | REST endpoint registration, CORS, submit/fields handlers |
| `PackRelay_ReCaptcha` | `class-packrelay-recaptcha.php` | Google reCAPTCHA v3 server-side verification |
| `PackRelay_Entry` | `class-packrelay-entry.php` | WPForms entry creation via `wpforms()->entry->add()` |
| `PackRelay_Notification` | `class-packrelay-notification.php` | Email notifications via `wp_mail()` |
| `PackRelay_Settings` | `class-packrelay-settings.php` | WordPress Settings API registration |
| `PackRelay_Activator` | `class-packrelay-activator.php` | Activation: defaults, WPForms check |
| `PackRelay_Deactivator` | `class-packrelay-deactivator.php` | Deactivation cleanup |

### Request Flow

```text
POST /packrelay/v1/submit/{form_id}
  → REST_API::handle_submit()
    → is_form_allowed() — check allowlist
    → get_wpforms_form() — verify form exists
    → ReCaptcha::verify() — validate token with Google
    → validate fields + email
    → Entry::create() — save via wpforms()->entry->add()
    → Notification::send() — email via wp_mail()
    → do_action('packrelay_entry_created')
    → return WP_REST_Response
```

## Coding Standards

- **WordPress Coding Standards (WPCS)** for all PHP
- Tabs for indentation (not spaces)
- Yoda conditions: `if ( 'value' === $var )`
- `ABSPATH` guard at top of every PHP file
- Prefix everything with `packrelay_` or `PackRelay_`
- Use `wp_remote_post()` for HTTP calls (never `curl`)
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`
- Sanitize all input: `sanitize_text_field()`, `sanitize_email()`, `absint()`

## Settings

All settings stored as a single option: `packrelay_settings`

| Key | Type | Default |
|-----|------|---------|
| `recaptcha_site_key` | string | `''` |
| `recaptcha_secret_key` | string | `''` |
| `recaptcha_threshold` | float | `0.5` |
| `notification_email` | string | `''` (falls back to admin email) |
| `allowed_form_ids` | string | `''` (comma-separated) |
| `allowed_origins` | string | `''` (comma-separated) |

## Hooks & Filters

```php
do_action( 'packrelay_entry_created', $entry_id, $form_id, $fields, $request );
apply_filters( 'packrelay_notification_args', $mail_args, $entry_id, $form_id );
apply_filters( 'packrelay_recaptcha_threshold', $threshold, $form_id );
apply_filters( 'packrelay_pre_save_fields', $fields, $form_id, $request );
apply_filters( 'packrelay_rest_response', $response, $entry_id, $form_id );
apply_filters( 'packrelay_allowed_form_ids', $allowed_form_ids );
```

## Testing

Tests use **PHPUnit 10 + Brain Monkey + Mockery** — no WordPress installation needed.

```bash
make test                                    # Run all tests
vendor/bin/phpunit tests/ReCaptchaTest.php   # Run single test file
vendor/bin/phpunit --filter test_name        # Run single test
```

### Test Structure

- `tests/bootstrap.php` — Defines WP constants, stubs WP classes, loads plugin files
- `tests/TestCase.php` — Base class with Brain Monkey setUp/tearDown + common WP stubs
- `tests/*Test.php` — Individual test files for each class (e.g., `EntryTest.php`, `RestApiTest.php`)

## REST API Endpoints

- `POST /wp-json/packrelay/v1/submit/{form_id}` — Submit form entry
- `GET /wp-json/packrelay/v1/forms/{form_id}/fields` — Get form field structure

See `MOBILE_INTEGRATION.md` for client-side integration examples.

## Build & Release

- `make zip` builds `build/packrelay.zip` excluding dev files via `.distignore`
- GitHub Actions runs tests on PHP 8.0–8.3
- Releases automatically attach ZIP to GitHub release

## Dependencies

**Runtime:** None (pure WordPress plugin)

**Dev only:** PHPUnit, Brain Monkey, Mockery (via Composer)
