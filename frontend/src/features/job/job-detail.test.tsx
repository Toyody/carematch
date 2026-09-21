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
import { getJob, transitionJob, updateJob, type Job } from "./api";
import { JobDetail } from "./job-detail";

vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({
  getJob: vi.fn(),
  transitionJob: vi.fn(),
  updateJob: vi.fn(),
}));

const job: Job = {
  closes_at: null,
  created_at: "2026-09-21T00:00:00+00:00",
  description: "Ward",
  employment_type: "full_time",
  id: 9,
  location: "Tokyo",
  occupation: "Nurse",
  opened_at: null,
  status: "draft",
  title: "Nurse",
  updated_at: "2026-09-21T00:00:00+00:00",
};
const organisation = (
  id: number,
  role: "admin" | "recruiter" | "hiring_manager",
) => ({
  created_at: "2026-09-20T00:00:00+00:00",
  id,
  membership: { role },
  name: id === 11 ? "North" : "South",
});

describe("JobDetail", () => {
  beforeEach(() => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: { created_at: "", email: "u@example.test", id: 7, name: "User" },
    });
    vi.mocked(getOrganisation).mockResolvedValue(organisation(11, "admin"));
    vi.mocked(getJob).mockResolvedValue(job);
  });
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("edits and performs only state-appropriate lifecycle actions", async () => {
    vi.mocked(updateJob).mockResolvedValue({ ...job, title: "Senior Nurse" });
    vi.mocked(transitionJob).mockResolvedValue({ ...job, status: "open" });
    render(<JobDetail jobId={9} organisationId={11} />);
    await screen.findByLabelText("Title");
    expect(screen.getByRole("button", { name: "Open job" })).toBeVisible();
    expect(
      screen.queryByRole("button", { name: "Close job" }),
    ).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Title"), {
      target: { value: "Senior Nurse" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Save job" }));
    expect(await screen.findByText("Job saved.")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Open job" }));
    await waitFor(() =>
      expect(transitionJob).toHaveBeenCalledWith(11, 9, "open"),
    );
  });

  it("shows stable 409 conflict feedback", async () => {
    vi.mocked(transitionJob).mockRejectedValue(
      new ApiError(409, "The requested job status transition is not allowed."),
    );
    render(<JobDetail jobId={9} organisationId={11} />);
    await screen.findByLabelText("Title");
    fireEvent.click(screen.getByRole("button", { name: "Open job" }));
    expect(
      await screen.findByText(
        "The requested job status transition is not allowed.",
      ),
    ).toBeInTheDocument();
  });

  it("is read-only for Hiring Manager and hides lifecycle actions", async () => {
    vi.mocked(getOrganisation).mockResolvedValue(
      organisation(11, "hiring_manager"),
    );
    render(<JobDetail jobId={9} organisationId={11} />);
    expect(
      await screen.findByText(
        "Your Organisation role has read-only Job access.",
      ),
    ).toBeInTheDocument();
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
  });

  it("shows edit controls for Recruiter and no lifecycle controls for archived jobs", async () => {
    vi.mocked(getOrganisation).mockResolvedValue(organisation(11, "recruiter"));
    vi.mocked(getJob).mockResolvedValue({ ...job, status: "archived" });
    render(<JobDetail jobId={9} organisationId={11} />);
    expect(
      await screen.findByRole("button", { name: "Save job" }),
    ).toBeVisible();
    expect(
      screen.getByText("This archived job has no further lifecycle actions."),
    ).toBeVisible();
    expect(
      screen.queryByLabelText("Job lifecycle actions"),
    ).not.toBeInTheDocument();
  });

  it("uses safe tenant 404 and keys state to tenant and job", async () => {
    vi.mocked(getJob).mockImplementation(async (org) =>
      org === 11 ? job : { ...job, id: 20, title: "South job" },
    );
    const { rerender } = render(<JobDetail jobId={9} organisationId={11} />);
    await screen.findByRole("heading", { name: "Nurse" });
    rerender(<JobDetail jobId={20} organisationId={22} />);
    expect(screen.queryByText("Nurse")).not.toBeInTheDocument();
    expect(
      await screen.findByRole("heading", { name: "South job" }),
    ).toBeInTheDocument();
    vi.mocked(getJob).mockRejectedValue(new ApiError(404, "Not Found"));
    rerender(<JobDetail jobId={999} organisationId={22} />);
    expect(
      await screen.findByRole("heading", { name: "Job unavailable" }),
    ).toBeInTheDocument();
  });
});
