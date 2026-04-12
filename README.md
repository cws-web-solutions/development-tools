# CwsDevelopmentTools Plugin

## Overview

This plugin provides development tools for local Shopware environments.
At the moment it includes media URL resolution for local development.

## Installation

1. Copy the plugin to your `custom/static-plugins/` directory
2. Enable the plugin in your Shopware configuration
3. Clear the cache

## Configuration

Open the plugin settings page in the Shopware administration panel under:
Settings → Extensions → CWS Development Tools

## Features

- Resolves media URLs for local development
- Provides a base plugin for additional development tooling
- Clears caches, recompiles assigned storefront themes, and resets OPcache in development
- Adds a Settings entry in the administration with a one-click maintenance action
- Asks for a Yes/No confirmation before executing maintenance shortcuts
- Uses Shopware's standard cache clear API flow for the cache clear button
- Adds a documentation page and plugin card documentation shortcut
- Compatible with Shopware 6
- Easy configuration

## Requirements

- Shopware 6.x
- PHP 7.4 or higher

## Usage

Once installed and activated, the plugin automatically handles media URL resolution in your local development environment.

For maintenance tasks you can use the dedicated buttons on the Settings page or run:

`bin/console cws:development-tools:refresh`

Optional command flags:

- `--active-only` to compile themes only for active sales channels
- `--keep-assets` to keep current theme assets during compilation
- `--skip-opcache-reset` to skip OPcache reset

Administration shortcuts:

- `ALT + T` opens a confirmation and then compiles themes
- `ALT + O` opens a confirmation and then resets OPcache
- Cache clear in this plugin uses the same standard administration cache clear flow used by Shopware

For web-runtime OPcache resets you can trigger the administration action from the same PHP SAPI as the administration:

`POST /api/_action/cws-development-tools/refresh`

Optional JSON payload:

```json
{
  "activeOnly": false,
  "keepAssets": false,
  "resetOpcache": true
}
```

The media fallback can be maintained directly on the `Media Fallback` tab inside the plugin Settings page.

## Support

For issues or questions, please contact the development team.

## License

MIT
