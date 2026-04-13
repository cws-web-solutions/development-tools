### [1.2.2]

- Added an enable/disable switch for Media Fallback in the administration and stored its active state separately from the host URL.
- Removed the media fallback host whitelist so the fallback can work with any development host in `APP_ENV=dev`.
- Updated the administration status panel and documentation for the new media fallback behavior.

### [1.2.1]

- Switched the storefront toolbar logo to the public bundle asset path.
- Added storefront snippet translations for toolbar labels, confirmation modal text, and maintenance error messages.
- Removed hardcoded English toolbar texts so the toolbar follows the active storefront language.

### [1.2.0]

- Added a dev-only storefront floating toolbar with quick maintenance actions and shortcuts.
- Added storefront confirmation modal and page refresh after successful maintenance actions.
- Improved toolbar styling and icon visibility.

### [1.1.0]

- Added the Maintenance tab with dedicated actions for cache clear, theme compilation, and OPcache reset.
- Added a shop information panel (environment, HTTP cache status, cache adapter) above maintenance actions.
- Changed the maintenance action layout from 3 columns to 3 stacked rows for better readability.
- Added shortcut labels next to each maintenance action button in code style.
- Added a Yes/No confirmation modal before executing maintenance shortcuts.
- Updated the maintenance cache clear button to use Shopware's standard cache clear API flow.
- Removed duplicate cache-clear call usage from the plugin administration API service.

### [1.0.0]

- Created the plugin
