import CwsDevelopmentToolsToolbarPlugin from "./plugin/cws-development-tools-toolbar.plugin";

const PluginManager = window.PluginManager;

PluginManager.register(
  "CwsDevelopmentToolsToolbar",
  CwsDevelopmentToolsToolbarPlugin,
  "[data-cws-devtools-toolbar]",
);
