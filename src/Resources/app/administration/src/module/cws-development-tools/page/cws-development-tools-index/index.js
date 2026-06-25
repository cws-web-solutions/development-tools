import template from "./cws-development-tools-index.html.twig";
import "./cws-development-tools-index.scss";

const { Component, Mixin } = Shopware;

Component.register("cws-development-tools-index", {
  template,

  inject: ["cwsDevelopmentToolsApiService", "cacheApiService"],

  mixins: [Mixin.getByName("notification")],

  data() {
    return {
      isLoading: false,
      isClearingCache: false,
      isCompilingThemes: false,
      isClearingOpcache: false,
      isSavingMediaFallback: false,
      isSavingStorefrontToolbar: false,
      state: null,
      cacheInfo: null,
      maintenanceResults: {
        clearCache: null,
        compileThemes: null,
        clearOpcache: null,
      },
      mediaFallbackForm: {
        host: "",
        enabled: false,
      },
      storefrontToolbarForm: {
        visible: true,
      },
    };
  },

  computed: {
    isBusy() {
      return (
        this.isClearingCache ||
        this.isCompilingThemes ||
        this.isClearingOpcache ||
        this.isSavingMediaFallback ||
        this.isSavingStorefrontToolbar
      );
    },

    mediaFallback() {
      return this.state?.mediaFallback ?? {};
    },

    storefrontToolbar() {
      return this.state?.storefrontToolbar ?? {};
    },

    systemStatus() {
      return this.state?.systemStatus ?? {};
    },

    systemStatusItems() {
      return [
        ["pluginVersion", this.$tc("cws-development-tools.index.systemStatus.pluginVersion")],
        ["environment", this.$tc("cws-development-tools.index.systemStatus.environment")],
        ["debug", this.$tc("cws-development-tools.index.systemStatus.debug"), "boolean"],
        ["phpVersion", this.$tc("cws-development-tools.index.systemStatus.phpVersion")],
        ["phpSapi", this.$tc("cws-development-tools.index.systemStatus.phpSapi")],
        ["operatingSystem", this.$tc("cws-development-tools.index.systemStatus.operatingSystem")],
        ["timezone", this.$tc("cws-development-tools.index.systemStatus.timezone")],
        ["memoryLimit", this.$tc("cws-development-tools.index.systemStatus.memoryLimit")],
        ["maxExecutionTime", this.$tc("cws-development-tools.index.systemStatus.maxExecutionTime")],
        ["opcacheLoaded", this.$tc("cws-development-tools.index.systemStatus.opcacheLoaded"), "boolean"],
        ["opcacheEnabled", this.$tc("cws-development-tools.index.systemStatus.opcacheEnabled"), "boolean"],
        ["apcuLoaded", this.$tc("cws-development-tools.index.systemStatus.apcuLoaded"), "boolean"],
        ["xdebugLoaded", this.$tc("cws-development-tools.index.systemStatus.xdebugLoaded"), "boolean"],
        ["projectDir", this.$tc("cws-development-tools.index.systemStatus.projectDir")],
      ];
    },

    environment() {
      return this.state?.environment ?? "";
    },

    maintenanceAvailable() {
      return this.state?.maintenance?.available === true;
    },

    themeCompileAvailable() {
      return this.state?.maintenance?.themeCompileAvailable === true;
    },

    opcacheAvailable() {
      return this.state?.maintenance?.opcacheAvailable === true;
    },

    shopEnvironmentLabel() {
      if (!this.cacheInfo?.environment) {
        return "-";
      }

      return this.cacheInfo.environment === "dev"
        ? this.$tc("cws-development-tools.index.shopInfo.environmentDev")
        : this.$tc("cws-development-tools.index.shopInfo.environmentProd");
    },

    shopHttpCacheLabel() {
      if (typeof this.cacheInfo?.httpCache !== "boolean") {
        return "-";
      }

      return this.cacheInfo.httpCache
        ? this.$tc("cws-development-tools.index.shopInfo.httpCacheOn")
        : this.$tc("cws-development-tools.index.shopInfo.httpCacheOff");
    },

    shopCacheAdapterLabel() {
      return this.cacheInfo?.cacheAdapter || "-";
    },

    mediaFallbackStatusLabel() {
      if (!this.mediaFallback.configured) {
        return this.$tc("cws-development-tools.index.mediaFallback.missing");
      }

      if (!this.mediaFallback.enabled) {
        return this.$tc("cws-development-tools.index.mediaFallback.disabled");
      }

      if (!this.mediaFallback.active) {
        return this.$tc(
          "cws-development-tools.index.mediaFallback.inactiveForEnvironment",
        );
      }

      if (this.mediaFallback.source === "legacy-system-config") {
        return this.$tc("cws-development-tools.index.mediaFallback.legacy");
      }

      return this.$tc("cws-development-tools.index.mediaFallback.ready");
    },

    mediaFallbackSourceLabel() {
      if (this.mediaFallback.source === "legacy-system-config") {
        return this.$tc(
          "cws-development-tools.index.mediaFallback.sourceLegacy",
        );
      }

      if (this.mediaFallback.source === "system-config") {
        return this.$tc(
          "cws-development-tools.index.mediaFallback.sourceSystemConfig",
        );
      }

      return this.$tc(
        "cws-development-tools.index.mediaFallback.sourceMissing",
      );
    },
  },

  created() {
    this.loadState();
  },

  methods: {
    goToDocumentation() {
      this.$router.push({ name: "cws.development.tools.documentation" });
    },

    async loadState() {
      this.isLoading = true;

      try {
        const [stateResponse, cacheInfoResponse] = await Promise.all([
          this.cwsDevelopmentToolsApiService.loadState(),
          this.cacheApiService.info(),
        ]);

        this.state = stateResponse;
        this.cacheInfo = cacheInfoResponse?.data ?? null;
        this.mediaFallbackForm.host = this.state?.mediaFallback?.host ?? "";
        this.mediaFallbackForm.enabled =
          this.state?.mediaFallback?.enabled === true;
        this.storefrontToolbarForm.visible =
          this.state?.storefrontToolbar?.visible !== false;
      } catch (error) {
        this.createNotificationError({
          message: this.getErrorMessage(error),
        });
      } finally {
        this.isLoading = false;
      }
    },

    async onClearCache() {
      this.isClearingCache = true;

      try {
        await this.cacheApiService.clear();
        this.maintenanceResults.clearCache = {
          environment: this.environment || "-",
          cacheCleared: true,
        };
        this.createNotificationSuccess({
          message: this.$tc(
            "cws-development-tools.notifications.clearCacheSuccess",
          ),
        });
      } catch (error) {
        this.createNotificationError({
          message: this.getErrorMessage(error),
        });
      } finally {
        this.isClearingCache = false;
      }
    },

    async onCompileThemes() {
      if (!this.themeCompileAvailable) {
        return;
      }

      this.isCompilingThemes = true;

      try {
        this.maintenanceResults.compileThemes =
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

    async onClearOpcache() {
      if (!this.opcacheAvailable) {
        return;
      }

      this.isClearingOpcache = true;

      try {
        this.maintenanceResults.clearOpcache =
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

    async onSaveMediaFallback() {
      this.isSavingMediaFallback = true;

      try {
        this.state = await this.cwsDevelopmentToolsApiService.saveMediaFallback(
          this.mediaFallbackForm.host,
          this.mediaFallbackForm.enabled,
        );
        this.mediaFallbackForm.host = this.state?.mediaFallback?.host ?? "";
        this.mediaFallbackForm.enabled =
          this.state?.mediaFallback?.enabled === true;
        this.createNotificationSuccess({
          message: this.$tc(
            "cws-development-tools.notifications.saveMediaFallbackSuccess",
          ),
        });
      } catch (error) {
        this.createNotificationError({
          message: this.getErrorMessage(error),
        });
      } finally {
        this.isSavingMediaFallback = false;
      }
    },

    async onSaveStorefrontToolbar() {
      this.isSavingStorefrontToolbar = true;

      try {
        this.state =
          await this.cwsDevelopmentToolsApiService.saveStorefrontToolbar(
            this.storefrontToolbarForm.visible,
          );
        this.storefrontToolbarForm.visible =
          this.state?.storefrontToolbar?.visible !== false;
        this.createNotificationSuccess({
          message: this.$tc(
            "cws-development-tools.notifications.saveStorefrontToolbarSuccess",
          ),
        });
      } catch (error) {
        this.createNotificationError({
          message: this.getErrorMessage(error),
        });
      } finally {
        this.isSavingStorefrontToolbar = false;
      }
    },

    formatStatusValue(value, type = "text") {
      if (type === "boolean") {
        return value
          ? this.$tc("cws-development-tools.index.systemStatus.yes")
          : this.$tc("cws-development-tools.index.systemStatus.no");
      }

      return value || "-";
    },

    getErrorMessage(error) {
      return (
        error?.response?.data?.errors?.[0]?.detail ||
        error?.response?.data?.message ||
        error?.message ||
        this.$tc("cws-development-tools.notifications.genericError")
      );
    },

    formatShortcut(key) {
      const systemKey = this.$device?.getSystemKey?.() || "ALT";

      return `${systemKey} + ${key}`;
    },
  },
});
