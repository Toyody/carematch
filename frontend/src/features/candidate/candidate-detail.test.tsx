import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { useAuth } from "@/features/identity/auth-context";
import { getOrganisation } from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";

import { getCandidate, updateCandidate, type Candidate } from "./api";
import { CandidateDetail } from "./candidate-detail";

vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({
  getOrganisation: vi.fn(),
}));
vi.mock("./api", () => ({
  getCandidate: vi.fn(),
  updateCandidate: vi.fn(),
}));
vi.mock("./candidate-documents", () => ({
  CandidateDocuments: () => <section>Documents</section>,
}));

const candidate: Candidate = {
  availability: "Immediately",
  created_at: "2026-09-21T00:00:00+00:00",
  email: "aiko@example.test",
  first_name: "Aiko",
  id: 9,
  last_name: "Tanaka",
  location: "Tokyo",
  notes: "Experienced",
  occupation: "Nurse",
  phone: "+81 90 0000 0000",
  updated_at: "2026-09-21T00:00:00+00:00",
};

function organisation(
  id: number,
  role: "admin" | "recruiter" | "hiring_manager",
) {
  return {
    created_at: "2026-09-20T00:00:00+00:00",
    id,
    membership: { role },
    name: id === 11 ? "Northside" : "Southside",
  };
}

describe("CandidateDetail", () => {
  beforeEach(() => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: {
        created_at: "2026-09-20T00:00:00+00:00",
        email: "user@example.test",
        id: 7,
        name: "User",
      },
    });
    vi.mocked(getOrganisation).mockResolvedValue(organisation(11, "admin"));
    vi.mocked(getCandidate).mockResolvedValue(candidate);
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("loads a directly addressed candidate and shows edit controls for Admin", async () => {
    render(<CandidateDetail candidateId={9} organisationId={11} />);

    expect(screen.getByText("Loading candidate…")).toBeInTheDocument();
    expect(
      await screen.findByRole("heading", { name: "Aiko Tanaka" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Save candidate" }),
    ).toBeVisible();
    expect(getCandidate).toHaveBeenCalledWith(11, 9);
  });

  it("shows edit controls for a Recruiter", async () => {
    vi.mocked(getOrganisation).mockResolvedValue(organisation(11, "recruiter"));
    render(<CandidateDetail candidateId={9} organisationId={11} />);

    expect(
      await screen.findByRole("button", { name: "Save candidate" }),
    ).toBeVisible();
  });

  it("shows a read-only detail for a Hiring Manager", async () => {
    vi.mocked(getOrganisation).mockResolvedValue(
      organisation(11, "hiring_manager"),
    );
    render(<CandidateDetail candidateId={9} organisationId={11} />);

    expect(
      await screen.findByText(
        "Your Organisation role has read-only Candidate access.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText("aiko@example.test")).toBeVisible();
    expect(
      screen.queryByRole("button", { name: "Save candidate" }),
    ).not.toBeInTheDocument();
  });

  it("updates a candidate and shows success", async () => {
    vi.mocked(updateCandidate).mockResolvedValue({
      ...candidate,
      first_name: "Updated",
    });
    render(<CandidateDetail candidateId={9} organisationId={11} />);
    await screen.findByDisplayValue("Aiko");

    fireEvent.change(screen.getByLabelText("First name"), {
      target: { value: "Updated" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Save candidate" }));

    expect(await screen.findByText("Candidate saved.")).toBeInTheDocument();
    expect(updateCandidate).toHaveBeenCalledWith(
      11,
      9,
      expect.objectContaining({ first_name: "Updated" }),
    );
  });

  it("shows backend validation errors", async () => {
    vi.mocked(updateCandidate).mockRejectedValue(
      new ApiError(422, "The given data was invalid.", {
        email: ["The email field must be a valid email address."],
      }),
    );
    render(<CandidateDetail candidateId={9} organisationId={11} />);
    await screen.findByDisplayValue("Aiko");

    fireEvent.click(screen.getByRole("button", { name: "Save candidate" }));

    expect(
      await screen.findByText("The email field must be a valid email address."),
    ).toBeInTheDocument();
  });

  it("uses a safe unavailable state for tenant-scoped 404", async () => {
    vi.mocked(getCandidate).mockRejectedValue(new ApiError(404, "Not Found"));
    render(<CandidateDetail candidateId={999} organisationId={11} />);

    expect(
      await screen.findByRole("heading", { name: "Candidate unavailable" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("Not Found")).not.toBeInTheDocument();
  });

  it("does not retain candidate data when the Organisation route changes", async () => {
    vi.mocked(getOrganisation).mockImplementation(async (id) =>
      organisation(id, "admin"),
    );
    vi.mocked(getCandidate).mockImplementation(async (organisationId) =>
      organisationId === 11
        ? candidate
        : { ...candidate, first_name: "South", id: 20 },
    );
    const { rerender } = render(
      <CandidateDetail candidateId={9} organisationId={11} />,
    );
    await screen.findByRole("heading", { name: "Aiko Tanaka" });

    rerender(<CandidateDetail candidateId={20} organisationId={22} />);

    expect(screen.queryByText("Aiko Tanaka")).not.toBeInTheDocument();
    expect(
      await screen.findByRole("heading", { name: "South Tanaka" }),
    ).toBeInTheDocument();
    await waitFor(() => expect(getCandidate).toHaveBeenLastCalledWith(22, 20));
  });
});
