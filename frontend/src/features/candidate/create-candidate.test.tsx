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

import { createCandidate, type Candidate } from "./api";
import { CreateCandidate } from "./create-candidate";

const push = vi.fn();

vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));
vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({
  getOrganisation: vi.fn(),
}));
vi.mock("./api", () => ({ createCandidate: vi.fn() }));

const candidate: Candidate = {
  availability: null,
  created_at: "2026-09-21T00:00:00+00:00",
  email: "aiko@example.test",
  first_name: "Aiko",
  id: 9,
  last_name: "Tanaka",
  location: null,
  notes: null,
  occupation: "Nurse",
  phone: null,
  updated_at: "2026-09-21T00:00:00+00:00",
};

describe("CreateCandidate", () => {
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
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "2026-09-20T00:00:00+00:00",
      id: 11,
      membership: { role: "recruiter" },
      name: "Northside",
    });
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("creates a candidate and navigates to tenant-scoped detail", async () => {
    vi.mocked(createCandidate).mockResolvedValue(candidate);
    render(<CreateCandidate organisationId={11} />);
    await screen.findByRole("heading", { name: "Add candidate" });

    fireEvent.change(screen.getByLabelText("First name"), {
      target: { value: "Aiko" },
    });
    fireEvent.change(screen.getByLabelText("Last name"), {
      target: { value: "Tanaka" },
    });
    fireEvent.change(screen.getByLabelText("Email"), {
      target: { value: "aiko@example.test" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Create candidate" }));

    await waitFor(() =>
      expect(push).toHaveBeenCalledWith("/organisations/11/candidates/9"),
    );
    expect(createCandidate).toHaveBeenCalledWith(
      11,
      expect.objectContaining({
        email: "aiko@example.test",
        first_name: "Aiko",
        last_name: "Tanaka",
      }),
    );
  });

  it("shows validation errors", async () => {
    vi.mocked(createCandidate).mockRejectedValue(
      new ApiError(422, "The given data was invalid.", {
        first_name: ["The first name field is required."],
      }),
    );
    render(<CreateCandidate organisationId={11} />);
    await screen.findByRole("heading", { name: "Add candidate" });

    fireEvent.change(screen.getByLabelText("First name"), {
      target: { value: "Aiko" },
    });
    fireEvent.change(screen.getByLabelText("Last name"), {
      target: { value: "Tanaka" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Create candidate" }));

    expect(
      await screen.findByText("The first name field is required."),
    ).toBeInTheDocument();
  });

  it("does not show the form to a Hiring Manager", async () => {
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "2026-09-20T00:00:00+00:00",
      id: 11,
      membership: { role: "hiring_manager" },
      name: "Northside",
    });
    render(<CreateCandidate organisationId={11} />);

    expect(
      await screen.findByText(
        "Your Organisation role cannot create candidates.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Create candidate" }),
    ).not.toBeInTheDocument();
  });
});
