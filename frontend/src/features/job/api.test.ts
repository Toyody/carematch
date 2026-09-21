import { beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest } from "@/lib/api/client";
import {
  createJob,
  getJob,
  listJobs,
  transitionJob,
  updateJob,
  type Job,
  type JobInput,
  type JobPage,
} from "./api";

vi.mock("@/lib/api/client", () => ({ apiRequest: vi.fn() }));

const job: Job = {
  closes_at: null,
  created_at: "2026-09-21T00:00:00+00:00",
  description: null,
  employment_type: "full_time",
  id: 9,
  location: "Tokyo",
  occupation: "Nurse",
  opened_at: null,
  status: "draft",
  title: "Nurse",
  updated_at: "2026-09-21T00:00:00+00:00",
};
const input: JobInput = {
  closes_at: null,
  description: null,
  employment_type: "full_time",
  location: "Tokyo",
  occupation: "Nurse",
  opened_at: null,
  title: "Nurse",
};

describe("Job API", () => {
  beforeEach(() => vi.clearAllMocks());

  it("lists jobs with explicit filters", async () => {
    const page = { data: [job] } as JobPage;
    vi.mocked(apiRequest).mockResolvedValue(page);
    await expect(
      listJobs(11, {
        direction: "asc",
        status: "open",
        search: "Nurse",
        sort: "opened_at",
      }),
    ).resolves.toBe(page);
    expect(apiRequest).toHaveBeenCalledWith(
      "/organisations/11/jobs?direction=asc&status=open&search=Nurse&sort=opened_at",
    );
  });

  it("gets, creates, updates, and transitions through tenant-scoped shared-client calls", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: job });
    await getJob(11, 9);
    await createJob(11, input);
    await updateJob(11, 9, { title: "Changed" });
    await transitionJob(11, 9, "open");
    expect(apiRequest).toHaveBeenNthCalledWith(1, "/organisations/11/jobs/9");
    expect(apiRequest).toHaveBeenNthCalledWith(2, "/organisations/11/jobs", {
      body: input,
      method: "POST",
      withCsrf: true,
    });
    expect(apiRequest).toHaveBeenNthCalledWith(3, "/organisations/11/jobs/9", {
      body: { title: "Changed" },
      method: "PATCH",
      withCsrf: true,
    });
    expect(apiRequest).toHaveBeenNthCalledWith(
      4,
      "/organisations/11/jobs/9/open",
      { method: "POST", withCsrf: true },
    );
  });
});
