# Changelog

All notable changes to this plugin are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] — 2026-05-07

### Changed
- Updates UI moved out of the Config tab into a dedicated top-level **Updates**
  tab on the plugin admin page (mirrors the `quick-buttons` plugin layout).
  Click is on demand — banner doesn't fire on every Config tab open.
- Admin assets (`week-start-admin.js` + `.css`) now scoped to this plugin's
  detail page only via `shouldInjectAdminAssets()` gate. Avoids contaminating
  other plugins' admin pages.

### Fixed
- Live-preview datepicker now defined in admin CSS (was inline `<style>` in
  FreeTextField anchor — duplicated work and harder to maintain).

## [1.1.0] — 2026-05-06

### Added
- One-click auto-update from GitHub Releases. Plugin instance Config tab now
  shows an Updates panel; banner appears when a newer release tag exists; Apply
  button downloads the release zip, backs up current files into `backups/`,
  and overwrites in place.
- New asset `assets/week-start-admin.css`.
- New `backups/` directory holds pre-update zip backups (e.g.
  `v1.0.2-to-v1.1.0-20260506-194530.zip`).

### Notes
- Updater behaviour mirrors the `quick-buttons` plugin pattern.
- Backup pruning is manual in this release (see README).

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
