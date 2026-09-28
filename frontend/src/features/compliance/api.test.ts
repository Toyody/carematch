import { beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest } from "@/lib/api/client";
import {
  addJobQualificationRequirement,
  createCandidateQualification,
  getQualificationCoverage,
  listQualificationExpiries,
} from "./api";

vi.mock("@/lib/api/client", () => ({ apiRequest: vi.fn() }));

describe("compliance API", () => {
  beforeEach(() => vi.clearAllMocks());

  it("uses the shared CSRF/session client for mutations", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: { id: 1 } });
    const input = {
      credential_number: null,
      expires_on: null,
      issued_on: null,
      issuer: null,
      qualification_definition_id: 4,
    };
    await createCandidateQualification(2, 3, input);
    await addJobQualificationRequirement(2, 5, 4);
    expect(apiRequest).toHaveBeenNthCalledWith(
      1,
      "/organisations/2/candidates/3/qualifications",
      { body: input, method: "POST", withCsrf: true },
    );
    expect(apiRequest).toHaveBeenNthCalledWith(
      2,
      "/organisations/2/jobs/5/qualification-requirements",
      {
        body: { qualification_definition_id: 4 },
        method: "POST",
        withCsrf: true,
      },
    );
  });

  it("loads contextual coverage and tenant expiry data", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: [] });
    await getQualificationCoverage(2, 5, 3);
    await listQualificationExpiries(2);
    expect(apiRequest).toHaveBeenNthCalledWith(
      1,
      "/organisations/2/jobs/5/candidates/3/qualification-coverage",
    );
    expect(apiRequest).toHaveBeenNthCalledWith(
      2,
      "/organisations/2/qualification-expiries",
    );
  });
});
