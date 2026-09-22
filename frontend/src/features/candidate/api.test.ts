import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiDownload, apiRequest, apiRequestForm } from "@/lib/api/client";

import {
  createCandidate,
  deleteCandidateDocument,
  downloadCandidateDocument,
  getCandidate,
  listCandidates,
  listCandidateDocuments,
  uploadCandidateDocument,
  updateCandidate,
  type Candidate,
  type CandidateInput,
  type CandidatePage,
} from "./api";

vi.mock("@/lib/api/client", () => ({
  apiDownload: vi.fn(),
  apiRequest: vi.fn(),
  apiRequestForm: vi.fn(),
}));

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

  it("lists tenant and Candidate scoped document metadata", async () => {
    const documents = [
      {
        created_at: "2026-09-24T00:00:00+00:00",
        id: 3,
        mime_type: "application/pdf",
        original_name: "resume.pdf",
        size_bytes: 1024,
        updated_at: "2026-09-24T00:00:00+00:00",
        uploaded_by_user_id: 7,
      },
    ];
    vi.mocked(apiRequest).mockResolvedValue({ data: documents });

    await expect(listCandidateDocuments(11, 9)).resolves.toEqual(documents);
    expect(apiRequest).toHaveBeenCalledWith(
      "/organisations/11/candidates/9/documents",
    );
  });

  it("uploads multipart through the shared CSRF client", async () => {
    const document = {
      created_at: "2026-09-24T00:00:00+00:00",
      id: 3,
      mime_type: "application/pdf",
      original_name: "resume.pdf",
      size_bytes: 10,
      updated_at: "2026-09-24T00:00:00+00:00",
      uploaded_by_user_id: 7,
    };
    const file = new File(["pdf"], "resume.pdf", {
      type: "application/pdf",
    });
    vi.mocked(apiRequestForm).mockResolvedValue({ data: document });

    await expect(uploadCandidateDocument(11, 9, file)).resolves.toEqual(
      document,
    );
    const form = vi.mocked(apiRequestForm).mock.calls[0]?.[1];
    expect(form).toBeInstanceOf(FormData);
    expect(form?.get("document")).toBe(file);
  });

  it("downloads binary content and deletes through shared helpers", async () => {
    const blob = new Blob(["pdf"], { type: "application/pdf" });
    vi.mocked(apiDownload).mockResolvedValue(blob);
    vi.mocked(apiRequest).mockResolvedValue(undefined);

    await expect(downloadCandidateDocument(11, 9, 3)).resolves.toBe(blob);
    await deleteCandidateDocument(11, 9, 3);

    expect(apiDownload).toHaveBeenCalledWith(
      "/organisations/11/candidates/9/documents/3/download",
    );
    expect(apiRequest).toHaveBeenCalledWith(
      "/organisations/11/candidates/9/documents/3",
      { method: "DELETE", withCsrf: true },
    );
  });
});
