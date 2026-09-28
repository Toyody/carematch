import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { useAuth } from "@/features/identity/auth-context";
import { getOrganisation } from "@/features/organisation/api";
import { listQualificationDefinitions, listQualificationExpiries } from "./api";
import { ComplianceWorkspace } from "./compliance-workspace";

vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({
  createQualificationDefinition: vi.fn(),
  listQualificationDefinitions: vi.fn(),
  listQualificationExpiries: vi.fn(),
  updateQualificationDefinition: vi.fn(),
}));
afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});

describe("ComplianceWorkspace", () => {
  it("shows expiry records and Admin catalogue controls", async () => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: {
        created_at: "",
        email: "admin@example.test",
        id: 1,
        name: "Admin",
      },
    });
    vi.mocked(getOrganisation).mockResolvedValue({
      id: 2,
      name: "Demo",
      created_at: "",
      membership: { role: "admin" },
    });
    vi.mocked(listQualificationDefinitions).mockResolvedValue([]);
    vi.mocked(listQualificationExpiries).mockResolvedValue([
      {
        candidate_id: 4,
        candidate_name: "Synthetic Candidate",
        candidate_qualification_id: 5,
        expires_on: "2026-09-27",
        qualification_definition_id: 6,
        qualification_name: "CPR",
        status: "expired",
      },
    ]);
    render(<ComplianceWorkspace organisationId={2} />);
    expect(
      await screen.findByText("Qualifications and expiry tracking"),
    ).toBeVisible();
    expect(screen.getByText(/Synthetic Candidate/)).toBeVisible();
    expect(
      screen.getByRole("button", { name: "Add definition" }),
    ).toBeVisible();
  });
});
