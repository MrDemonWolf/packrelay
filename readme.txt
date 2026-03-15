=== PackRelay ===
Contributors: mrdemonwolf
Tags: wpforms, rest-api, forms, firebase, mobile, divi, gravity forms
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept form submissions from external apps and mobile clients via REST API with Firebase App Check protection. Supports Divi, WPForms, and Gravity Forms.

== Description ==

PackRelay adds REST API endpoints to multiple WordPress form builders, enabling external applications (React Native, web apps, etc.) to submit form entries directly. Entries appear in the standard Entries dashboard just like normal submissions.

**Why PackRelay?**

Most form builders have no built-in REST API for accepting external submissions. PackRelay bridges that gap so your mobile app or external site can submit forms to your existing setup.

**Features:**

* REST API endpoints for form submission (`POST /wp-json/packrelay/v1/submit/{form_id}`)
* REST API endpoints for form field structure (`GET /wp-json/packrelay/v1/forms/{form_id}/fields`)
* Support for Divi, WPForms, and Gravity Forms
* Firebase App Check server-side verification for spam protection
* Form ID allowlist for security
* CORS support with configurable allowed origins
* Email notifications via wp_mail() on successful submissions
* WordPress hooks and filters for extensibility

**Requirements:**

* WordPress 6.0+
* PHP 8.1+
* Supported form builder (Divi, WPForms, or Gravity Forms)
* Firebase project with App Check enabled

== Installation ==

1. Upload the `packrelay` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Install and activate a supported form builder (Divi, WPForms, or Gravity Forms)
4. Go to Settings > PackRelay to configure:
   - Firebase project ID
   - Allowed form IDs
   - Notification email
   - Allowed CORS origins (if needed)

== Frequently Asked Questions ==

= Does this require WPForms Pro? =

No, PackRelay works with WPForms Lite (free) and Pro.

= How do I set up Firebase App Check? =

1. Create a Firebase project at https://console.firebase.google.com
2. Enable App Check in the Firebase console
3. Register your app with an attestation provider (App Attest for iOS, Play Integrity for Android)
4. Enter your Firebase project ID in the PackRelay settings

= Will entries trigger form builder notifications? =

No. PackRelay creates entries directly in the database or via provider-specific APIs which does not always trigger built-in notifications. PackRelay sends its own email notification via `wp_mail()`.

= Is this secure without WordPress nonces? =

Yes. Since this is a public API for external apps, WordPress nonces are not applicable. Firebase App Check serves as the anti-abuse mechanism, combined with form ID allowlisting and input sanitization.

== Changelog ==

= 1.1.0 =
* Add support for Divi 5 block-based forms and Gravity Forms
* Add security fixes: CORS origin sanitization, SQL injection protection, and nonce verification for entry links
* Add performance improvements: static caching for settings and provider factory
* Add chunked streaming for CSV entry exports
* Add composite database indexes for faster entry lookups
* Add Cache-Control headers to REST API fields endpoint

= 1.0.0 =
* Initial release
* REST API endpoints for form submission and field retrieval
* Firebase App Check verification
* Email notifications
* CORS support
* WordPress admin settings page

== Upgrade Notice ==

= 1.1.0 =
Security and performance update with Divi 5 and Gravity Forms support.

= 1.0.0 =
Initial release.
