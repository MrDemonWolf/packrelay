# PackRelay - Multi-Builder REST API Bridge for WordPress

PackRelay is a WordPress plugin that adds REST API endpoints for
accepting form submissions from external apps and mobile clients.
It supports Divi, WPForms, and Gravity Forms via a provider
abstraction, with Firebase App Check protection against abuse.

When using the Divi provider, PackRelay also captures front-end
Divi contact form submissions and provides an admin UI for
viewing, filtering, and exporting them.

Build the bridge between your WordPress forms and the world.

## Features

- **REST API Endpoints** - Submit forms and retrieve field
  structures via `POST /packrelay/v1/submit/{form_id}` and
  `GET /packrelay/v1/forms/{form_id}/fields`.
- **Multi-Provider Support** - Works with Divi (default),
  WPForms, and Gravity Forms through a pluggable provider
  abstraction.
- **Firebase App Check** - Server-side token verification using
  `kreait/firebase-php` to block unauthorized requests.
- **Divi Front-End Capture** - Hooks into
  `et_pb_contact_form_submit` to save Divi form submissions with
  an admin list, detail view, and CSV export.
- **Unified Entry Storage** - Custom `wp_packrelay_entries` table
  stores all submissions with provider, form, page, and contact
  metadata.
- **CORS Support** - Configurable allowed origins for
  cross-origin requests from mobile apps and external sites.
- **Auto-Updates** - Automatic update detection from GitHub
  releases via `yahnis-elsts/plugin-update-checker`.
- **Hooks and Filters** - Extensible with actions like
  `packrelay_entry_created` and filters like
  `packrelay_rest_response`.

## Getting Started

See `MOBILE_INTEGRATION.md` for full client-side integration
examples with React Native.

1. Download the latest `packrelay.zip` from the
   [GitHub Releases](https://github.com/mrdemonwolf/packrelay/releases)
   page.
2. Upload and activate the plugin in WordPress.
3. Go to **PackRelay > Settings** to configure your provider and
   Firebase project ID.
4. Add your form IDs to the allowlist and set allowed CORS
   origins if needed.

## Usage

### Settings

All settings are stored as a single option: `packrelay_settings`

| Key                  | Type   | Default                      |
|----------------------|--------|------------------------------|
| `form_provider`      | string | `'divi'`                     |
| `firebase_project_id`| string | `'mrdemonwolf-official-app'` |
| `notification_email` | string | `''` (falls back to admin)   |
| `allowed_form_ids`   | string | `''` (comma-separated)       |
| `allowed_origins`    | string | `''` (comma-separated)       |

### Firebase App Check Setup

PackRelay uses Firebase App Check to verify that REST API
requests come from your authorized apps. Every submission to
`/packrelay/v1/submit/{form_id}` must include a valid
`app_check_token` in the POST body.

1. Create a Firebase project at
   https://console.firebase.google.com (or use an existing one).
2. Navigate to **App Check** in the Firebase console and enable
   it.
3. Register your apps with an attestation provider:
   - **iOS**: App Attest or DeviceCheck
   - **Android**: Play Integrity
   - **Web**: reCAPTCHA Enterprise (if applicable)
4. Enter your Firebase project ID in **PackRelay > Settings >
   Firebase Project ID**.
5. In your client app, initialize the Firebase App Check SDK,
   obtain a token, and include it as `app_check_token` in the
   POST body when submitting forms.

If the token is missing, the API returns `403 appcheck_missing`.
If the token is invalid or expired, it returns
`403 appcheck_failed`.

Divi front-end form submissions (captured via the
`et_pb_contact_form_submit` hook) do not require App Check since
they go through standard WordPress form handling.

### REST API Endpoints

**Submit a form entry:**

```bash
curl -X POST https://yoursite.com/wp-json/packrelay/v1/submit/42:0 \
  -H "Content-Type: application/json" \
  -d '{
    "fields": {"0": "John Doe", "1": "john@example.com"},
    "app_check_token": "your-firebase-appcheck-token"
  }'
```

**Get form fields:**

```bash
curl https://yoursite.com/wp-json/packrelay/v1/forms/42:0/fields
```

Form IDs support numeric (`123`) and Divi composite (`42:0`)
formats.

### Error Codes

| Status | Code               | Description                        |
|--------|--------------------|------------------------------------|
| 400    | `missing_fields`   | Required fields are missing        |
| 400    | `invalid_email`    | Email field has invalid format     |
| 403    | `appcheck_missing` | App Check token not provided       |
| 403    | `appcheck_failed`  | Token invalid or expired           |
| 404    | `form_not_found`   | Form ID not in allowlist or absent |
| 500    | `entry_failed`     | Server-side entry creation error   |

## Tech Stack

| Layer          | Technology                          |
|----------------|-------------------------------------|
| Runtime        | PHP 8.1+, WordPress 6.0+           |
| App Check      | kreait/firebase-php ^7.0            |
| Auto-Updates   | yahnis-elsts/plugin-update-checker  |
| Testing        | PHPUnit 10, Brain Monkey, Mockery   |
| CI             | GitHub Actions (PHP 8.1-8.4)        |

## Development

### Prerequisites

- PHP 8.1 or higher
- Composer
- WordPress 6.0+ (for manual testing)
- A form builder plugin: Divi, WPForms, or Gravity Forms

### Setup

1. Clone the repository:

   ```bash
   git clone https://github.com/mrdemonwolf/packrelay.git
   cd packrelay
   ```

2. Install dependencies:

   ```bash
   composer install
   ```

3. Run the test suite:

   ```bash
   make test
   ```

### Development Scripts

- `make test` - Run all PHPUnit tests.
- `make zip` - Build a production ZIP with optimized autoloader
  and no dev dependencies.
- `make clean` - Remove build artifacts.
- `vendor/bin/phpunit tests/DiviSubmissionsTest.php` - Run a
  single test file.
- `vendor/bin/phpunit --filter test_name` - Run a single test
  by name.

### Code Quality

- WordPress Coding Standards (WPCS) for all PHP.
- Tabs for indentation, Yoda conditions.
- `ABSPATH` guard at the top of every PHP file.
- All output escaped, all input sanitized.
- Tests use Brain Monkey to stub WordPress functions without
  requiring a WordPress installation.

## Project Structure

```
packrelay/
├── packrelay.php                  # Main plugin bootstrap
├── includes/
│   ├── class-packrelay.php        # Singleton orchestrator
│   ├── class-packrelay-loader.php # Hook/filter registration
│   ├── class-packrelay-rest-api.php       # REST endpoints
│   ├── class-packrelay-appcheck.php       # Firebase App Check
│   ├── class-packrelay-entry-store.php    # Custom DB table
│   ├── class-packrelay-entries-page.php   # Admin entries page
│   ├── class-packrelay-entries-list-table.php # WP_List_Table
│   ├── class-packrelay-divi-submissions.php   # Divi capture
│   ├── class-packrelay-settings.php       # Settings API
│   ├── class-packrelay-provider-factory.php   # Provider factory
│   ├── class-packrelay-activator.php      # Activation handler
│   ├── class-packrelay-deactivator.php    # Deactivation handler
│   └── providers/
│       ├── class-packrelay-provider.php           # Abstract base
│       ├── class-packrelay-provider-divi.php      # Divi provider
│       ├── class-packrelay-provider-wpforms.php   # WPForms
│       └── class-packrelay-provider-gravityforms.php # GF
├── templates/
│   ├── divi-submissions-list.php   # Submissions list view
│   └── divi-submissions-detail.php # Submission detail view
├── assets/css/
│   └── packrelay-admin.css         # Admin styles
├── admin/
│   └── settings-page.php           # Settings page template
├── tests/                          # PHPUnit test suite
├── Makefile                        # Build and test commands
├── composer.json                   # Dependencies
├── readme.txt                      # WordPress.org readme
├── CLAUDE.md                       # Development guide
└── MOBILE_INTEGRATION.md           # Client integration guide
```

## License

![GitHub license](https://img.shields.io/github/license/mrdemonwolf/packrelay.svg?style=for-the-badge&logo=github)

## Contact

Have questions or feedback?

- Discord: [Join my server](https://mrdwolf.net/discord)

Made with love by [MrDemonWolf, Inc.](https://www.mrdemonwolf.com)
