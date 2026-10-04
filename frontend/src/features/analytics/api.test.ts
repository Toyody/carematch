import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiRequest } from "@/lib/api/client";

import { getAnalytics } from "./api";

vi.mock("@/lib/api/client", () => ({ apiRequest: vi.fn() }));

describe("analytics API", () => {
  beforeEach(() => vi.clearAllMocks());

  it("requests the default tenant-scoped report", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: { period: {} } });

    await getAnalytics(12);

    expect(apiRequest).toHaveBeenCalledWith("/organisations/12/analytics");
  });

  it("encodes an explicit date period", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: { period: {} } });

    await getAnalytics(12, { from: "2026-09-01", to: "2026-09-30" });

    expect(apiRequest).toHaveBeenCalledWith(
      "/organisations/12/analytics?from=2026-09-01&to=2026-09-30",
    );
  });
});
