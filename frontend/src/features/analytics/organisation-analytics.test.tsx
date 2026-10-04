import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { useAuth } from "@/features/identity/auth-context";
import { ApiError } from "@/lib/api/client";

import { getAnalytics, type Analytics } from "./api";
import { OrganisationAnalytics } from "./organisation-analytics";

const push = vi.fn();
let parameters = new URLSearchParams();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  useSearchParams: () => parameters,
}));
vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("./api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("./api")>()),
  getAnalytics: vi.fn(),
}));

const analytics: Analytics = {
  application_series: [
    { applications: 1, date: "2026-09-01" },
    { applications: 0, date: "2026-09-02" },
  ],
  current_statuses: {
    applied: 1,
    hired: 1,
    interview: 0,
    offer: 0,
    rejected: 1,
    screening: 0,
  },
  funnel: { applied: 3, hired: 1, interview: 1, offer: 1, screening: 2 },
  jobs: [
    {
      applications: 3,
      hired: 1,
      interview: 1,
      job_id: 8,
      job_title: "Registered Nurse",
      offer: 1,
      rejected: 1,
    },
  ],
  period: { from: "2026-09-01", timezone: "UTC", to: "2026-09-30" },
  summary: {
    applications: 3,
    hired: 1,
    new_candidates: 2,
    new_jobs: 1,
    rejected: 1,
  },
  time_to_stage: {
    hired: { median_days: 10.5, sample_size: 1 },
    interview: { median_days: 4, sample_size: 1 },
  },
};

describe("OrganisationAnalytics", () => {
  beforeEach(() => {
    parameters = new URLSearchParams();
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: { created_at: "", email: "user@example.test", id: 5, name: "User" },
    });
    vi.mocked(getAnalytics).mockResolvedValue(analytics);
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("shows loading then authoritative summary, funnel, trend and Job analytics", async () => {
    render(<OrganisationAnalytics organisationId={7} />);

    expect(screen.getByText("Loading analytics…")).toBeInTheDocument();
    expect(
      await screen.findByText(
        "Applications submitted in this period that eventually reached each stage.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText("New candidates").parentElement).toHaveTextContent(
      "2",
    );
    expect(
      screen.getByText("Rejected is a separate outcome: 1"),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Registered Nurse" }),
    ).toHaveAttribute("href", "/organisations/7/jobs/8");
    expect(
      screen.getByRole("row", { name: "2026-09-02 0" }),
    ).toBeInTheDocument();
    expect(screen.getByText("10.5 days")).toBeInTheDocument();
    expect(screen.getAllByText("Sample size: 1")).toHaveLength(2);
  });

  it("shows explicit null and empty-cohort states", async () => {
    vi.mocked(getAnalytics).mockResolvedValue({
      ...analytics,
      jobs: [],
      summary: {
        applications: 0,
        hired: 0,
        new_candidates: 0,
        new_jobs: 0,
        rejected: 0,
      },
      time_to_stage: {
        hired: { median_days: null, sample_size: 0 },
        interview: { median_days: null, sample_size: 0 },
      },
    });

    render(<OrganisationAnalytics organisationId={7} />);

    expect(
      await screen.findByText("No Applications were submitted in this period."),
    ).toBeInTheDocument();
    expect(screen.getAllByText("No qualifying Applications")).toHaveLength(2);
    expect(screen.queryByText("0 days")).not.toBeInTheDocument();
  });

  it("puts the selected period in the URL", async () => {
    render(<OrganisationAnalytics organisationId={7} />);
    await screen.findByText("Period summary");

    fireEvent.change(screen.getByLabelText("From"), {
      target: { value: "2026-08-01" },
    });
    fireEvent.change(screen.getByLabelText("To"), {
      target: { value: "2026-08-31" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Apply period" }));

    expect(push).toHaveBeenCalledWith(
      "/organisations/7/analytics?from=2026-08-01&to=2026-08-31",
    );
  });

  it("passes query-string dates to the API", async () => {
    parameters = new URLSearchParams("from=2026-08-01&to=2026-08-31");
    render(<OrganisationAnalytics organisationId={7} />);

    await screen.findByText("Period summary");
    expect(getAnalytics).toHaveBeenCalledWith(7, {
      from: "2026-08-01",
      to: "2026-08-31",
    });
  });

  it("shows safe validation and tenant errors", async () => {
    vi.mocked(getAnalytics).mockRejectedValue(
      new ApiError(422, "Hidden", {
        to: ["The reporting period must not exceed 365 days."],
      }),
    );
    const { rerender } = render(<OrganisationAnalytics organisationId={7} />);
    expect(await screen.findByRole("alert")).toHaveTextContent(
      "must not exceed 365 days",
    );

    vi.mocked(getAnalytics).mockRejectedValue(
      new ApiError(404, "Hidden tenant"),
    );
    rerender(<OrganisationAnalytics organisationId={8} />);
    expect(await screen.findByRole("alert")).toHaveTextContent("unavailable");
    expect(screen.queryByText("Hidden tenant")).not.toBeInTheDocument();
  });

  it("does not show stale data when the Organisation changes", async () => {
    let resolveSecond: ((value: Analytics) => void) | undefined;
    vi.mocked(getAnalytics)
      .mockResolvedValueOnce(analytics)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveSecond = resolve;
          }),
      );
    const { rerender } = render(<OrganisationAnalytics organisationId={7} />);
    await screen.findByText("Registered Nurse");

    rerender(<OrganisationAnalytics organisationId={8} />);
    expect(screen.getByText("Loading analytics…")).toBeInTheDocument();
    expect(screen.queryByText("Registered Nurse")).not.toBeInTheDocument();
    resolveSecond?.({ ...analytics, jobs: [] });
    expect(
      await screen.findByText("No Applications were submitted in this period."),
    ).toBeInTheDocument();
  });

  it("does not load tenant analytics for a guest", () => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: null,
    });
    render(<OrganisationAnalytics organisationId={7} />);

    expect(screen.getByRole("alert")).toHaveTextContent("signed in");
    expect(getAnalytics).not.toHaveBeenCalled();
  });
});
