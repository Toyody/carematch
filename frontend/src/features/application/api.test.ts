import { beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest } from "@/lib/api/client";
import { createApplication, getApplication, listApplications } from "./api";

vi.mock("@/lib/api/client", () => ({ apiRequest: vi.fn() }));

describe("application API", () => {
  beforeEach(() => vi.clearAllMocks());

  it("uses tenant-scoped list and detail paths", async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({
      data: [],
      links: {},
      meta: {},
    });
    await listApplications(4, { job_id: "8", status: "applied" });
    expect(apiRequest).toHaveBeenCalledWith(
      "/organisations/4/applications?job_id=8&status=applied",
    );

    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { id: 9 } });
    await getApplication(4, 9);
    expect(apiRequest).toHaveBeenCalledWith("/organisations/4/applications/9");
  });

  it("creates with CSRF through the shared client", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: { id: 9 } });
    await createApplication(4, { candidate_id: 7, job_id: 8 });
    expect(apiRequest).toHaveBeenCalledWith("/organisations/4/applications", {
      body: { candidate_id: 7, job_id: 8 },
      method: "POST",
      withCsrf: true,
    });
  });
});
