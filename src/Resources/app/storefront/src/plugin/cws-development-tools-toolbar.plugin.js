import Plugin from "src/plugin-system/plugin.class";

export default class CwsDevelopmentToolsToolbarPlugin extends Plugin {
  init() {
    this._boundKeydown = this._onKeydown.bind(this);
    this._boundModalCancel = () => this._closeConfirmModal(false);
    this._boundModalAccept = () => this._closeConfirmModal(true);

    this._confirmResolve = null;
    this._isProcessing = false;

    this._modalBackdrop = this.el.querySelector(
      "[data-cws-devtools-modal-backdrop]",
    );
    this._modalText = this.el.querySelector("[data-cws-devtools-modal-text]");
    this._modalCancelButton = this.el.querySelector(
      "[data-cws-devtools-modal-cancel]",
    );
    this._modalAcceptButton = this.el.querySelector(
      "[data-cws-devtools-modal-accept]",
    );

    this._registerEvents();

    document.addEventListener("keydown", this._boundKeydown);

    if (this._modalCancelButton) {
      this._modalCancelButton.addEventListener("click", this._boundModalCancel);
    }

    if (this._modalAcceptButton) {
      this._modalAcceptButton.addEventListener("click", this._boundModalAccept);
    }
  }

  destroy() {
    document.removeEventListener("keydown", this._boundKeydown);

    if (this._modalCancelButton) {
      this._modalCancelButton.removeEventListener(
        "click",
        this._boundModalCancel,
      );
    }

    if (this._modalAcceptButton) {
      this._modalAcceptButton.removeEventListener(
        "click",
        this._boundModalAccept,
      );
    }

    super.destroy();
  }

  _registerEvents() {
    this.el.addEventListener("click", async (event) => {
      const button = event.target.closest("[data-cws-devtools-action]");

      if (!button) {
        return;
      }

      const action = button.getAttribute("data-cws-devtools-action");

      if (action === "reload") {
        window.location.reload();
        return;
      }

      await this._executeAction(action);
    });
  }

  _onKeydown(event) {
    const key = typeof event.key === "string" ? event.key.toLowerCase() : "";

    if (key === "escape" && this._isModalOpen()) {
      event.preventDefault();
      this._closeConfirmModal(false);
      return;
    }

    if (!event.altKey) {
      return;
    }

    if (key === "c") {
      event.preventDefault();
      this._executeAction("cache-clear");
    }

    if (key === "t") {
      event.preventDefault();
      this._executeAction("theme-compile");
    }
  }

  async _executeAction(action) {
    if (this._isProcessing) {
      return;
    }

    const actionLabel =
      action === "cache-clear" ? "cache:clear" : "theme:compile";
    const shouldRun = await this._openConfirmModal(`Run ${actionLabel}?`);

    if (!shouldRun) {
      return;
    }

    try {
      this._isProcessing = true;

      const response = await window.fetch(
        `/cws-devtools/maintenance/${action}`,
        {
          method: "POST",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        },
      );

      if (!response.ok) {
        throw new Error(`Maintenance request failed (${response.status}).`);
      }

      const payload = await response.json();

      if (payload.success !== true) {
        throw new Error(payload.message || "Maintenance request failed.");
      }

      window.location.reload();
    } catch (error) {
      const message =
        error instanceof Error ? error.message : "Maintenance request failed.";
      window.alert(message);
    } finally {
      this._isProcessing = false;
    }
  }

  _isModalOpen() {
    return (
      this._modalBackdrop &&
      !this._modalBackdrop.classList.contains("is-hidden")
    );
  }

  _openConfirmModal(message) {
    if (!this._modalBackdrop || !this._modalText) {
      return Promise.resolve(window.confirm(message));
    }

    this._modalText.textContent = message;
    this._modalBackdrop.classList.remove("is-hidden");

    return new Promise((resolve) => {
      this._confirmResolve = resolve;
    });
  }

  _closeConfirmModal(accepted) {
    if (!this._modalBackdrop) {
      return;
    }

    this._modalBackdrop.classList.add("is-hidden");

    if (typeof this._confirmResolve === "function") {
      this._confirmResolve(accepted);
      this._confirmResolve = null;
    }
  }
}
