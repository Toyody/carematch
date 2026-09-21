import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { listCandidates } from "@/features/candidate/api";
import { useAuth } from "@/features/identity/auth-context";
import { listJobs } from "@/features/job/api";
import { getOrganisation } from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";
import { createApplication } from "./api";
import { CreateApplication } from "./create-application";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));
vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("@/features/job/api", () => ({ listJobs: vi.fn() }));
vi.mock("@/features/candidate/api", () => ({ listCandidates: vi.fn() }));
vi.mock("./api", () => ({ createApplication: vi.fn() }));

describe("CreateApplication", () => {
  beforeEach(() => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: { created_at: "", email: "u@example.test", id: 1, name: "User" },
    });
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "",
      id: 4,
      membership: { role: "admin" },
      name: "North",
    });
    vi.mocked(listJobs).mockResolvedValue({
      data: [{ id: 8, title: "Nurse", status: "open" }],
      links: {},
      meta: {},
    } as never);
    vi.mocked(listCandidates).mockResolvedValue({
      data: [{ id: 7, first_name: "Ada", last_name: "Lovelace" }],
      links: {},
      meta: {},
    } as never);
    vi.mocked(createApplication).mockResolvedValue({ id: 9 } as never);
  });
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("offers only fetched open jobs and creates an application", async () => {
    render(<CreateApplication organisationId={4} />);
    await screen.findByRole("option", { name: "Nurse" });
    fireEvent.change(screen.getByLabelText("Open job"), {
      target: { value: "8" },
    });
    fireEvent.change(screen.getByLabelText("Candidate"), {
      target: { value: "7" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Create application" }));
    await waitFor(() =>
      expect(createApplication).toHaveBeenCalledWith(4, {
        candidate_id: 7,
        job_id: 8,
      }),
    );
    expect(push).toHaveBeenCalledWith("/organisations/4/applications/9");
    expect(listJobs).toHaveBeenCalledWith(4, {
      per_page: "100",
      status: "open",
    });
  });

  it("hides creation from Hiring Manager and shows conflict errors", async () => {
    vi.mocked(getOrganisation).mockResolvedValueOnce({
      created_at: "",
      id: 4,
      membership: { role: "hiring_manager" },
      name: "North",
    });
    const { rerender } = render(<CreateApplication organisationId={4} />);
    expect(
      await screen.findByText(
        "Your Organisation role cannot create applications.",
      ),
    ).toBeVisible();

    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "",
      id: 5,
      membership: { role: "recruiter" },
      name: "South",
    });
    vi.mocked(createApplication).mockRejectedValue(
      new ApiError(
        409,
        "This candidate already has an application for this job.",
      ),
    );
    rerender(<CreateApplication organisationId={5} />);
    await screen.findByRole("option", { name: "Nurse" });
    fireEvent.change(screen.getByLabelText("Open job"), {
      target: { value: "8" },
    });
    fireEvent.change(screen.getByLabelText("Candidate"), {
      target: { value: "7" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Create application" }));
    expect(
      await screen.findByText(
        "This candidate already has an application for this job.",
      ),
    ).toBeVisible();
  });
});
