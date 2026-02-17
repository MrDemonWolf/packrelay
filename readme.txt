=== PackRelay ===
Contributors: mrdemonwolf
Tags: wpforms, rest-api, forms, recaptcha, mobile
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept WPForms submissions from external apps and mobile clients via REST API with Google reCAPTCHA v3 protection.

== Description ==

PackRelay adds REST API endpoints to WPForms, enabling external applications (React Native, web apps, etc.) to submit form entries directly into WPForms. Entries appear in the standard WPForms Entries dashboard just like normal submissions.

**Why PackRelay?**

WPForms has no built-in REST API for accepting external submissions. PackRelay bridges that gap so your mobile app or external site can submit forms to your existing WPForms setup.

**Features:**

* REST API endpoint for form submission (`POST /wp-json/packrelay/v1/submit/{form_id}`)
* REST API endpoint for form field structure (`GET /wp-json/packrelay/v1/forms/{form_id}/fields`)
* Google reCAPTCHA v3 server-side verification with configurable score threshold
* Form ID allowlist for security
* CORS support with configurable allowed origins
* Email notifications via wp_mail() on successful submissions
* WordPress hooks and filters for extensibility

**Requirements:**

* WordPress 6.0+
* WPForms Lite or Pro
* PHP 8.0+
* Google reCAPTCHA v3 site key + secret key

== Installation ==

1. Upload the `packrelay` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Install and activate WPForms (Lite or Pro) if not already installed
4. Go to Settings > PackRelay to configure:
   - Google reCAPTCHA v3 keys
   - Allowed form IDs
   - Notification email
   - Allowed CORS origins (if needed)

== Frequently Asked Questions ==

= Does this require WPForms Pro? =

No, PackRelay works with WPForms Lite (free) and Pro.

= How do I get reCAPTCHA keys? =

Visit https://www.google.com/recaptcha/admin and create a reCAPTCHA v3 site.

= Will entries trigger WPForms notifications? =

No. PackRelay creates entries via `wpforms()->entry->add()` which does not trigger WPForms built-in notifications. PackRelay sends its own email notification via `wp_mail()`.

= Is this secure without WordPress nonces? =

Yes. Since this is a public API for external apps, WordPress nonces are not applicable. Google reCAPTCHA v3 serves as the anti-abuse mechanism, combined with form ID allowlisting and input sanitization.

== Changelog ==

= 1.0.0 =
* Initial release
* REST API endpoints for form submission and field retrieval
* Google reCAPTCHA v3 verification
* Email notifications
* CORS support
* WordPress admin settings page

== Upgrade Notice ==

= 1.0.0 =
Initial release.
