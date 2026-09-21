import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiRequest } from "@/lib/api/client";

import {
  createCandidate,
  getCandidate,
  listCandidates,
  updateCandidate,
  type Candidate,
  type CandidateInput,
  type CandidatePage,
} from "./api";

vi.mock("@/lib/api/client", () => ({ apiRequest: vi.fn() }));

const candidate: Candidate = {
  availability: null,
  created_at: "2026-09-21T00:00:00+00:00",
  email: "aiko@example.test",
  first_name: "Aiko",
  id: 9,
  last_name: "Tanaka",
  location: "Tokyo",
  notes: null,
  occupation: "Nurse",
  phone: null,
  updated_at: "2026-09-21T00:00:00+00:00",
};

const input: CandidateInput = {
  availability: null,
  email: candidate.email,
  first_name: candidate.first_name,
  last_name: candidate.last_name,
  location: candidate.location,
  notes: null,
  occupation: candidate.occupation,
  phone: null,
};

describe("Candidate API", () => {
  beforeEach(() => vi.clearAllMocks());

  it("lists candidates with explicit query parameters", async () => {
    const page = { data: [candidate] } as CandidatePage;
    vi.mocked(apiRequest).mockResolvedValue(page);

    await expect(
      listCandidates(11, {
        direction: "asc",
        occupation: "Nurse",
        page: "2",
        search: "Aiko",
        sort: "name",
      }),
    ).resolves.toBe(page);
    expect(apiRequest).toHaveBeenCalledWith(
      "/organisations/11/candidates?direction=asc&occupation=Nurse&page=2&search=Aiko&sort=name",
    );
  });

  it("gets a tenant-scoped candidate", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: candidate });

    await expect(getCandidate(11, candidate.id)).resolves.toEqual(candidate);
    expect(apiRequest).toHaveBeenCalledWith(
      `/organisations/11/candidates/${candidate.id}`,
    );
  });

  it("creates through the shared CSRF client", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: candidate });

    await expect(createCandidate(11, input)).resolves.toEqual(candidate);
    expect(apiRequest).toHaveBeenCalledWith("/organisations/11/candidates", {
      body: input,
      method: "POST",
      withCsrf: true,
    });
  });

  it("updates through the shared CSRF client", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: candidate });

    await expect(
      updateCandidate(11, candidate.id, { occupation: "Midwife" }),
    ).resolves.toEqual(candidate);
    expect(apiRequest).toHaveBeenCalledWith(
      `/organisations/11/candidates/${candidate.id}`,
      {
        body: { occupation: "Midwife" },
        method: "PATCH",
        withCsrf: true,
      },
    );
  });
});
