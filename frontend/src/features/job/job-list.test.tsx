import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useAuth } from "@/features/identity/auth-context";
import { getOrganisation } from "@/features/organisation/api";
import { listJobs, type Job, type JobPage } from "./api";
import { JobList } from "./job-list";

const push = vi.fn();
let parameters = new URLSearchParams();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  useSearchParams: () => parameters,
}));
vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({ listJobs: vi.fn() }));
const job: Job = {
  closes_at: null,
  created_at: "",
  description: null,
  employment_type: "full_time",
  id: 9,
  location: "Tokyo",
  occupation: "Nurse",
  opened_at: null,
  status: "draft",
  title: "Nurse",
  updated_at: "",
};
const page = (data: Job[] = [job]): JobPage => ({
  data,
  links: { first: "", last: "", next: null, prev: null },
  meta: {
    current_page: 1,
    from: data.length ? 1 : null,
    last_page: 2,
    path: "",
    per_page: 20,
    to: data.length || null,
    total: data.length,
  },
});

describe("JobList", () => {
  beforeEach(() => {
    parameters = new URLSearchParams();
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
      membership: { role: "admin" },
      name: "North",
    });
    vi.mocked(listJobs).mockResolvedValue(page());
  });
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("lists, filters, sorts, paginates, and links tenant-scoped jobs", async () => {
    parameters = new URLSearchParams("status=draft&occupation=Nurse");
    render(<JobList organisationId={11} />);
    expect(await screen.findByRole("link", { name: "Nurse" })).toHaveAttribute(
      "href",
      "/organisations/11/jobs/9",
    );
    expect(screen.getByRole("link", { name: "Next" })).toHaveAttribute(
      "href",
      "/organisations/11/jobs?status=draft&occupation=Nurse&page=2",
    );
    expect(screen.getByText("full_time")).toBeVisible();
    expect(screen.getByText("No opening date")).toBeVisible();
    fireEvent.change(screen.getByLabelText("Search title"), {
      target: { value: "Ward" },
    });
    fireEvent.change(screen.getByLabelText("Status"), {
      target: { value: "open" },
    });
    fireEvent.change(screen.getByLabelText("Occupation"), {
      target: { value: "Nurse" },
    });
    fireEvent.change(screen.getByLabelText("Employment type"), {
      target: { value: "full_time" },
    });
    fireEvent.change(screen.getByLabelText("Sort by"), {
      target: { value: "opened_at" },
    });
    fireEvent.change(screen.getByLabelText("Direction"), {
      target: { value: "asc" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Apply" }));
    expect(push).toHaveBeenCalledWith(
      "/organisations/11/jobs?search=Ward&status=open&occupation=Nurse&employment_type=full_time&sort=opened_at&direction=asc",
    );
  });

  it("shows a valid empty state", async () => {
    vi.mocked(listJobs).mockResolvedValue(page([]));
    render(<JobList organisationId={11} />);
    expect(
      await screen.findByText("No jobs match the current filters."),
    ).toBeInTheDocument();
  });

  it("hides creation for Hiring Manager", async () => {
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "",
      id: 11,
      membership: { role: "hiring_manager" },
      name: "North",
    });
    render(<JobList organisationId={11} />);
    await screen.findByRole("heading", { name: "Jobs" });
    expect(
      screen.queryByRole("link", { name: "Add job" }),
    ).not.toBeInTheDocument();
  });
});
