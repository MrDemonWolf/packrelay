# Changelog

## [1.3.0] - 2026-06-11

### Added
- Settings page header card: version badge, active/inactive status pill, provider chip, and GitHub link

### Changed
- Admin CSS rewritten to WordPress-native system font stack (removes Montserrat/Roboto Google Fonts CDN)
- Entries list table Source and Provider columns now render colored badge pills
- Entry detail view Source and Provider rows now render colored badge pills
- Export CSV button moved to WordPress-native page title action area (inline with page `<h1>`)
- CI: release workflow verifies plugin version header matches the git tag before running tests
- CI: release workflow now caches Composer dependencies across PHP matrix jobs
- CI: build-zip workflow now also triggers on pushes to `main` (not just pull requests)

## [1.1.0] - 2026-03-14

### Security
- Fix CORS header injection: sanitize Origin with CRLF stripping and esc_url()
- Fix SQL injection in uninstall.php: use esc_sql() for table name
- Add nonce verification to entry detail view links
- Add `packrelay_trusted_proxy_headers` filter for X-Forwarded-For spoofing protection

### Added
- Divi 5 block-based form parsing (`divi/contact-form` via `parse_blocks()`)
- Divi 5 version detection (`is_divi5()` method)
- UUID-based form ID support for Divi 5 (`post_id:uuid` format)
- Divi 5 field types: checkbox, booleancheckbox, radio, select, hidden
- UUID extraction from Divi 5 frontend submissions (`et_pb_contact_form_{uuid}`)
- REST API Cache-Control headers on GET /fields endpoint
- Settings and provider factory static caching with clear_cache() methods

### Changed
- CSV export uses chunked streaming (500-row batches) instead of loading all entries
- Composite database indexes on (provider, form_id) and (provider, date_created)

### Fixed
- Test isolation: clear static caches between tests
- WP_REST_Response stub: add header() method for tests
