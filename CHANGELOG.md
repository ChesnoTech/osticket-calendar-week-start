# Changelog

All notable changes to this plugin are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] — 2026-05-06

### Fixed
- `readFirstDay()` now uses the correct osTicket instance namespace `plugin.{plugin_id}.instance.{instance_id}` (was `plugin.{instance_id}` — never matched what the admin save actually wrote, so the configured value was ignored).
- `readFirstDay()` decodes the `ChoiceField` JSON value form `{"1":"Monday"}` (was reading the raw JSON string as a scalar and falling back to the default).
- `pre_save()` accepts both array and JSON-string variants of the `ChoiceField` value, normalising to the integer key.

## [1.0.1] — 2026-05-06

### Fixed
- Live-preview datepicker on the plugin config page no longer clips the last column ("Sunday"). Container widened with explicit inline-block + auto-width CSS injected via the FreeTextField anchor.

## [1.0.0] — 2026-05-06

### Added
- First release.
- Single global admin setting for first day of the week (0–6).
- Override applied across staff panel and client portal jQuery UI date pickers.
- Live preview datepicker inside the plugin config page.
