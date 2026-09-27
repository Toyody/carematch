import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiRequest } from "@/lib/api/client";

import { getDashboard } from "./api";

vi.mock("@/lib/api/client", () => ({ apiRequest: vi.fn() }));

describe("Dashboard API", () => {
  beforeEach(() => vi.clearAllMocks());

  it("loads the tenant dashboard through the shared session client", async () => {
    const dashboard = {
      application_counts: {
        applied: 0,
        hired: 0,
        interview: 0,
        offer: 0,
        rejected: 0,
        screening: 0,
      },
      candidate_count: 0,
      open_job_count: 0,
      recent_application_activity: [],
    };
    vi.mocked(apiRequest).mockResolvedValue({ data: dashboard });

    await expect(getDashboard(12)).resolves.toEqual(dashboard);
    expect(apiRequest).toHaveBeenCalledWith("/organisations/12/dashboard");
  });
});
