# Calendar Week Start

osTicket plugin that customizes the first day of week for every jQuery UI date picker shown in the staff panel and client portal.

## Why
osTicket's date pickers default to a per-locale first day (Sunday in `en_US`, Monday in `en_GB`). Some regions need Saturday. This plugin lets the admin set one global value (0=Sunday … 6=Saturday) without editing core or locale files.

## Install
1. Copy `calendar-week-start/` into `include/plugins/`.
2. Admin Panel → Manage → Plugins → Install → Enable.
3. Open the plugin config and pick a first day. Save.

## How it works
- A single `ChoiceField` stores `first_day` (0–6) in `PluginConfig`.
- `bootstrap()` injects a tiny script before `</body>` on every full HTML response.
- The script calls `$.datepicker.setDefaults({firstDay: N})` and monkey-patches `$.fn.datepicker` so future inits inherit the value.
- Asset routes registered on both `ajax.scp` and `ajax.client` so the client portal can fetch the script too.

## Config
- **First Day of Week** — dropdown, all 7 options.
- **Live Preview** — inline datepicker shows the chosen first day before save.

## Uninstall
Disable the plugin → date pickers revert to locale default. Remove the row from `ost_plugin` to fully remove.
