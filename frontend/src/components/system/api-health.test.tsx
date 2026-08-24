import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

import { ApiHealth } from "./api-health";

describe("ApiHealth", () => {
  afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
  });

  it("shows a successful API connection", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({
          data: { status: "ok", service: "carematch-api" },
        }),
      }),
    );

    render(<ApiHealth />);

    expect(await screen.findByText("API connected")).toBeInTheDocument();
  });

  it("shows an unavailable state when the request fails", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("offline")));

    render(<ApiHealth />);

    expect(await screen.findByText("API unavailable")).toBeInTheDocument();
  });
});
