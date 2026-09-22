import { afterEach, describe, expect, it, vi } from "vitest";

import { ApiError, apiDownload, apiRequest, apiRequestForm } from "./client";

function jsonResponse(
  body: unknown,
  status: number,
  headers: HeadersInit = {},
) {
  return new Response(JSON.stringify(body), {
    headers: { "Content-Type": "application/json", ...headers },
    status,
  });
}

function clearCookies() {
  for (const cookie of document.cookie.split(";")) {
    const name = cookie.split("=")[0]?.trim();

    if (name) {
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
    }
  }
}

describe("API client", () => {
  afterEach(() => {
    clearCookies();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("bootstraps CSRF and sends the URL-decoded XSRF token with credentials", async () => {
    document.cookie = "carematch_session=browser-managed; path=/";
    document.cookie = "XSRF-TOKEN=token%3Dvalue%2520; path=/";
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }));
    const localStorageSpy = vi.spyOn(Storage.prototype, "getItem");
    vi.stubGlobal("fetch", fetchMock);

    await apiRequest<void>("/auth/logout", { method: "POST", withCsrf: true });

    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      "http://localhost:8000/sanctum/csrf-cookie",
      expect.objectContaining({ credentials: "include", method: "GET" }),
    );
    const request = fetchMock.mock.calls[1]?.[1] as RequestInit;
    const headers = request.headers as Headers;
    expect(request.credentials).toBe("include");
    expect(headers.get("Accept")).toBe("application/json");
    expect(headers.get("X-XSRF-TOKEN")).toBe("token=value%20");
    expect(headers.has("Authorization")).toBe(false);
    expect(headers.has("Cookie")).toBe(false);
    expect(localStorageSpy).not.toHaveBeenCalled();
  });

  it("fails safely with 419 when the CSRF cookie cannot be read", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(new Response(null, { status: 204 })),
    );

    await expect(
      apiRequest<void>("/auth/logout", { method: "POST", withCsrf: true }),
    ).rejects.toMatchObject({ status: 419 });
  });

  it("preserves validation details for a 422 response", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        jsonResponse(
          {
            message: "The given data was invalid.",
            errors: { email: ["Email is required."] },
          },
          422,
        ),
      ),
    );

    await expect(apiRequest("/auth/me")).rejects.toEqual(
      new ApiError(422, "The given data was invalid.", {
        email: ["Email is required."],
      }),
    );
  });

  it("turns Retry-After into useful 429 feedback", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        jsonResponse({ message: "Too Many Attempts." }, 429, {
          "Retry-After": "27",
        }),
      ),
    );

    await expect(apiRequest("/auth/me")).rejects.toMatchObject({
      message: "Too many attempts. Please try again in 27 seconds.",
      retryAfter: 27,
      status: 429,
    });
  });

  it("sends multipart data with CSRF and lets the browser set its boundary", async () => {
    document.cookie = "XSRF-TOKEN=multipart-token; path=/";
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(jsonResponse({ data: { id: 1 } }, 201));
    vi.stubGlobal("fetch", fetchMock);
    const form = new FormData();
    form.set("document", new File(["pdf"], "resume.pdf"));

    await apiRequestForm("/organisations/1/candidates/2/documents", form);

    const request = fetchMock.mock.calls[1]?.[1] as RequestInit;
    const headers = request.headers as Headers;
    expect(request.body).toBe(form);
    expect(request.credentials).toBe("include");
    expect(headers.get("X-XSRF-TOKEN")).toBe("multipart-token");
    expect(headers.has("Content-Type")).toBe(false);
    expect(headers.has("Authorization")).toBe(false);
  });

  it("downloads private binary content with cookie credentials", async () => {
    const blob = new Blob(["private"], { type: "application/pdf" });
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(blob, {
        headers: { "Content-Type": "application/pdf" },
        status: 200,
      }),
    );
    vi.stubGlobal("fetch", fetchMock);

    await expect(apiDownload("/documents/1/download")).resolves.toEqual(blob);
    expect(fetchMock).toHaveBeenCalledWith(
      "http://localhost:8000/api/v1/documents/1/download",
      expect.objectContaining({ credentials: "include", method: "GET" }),
    );
  });
});
