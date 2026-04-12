export default class CwsDevelopmentToolsApiService {
  constructor(httpClient, loginService) {
    this.httpClient = httpClient;
    this.loginService = loginService;
  }

  loadState() {
    return this.httpClient
      .get("/_action/cws-development-tools/state", {
        headers: this.getAuthHeaders(),
      })
      .then((response) => response.data.data);
  }

  compileThemes(payload = {}) {
    return this.httpClient
      .post("/_action/cws-development-tools/compile-themes", payload, {
        headers: this.getAuthHeaders(),
      })
      .then((response) => response.data.data);
  }

  clearOpcache() {
    return this.httpClient
      .post(
        "/_action/cws-development-tools/clear-opcache",
        {},
        {
          headers: this.getAuthHeaders(),
        },
      )
      .then((response) => response.data.data);
  }

  saveMediaFallback(host) {
    return this.httpClient
      .post(
        "/_action/cws-development-tools/media-fallback",
        { host },
        {
          headers: this.getAuthHeaders(),
        },
      )
      .then((response) => response.data.data);
  }

  getAuthHeaders() {
    return {
      Accept: "application/json",
      Authorization: `Bearer ${this.loginService.getToken()}`,
      "Content-Type": "application/json",
    };
  }
}
