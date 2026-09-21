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
import { createJob, type Job } from "./api";
import { CreateJob } from "./create-job";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));
vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({ createJob: vi.fn() }));
const job: Job = {
  closes_at: null,
  created_at: "",
  description: null,
  employment_type: null,
  id: 9,
  location: null,
  occupation: null,
  opened_at: null,
  status: "draft",
  title: "Nurse",
  updated_at: "",
};

describe("CreateJob", () => {
  beforeEach(() => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: { created_at: "", email: "u@example.test", id: 7, name: "User" },
    });
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "",
      id: 11,
      membership: { role: "recruiter" },
      name: "North",
    });
  });
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("creates a draft profile and navigates to tenant-scoped detail", async () => {
    vi.mocked(createJob).mockResolvedValue(job);
    render(<CreateJob organisationId={11} />);
    await screen.findByRole("heading", { name: "Add job" });
    fireEvent.change(screen.getByLabelText("Title"), {
      target: { value: "Nurse" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Create draft" }));
    await waitFor(() =>
      expect(push).toHaveBeenCalledWith("/organisations/11/jobs/9"),
    );
    expect(createJob).toHaveBeenCalledWith(
      11,
      expect.objectContaining({ title: "Nurse" }),
    );
  });

  it("renders validation and Hiring Manager denial safely", async () => {
    vi.mocked(createJob).mockRejectedValue(
      new ApiError(422, "The given data was invalid.", {
        title: ["The title field is required."],
      }),
    );
    const { unmount } = render(<CreateJob organisationId={11} />);
    await screen.findByLabelText("Title");
    fireEvent.change(screen.getByLabelText("Title"), {
      target: { value: "Nurse" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Create draft" }));
    expect(
      await screen.findByText("The title field is required."),
    ).toBeInTheDocument();
    unmount();
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "",
      id: 11,
      membership: { role: "hiring_manager" },
      name: "North",
    });
    render(<CreateJob organisationId={11} />);
    expect(
      await screen.findByText("Your Organisation role cannot create jobs."),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Create draft" }),
    ).not.toBeInTheDocument();
  });
});
