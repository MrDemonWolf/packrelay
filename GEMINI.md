# PackRelay - WordPress REST API Bridge

PackRelay is a WordPress plugin that provides a secure REST API bridge for accepting form submissions from external applications and mobile clients. It supports multiple form builders through a provider abstraction and uses Firebase App Check to ensure request authenticity.

## Project Overview

- **Type:** WordPress Plugin
- **Purpose:** Securely relay form submissions from mobile/external apps to WordPress form builders (Divi, WPForms, Gravity Forms).
- **Core Tech:** PHP 8.1+, WordPress 6.0+, Firebase App Check, Composer.
- **Architecture:** 
  - **Singleton Orchestrator:** `PackRelay` class in `includes/class-packrelay.php`.
  - **Provider System:** Abstracted integration for form builders in `includes/providers/`.
  - **REST API:** Namespace `packrelay/v1` with endpoints for submission and field discovery.
  - **Security:** Server-side Firebase App Check token verification.

## Building and Running

### Development Setup
1.  **Install Dependencies:**
    ```bash
    composer install
    ```
2.  **Run Tests:**
    ```bash
    make test
    # Or specifically:
    vendor/bin/phpunit
    ```

### Production Build
1.  **Generate Production ZIP:**
    ```bash
    make zip
    ```
    This command optimizes the autoloader, removes dev dependencies, and packages the plugin into `build/packrelay.zip`.

### Cleanup
1.  **Remove Build Artifacts:**
    ```bash
    make clean
    ```

## Development Conventions

- **Coding Standards:** Follows [WordPress Coding Standards (WPCS)](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
  - Use **Tabs** for indentation.
  - Use **Yoda conditions** (e.g., `if ( 'value' === $var )`).
  - Always include an `ABSPATH` guard at the top of PHP files:
    ```php
    if ( ! defined( 'ABSPATH' ) ) { exit; }
    ```
- **Prefixing:** Use `packrelay_` for functions and `PackRelay_` for classes.
- **Testing:** 
  - Powered by **PHPUnit 10**, **Brain Monkey**, and **Mockery**.
  - Tests are designed to run **without** a full WordPress installation (using stubs).
  - Test files are located in `tests/`.
- **Hooks:** 
  - Registration is centralized in `PackRelay_Loader`.
  - Custom actions like `packrelay_entry_created` and filters like `packrelay_rest_response` are available for extension.

## Key Files & Directories

- `packrelay.php`: Main plugin file and entry point.
- `includes/class-packrelay-rest-api.php`: REST API route definitions and handlers.
- `includes/class-packrelay-appcheck.php`: Firebase App Check verification logic.
- `includes/providers/`: Concrete implementations for Divi, WPForms, and Gravity Forms.
- `includes/class-packrelay-entry-store.php`: Logic for the custom `wp_packrelay_entries` database table.
- `CLAUDE.md`: Internal development guide and technical reference.
- `MOBILE_INTEGRATION.md`: Documentation for client-side integration (e.g., React Native).

## REST API Summary

- **Submit Entry:** `POST /wp-json/packrelay/v1/submit/{form_id}`
- **Get Fields:** `GET /wp-json/packrelay/v1/forms/{form_id}/fields`
- **Authentication:** Requires `app_check_token` in the request body for submissions.
- **CORS:** Configurable via plugin settings (Allowed Origins).
