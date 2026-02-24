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

PackRelay is a WordPress plugin that adds REST API endpoints for external form submissions with Firebase App Check protection. It supports **Divi** (default), **WPForms**, and **Gravity Forms** via a provider abstraction.

### Class Structure

| Class | File | Purpose |
|-------|------|---------|
| `PackRelay` | `class-packrelay.php` | Singleton orchestrator — loads deps, wires hooks via loader |
| `PackRelay_Loader` | `class-packrelay-loader.php` | Collects actions/filters, registers them with WP on `run()` |
| `PackRelay_REST_API` | `class-packrelay-rest-api.php` | REST endpoint registration, CORS, submit/fields handlers |
| `PackRelay_AppCheck` | `class-packrelay-appcheck.php` | Firebase App Check server-side verification via kreait/firebase-php |
| `PackRelay_Provider` | `providers/class-packrelay-provider.php` | Abstract base class for form builder providers |
| `PackRelay_Provider_Divi` | `providers/class-packrelay-provider-divi.php` | Divi contact form integration (shortcode parsing, `wp_mail()`) |
| `PackRelay_Provider_WPForms` | `providers/class-packrelay-provider-wpforms.php` | WPForms integration (native entry + email system) |
| `PackRelay_Provider_GravityForms` | `providers/class-packrelay-provider-gravityforms.php` | Gravity Forms integration (`GFAPI::submit_form()`) |
| `PackRelay_Provider_Factory` | `class-packrelay-provider-factory.php` | Creates provider instances based on settings |
| `PackRelay_Entry_Store` | `class-packrelay-entry-store.php` | Custom `wp_packrelay_entries` table for unified entry storage |
| `PackRelay_Entries_List_Table` | `class-packrelay-entries-list-table.php` | `WP_List_Table` subclass for admin entry list |
| `PackRelay_Entries_Page` | `class-packrelay-entries-page.php` | Admin page controller for unified entry list + detail + CSV export |
| `PackRelay_Divi_Submissions` | `class-packrelay-divi-submissions.php` | Divi front-end form submission capture + helper utilities |
| `PackRelay_Settings` | `class-packrelay-settings.php` | WordPress Settings API registration + email template parsing |
| `PackRelay_Activator` | `class-packrelay-activator.php` | Activation: defaults, table creation, provider check |
| `PackRelay_Deactivator` | `class-packrelay-deactivator.php` | Deactivation cleanup |

### Provider System

Each provider implements the `PackRelay_Provider` abstract class:

```php
abstract class PackRelay_Provider {
    abstract public function is_available();
    abstract public function get_form( $form_id );
    abstract public function get_fields( $form_id );
    abstract public function get_field_types( $form_id );
    abstract public function create_entry( $form_id, $fields, $request );
    abstract public function send_notifications( $form_id, $entry_id, $fields, $form_data );
    abstract public function get_label();
    abstract public function get_slug();
}
```

| Provider | Notifications | Entry Storage |
|----------|--------------|---------------|
| **Divi** | `wp_mail()` with shortcode `email` attr | `wp_packrelay_entries` only |
| **WPForms** | `wpforms()->process->entry_email()` | WPForms native + `wp_packrelay_entries` |
| **Gravity Forms** | `GFAPI::submit_form()` auto-fires | GF native + `wp_packrelay_entries` |

### Request Flow

```text
POST /packrelay/v1/submit/{form_id}
  → REST_API::handle_submit()
    → is_form_allowed() — check allowlist (string comparison for Divi's 42:0 format)
    → provider->get_form() — verify form exists via provider
    → AppCheck::verify() — validate token with Firebase
    → validate fields + email via provider->get_field_types()
    → provider->create_entry() — save via provider-specific method
    → entry_store->add() — log to unified entries table (non-Divi providers)
    → provider->send_notifications() — trigger native emails
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
| `form_provider` | string | `'divi'` |
| `firebase_project_id` | string | `'mrdemonwolf-official-app'` |
| `notification_email` | string | `''` (falls back to admin email) |
| `allowed_form_ids` | string | `''` (comma-separated; Divi uses `post_id:form_index` format) |
| `allowed_origins` | string | `''` (comma-separated) |
| `notification_subject` | string | `'New {form_name} submission from {site_name}'` |
| `notification_body` | string | (default template with `{all_fields}` and `{submission_date}`) |

## Hooks & Filters

```php
do_action( 'packrelay_entry_created', $entry_id, $form_id, $fields, $request );
do_action( 'packrelay_appcheck_verified', $app_id, $form_id, $token );
apply_filters( 'packrelay_notification_args', $mail_args, $entry_id, $form_id );
apply_filters( 'packrelay_pre_save_fields', $fields, $form_id, $request );
apply_filters( 'packrelay_rest_response', $response, $entry_id, $form_id );
apply_filters( 'packrelay_allowed_form_ids', $allowed_form_ids );
```

## Testing

Tests use **PHPUnit 10 + Brain Monkey + Mockery** — no WordPress installation needed.

```bash
make test                                              # Run all tests
vendor/bin/phpunit tests/ProviderDiviTest.php          # Run single test file
vendor/bin/phpunit --filter test_name                  # Run single test
```

### Test Structure

- `tests/bootstrap.php` — Defines WP constants, stubs WP classes, loads plugin files
- `tests/TestCase.php` — Base class with Brain Monkey setUp/tearDown + common WP stubs
- `tests/RestApiTest.php` — REST API with mock provider injection
- `tests/ProviderDiviTest.php` — Divi provider (shortcode parsing, notifications)
- `tests/ProviderWPFormsTest.php` — WPForms provider (entry creation, sanitization)
- `tests/ProviderGravityFormsTest.php` — Gravity Forms provider
- `tests/ProviderFactoryTest.php` — Factory (default, settings, fallback)
- `tests/EntryStoreTest.php` — Custom entry table CRUD
- `tests/EntriesPageTest.php` — Admin entries page menu registration
- `tests/SettingsTest.php` — Settings registration and sanitization
- `tests/ActivatorTest.php` — Activation defaults and table creation
- `tests/CoreTest.php` — Singleton, hook registration
- `tests/AppCheckTest.php` — Firebase App Check verification
- `tests/DiviSubmissionsTest.php` — Divi front-end submission capture + admin page

## REST API Endpoints

- `POST /wp-json/packrelay/v1/submit/{form_id}` — Submit form entry
- `GET /wp-json/packrelay/v1/forms/{form_id}/fields` — Get form field structure

Form IDs support numeric (`123`) and Divi composite (`42:0`) formats.

See `MOBILE_INTEGRATION.md` for client-side integration examples.

## Auto-Updates

Uses `yahnis-elsts/plugin-update-checker` to poll GitHub releases. When a newer tag is found, WordPress shows the standard "Update Available" UI. Release assets (ZIP from `make zip`) are downloaded automatically.

## Build & Release

- `make zip` builds `build/packrelay.zip` with production vendor deps (excludes dev files via `.distignore`)
- GitHub Actions runs tests on PHP 8.1–8.4
- Releases automatically attach ZIP to GitHub release
- Bump `Version:` header in `packrelay.php` → tag → release → PUC discovers it

## Dependencies

**Runtime:**
- `kreait/firebase-php` ^7.0 (Firebase App Check verification)
- `yahnis-elsts/plugin-update-checker` ^5.6 (GitHub release auto-updates)

**Dev only:** PHPUnit, Brain Monkey, Mockery (via Composer)

## CRITICAL DOCUMENTATION PATTERN

**ALWAYS ADD IMPORTANT DOCS HERE!** When you create or discover:

- Architecture diagrams → Add reference path here
- Database schemas → Add reference path here
- Problem solutions → Add reference path here
- Setup guides → Add reference path here

This prevents context loss! Update this file IMMEDIATELY when creating important docs.
