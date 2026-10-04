import { beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest } from "@/lib/api/client";
import { listCandidateMatches } from "./api";

vi.mock("@/lib/api/client", () => ({ apiRequest: vi.fn() }));

describe("matching API", () => {
  beforeEach(() => {
    vi.mocked(apiRequest).mockResolvedValue({ data: [] });
  });

  it("requests tenant and job scoped matches with supported filters", async () => {
    await listCandidateMatches(4, 9, {
      max_distance_km: "25",
      page: "2",
    });

    expect(apiRequest).toHaveBeenCalledWith(
      "/organisations/4/jobs/9/matches?max_distance_km=25&page=2",
    );
  });
});
