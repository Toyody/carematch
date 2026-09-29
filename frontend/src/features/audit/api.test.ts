import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiRequest } from "@/lib/api/client";

import { listAuditEvents } from "./api";

vi.mock("@/lib/api/client", () => ({ apiRequest: vi.fn() }));

describe("audit API", () => {
  beforeEach(() => vi.clearAllMocks());

  it("uses the shared client and serialises only supplied filters", async () => {
    vi.mocked(apiRequest).mockResolvedValue({
      data: [],
      links: { first: "", last: "", next: null, prev: null },
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    });

    await listAuditEvents(9, { event_type: "candidate.updated", page: 2 });

    expect(apiRequest).toHaveBeenCalledWith(
      "/organisations/9/audit-events?event_type=candidate.updated&page=2",
    );
  });
});
