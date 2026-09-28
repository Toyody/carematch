import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  listCandidateQualifications,
  listQualificationDefinitions,
} from "./api";
import { CandidateQualifications } from "./candidate-qualifications";

vi.mock("./api", () => ({
  createCandidateQualification: vi.fn(),
  deleteCandidateQualification: vi.fn(),
  listCandidateQualifications: vi.fn(),
  listQualificationDefinitions: vi.fn(),
  updateCandidateQualification: vi.fn(),
}));

describe("CandidateQualifications", () => {
  beforeEach(() => {
    vi.mocked(listQualificationDefinitions).mockResolvedValue([]);
    vi.mocked(listCandidateQualifications).mockResolvedValue([]);
  });
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("shows loading, empty, and manager controls", async () => {
    render(
      <CandidateQualifications candidateId={3} mayManage organisationId={2} />,
    );
    expect(screen.getByText("Loading qualifications…")).toBeVisible();
    expect(
      await screen.findByText("No qualifications recorded."),
    ).toBeVisible();
    expect(
      screen.getByRole("button", { name: "Add qualification" }),
    ).toBeVisible();
  });

  it("keeps Hiring Manager qualification access read-only", async () => {
    vi.mocked(listCandidateQualifications).mockResolvedValue([
      {
        created_at: "2026-01-01T00:00:00Z",
        credential_number: null,
        expires_on: null,
        id: 1,
        issued_on: null,
        issuer: null,
        qualification_definition_id: 4,
        qualification_name: "CPR",
        status: "valid",
        updated_at: "2026-01-01T00:00:00Z",
      },
    ]);
    render(
      <CandidateQualifications
        candidateId={3}
        mayManage={false}
        organisationId={2}
      />,
    );
    expect(await screen.findByText("CPR")).toBeVisible();
    expect(
      screen.getByText(
        "Your Organisation role has read-only qualification access.",
      ),
    ).toBeVisible();
    expect(
      screen.queryByRole("button", { name: "Remove" }),
    ).not.toBeInTheDocument();
  });
});
