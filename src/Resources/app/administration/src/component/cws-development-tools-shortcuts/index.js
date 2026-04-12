import template from "./cws-development-tools-shortcuts.html.twig";

const { Component, Mixin } = Shopware;

Component.register("cws-development-tools-shortcuts", {
  template,

  inject: ["cwsDevelopmentToolsApiService", "acl"],

  mixins: [Mixin.getByName("notification")],

  shortcuts: {
    "SYSTEMKEY+t": {
      active() {
        return this.canUseShortcuts && !this.isBusy;
      },
      method: "compileThemesShortcut",
    },
    "SYSTEMKEY+o": {
      active() {
        return this.canUseShortcuts && !this.isBusy;
      },
      method: "clearOpcacheShortcut",
    },
  },

  data() {
    return {
      isCompilingThemes: false,
      isClearingOpcache: false,
      isConfirmModalOpen: false,
      pendingShortcutAction: null,
    };
  },

  computed: {
    canUseShortcuts() {
      return this.acl.can("system.plugin_maintain");
    },

    isBusy() {
      return this.isCompilingThemes || this.isClearingOpcache;
    },
  },

  created() {
    this.createdComponent();
  },

  beforeUnmount() {
    this.beforeUnmountComponent();
  },

  methods: {
    createdComponent() {
      document.addEventListener("keydown", this.keydownEventListener);
    },

    beforeUnmountComponent() {
      document.removeEventListener("keydown", this.keydownEventListener);
    },

    keydownEventListener(event) {
      if (this.$device.getSystemKey() !== "ALT") {
        return;
      }

      const key = typeof event.key === "string" ? event.key.toLowerCase() : "";
      if (
        event.key === "Alt" ||
        ((key === "t" || key === "o") && event.altKey)
      ) {
        event.preventDefault();
      }
    },

    openShortcutConfirmation(action) {
      this.pendingShortcutAction = action;
      this.isConfirmModalOpen = true;
    },

    closeShortcutConfirmation() {
      this.isConfirmModalOpen = false;
      this.pendingShortcutAction = null;
    },

    confirmShortcutAction() {
      if (this.pendingShortcutAction === "compile-themes") {
        this.closeShortcutConfirmation();
        return this.executeCompileThemesShortcut();
      }

      if (this.pendingShortcutAction === "clear-opcache") {
        this.closeShortcutConfirmation();
        return this.executeClearOpcacheShortcut();
      }

      this.closeShortcutConfirmation();

      return Promise.resolve();
    },

    getShortcutConfirmationMessage() {
      if (this.pendingShortcutAction === "compile-themes") {
        return this.$tc("cws-development-tools.shortcuts.confirmCompileThemes");
      }

      if (this.pendingShortcutAction === "clear-opcache") {
        return this.$tc("cws-development-tools.shortcuts.confirmClearOpcache");
      }

      return "";
    },

    async compileThemesShortcut() {
      if (!this.canUseShortcuts || this.isBusy) {
        return;
      }

      this.openShortcutConfirmation("compile-themes");
    },

    async executeCompileThemesShortcut() {
      if (!this.canUseShortcuts || this.isBusy) {
        return;
      }

      this.isCompilingThemes = true;

      try {
        await this.cwsDevelopmentToolsApiService.compileThemes();
        this.createNotificationSuccess({
          message: this.$tc(
            "cws-development-tools.notifications.compileThemesSuccess",
          ),
        });
      } catch (error) {
        this.createNotificationError({
          message: this.getErrorMessage(error),
        });
      } finally {
        this.isCompilingThemes = false;
      }
    },

    async clearOpcacheShortcut() {
      if (!this.canUseShortcuts || this.isBusy) {
        return;
      }

      this.openShortcutConfirmation("clear-opcache");
    },

    async executeClearOpcacheShortcut() {
      if (!this.canUseShortcuts || this.isBusy) {
        return;
      }

      this.isClearingOpcache = true;

      try {
        await this.cwsDevelopmentToolsApiService.clearOpcache();
        this.createNotificationSuccess({
          message: this.$tc(
            "cws-development-tools.notifications.clearOpcacheSuccess",
          ),
        });
      } catch (error) {
        this.createNotificationError({
          message: this.getErrorMessage(error),
        });
      } finally {
        this.isClearingOpcache = false;
      }
    },

    getErrorMessage(error) {
      return (
        error?.response?.data?.errors?.[0]?.detail ||
        error?.response?.data?.message ||
        error?.message ||
        this.$tc("cws-development-tools.notifications.genericError")
      );
    },
  },
});
