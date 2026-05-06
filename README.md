# Calendar Week Start

osTicket plugin that customizes the first day of week for every jQuery UI date picker shown in the staff panel and client portal.

## Why
osTicket's date pickers default to a per-locale first day (Sunday in `en_US`, Monday in `en_GB`). Some regions need Saturday. This plugin lets the admin set one global value (0=Sunday … 6=Saturday) without editing core or locale files.

## Install
1. Copy `calendar-week-start/` into `include/plugins/`.
2. Admin Panel → Manage → Plugins → Install → Enable.
3. Open the instance config and pick a first day. Save.

## Auto-update
1. Open the plugin instance Config tab.
2. The Updates panel checks GitHub Releases on load.
3. When a newer release exists, click **Apply update**.
4. The plugin downloads the release zip, writes a backup zip into
   `backups/v{old}-to-v{new}-{timestamp}.zip`, and overwrites files in place.
5. The page reloads after success.

If something goes wrong, the latest backup zip is in `backups/`. Replace the
plugin files manually:

```bash
unzip -o backups/v1.0.2-to-v1.1.0-20260506-194530.zip \
  -d include/plugins/calendar-week-start/
```

Backup pruning is manual in v1.1.0 — delete old zips by hand.

## How it works
- A single `ChoiceField` stores `first_day` (0–6) in `PluginConfig`.
- `bootstrap()` injects a tiny script before `</body>` on every full HTML response.
- The script calls `$.datepicker.setDefaults({firstDay: N})` and monkey-patches `$.fn.datepicker` so future inits inherit the value.
- Asset routes registered on both `ajax.scp` and `ajax.client` so the client portal can fetch the script too.
- Updater methods (`checkForUpdate`, `applyUpdate`) live on the Plugin class and are exposed via `/scp/ajax.php/calendar-week-start/check-update` and `/apply-update`.

## Config
- **First Day of Week** — dropdown, all 7 options.
- **Live Preview** — inline datepicker shows the chosen first day before save.
- **Updates panel** — banner + Apply button when a newer release is on GitHub.

## Uninstall
Disable the plugin → date pickers revert to locale default. Remove the row from `ost_plugin` to fully remove. The `backups/` directory is left in place; remove the plugin directory manually if you want it gone.
