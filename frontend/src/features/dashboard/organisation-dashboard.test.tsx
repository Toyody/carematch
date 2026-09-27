import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { ApiError } from "@/lib/api/client";

import { getDashboard, type Dashboard } from "./api";
import { OrganisationDashboard } from "./organisation-dashboard";

vi.mock("./api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("./api")>()),
  getDashboard: vi.fn(),
}));

const populatedDashboard: Dashboard = {
  application_counts: {
    applied: 1,
    screening: 2,
    interview: 3,
    offer: 4,
    hired: 5,
    rejected: 6,
  },
  candidate_count: 12,
  open_job_count: 3,
  recent_application_activity: [
    {
      application_id: 91,
      candidate: { first_name: "Ada", id: 21, last_name: "Lovelace" },
      changed_at: "2026-09-25T12:00:00+00:00",
      from_status: "offer",
      job: { id: 31, title: "Registered Nurse" },
      to_status: "hired",
    },
  ],
};

const emptyDashboard: Dashboard = {
  application_counts: {
    applied: 0,
    screening: 0,
    interview: 0,
    offer: 0,
    hired: 0,
    rejected: 0,
  },
  candidate_count: 0,
  open_job_count: 0,
  recent_application_activity: [],
};

describe("OrganisationDashboard", () => {
  beforeEach(() => {
    vi.mocked(getDashboard).mockResolvedValue(populatedDashboard);
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("shows loading, every status count, and recent enriched activity", async () => {
    render(<OrganisationDashboard organisationId={7} userId={4} />);

    expect(screen.getByText("Loading dashboard…")).toBeInTheDocument();
    expect(await screen.findByText("Ada Lovelace")).toHaveAttribute(
      "href",
      "/organisations/7/applications/91",
    );
    expect(screen.getByText("Registered Nurse")).toBeInTheDocument();
    expect(screen.getByText("Offer to Hired")).toBeInTheDocument();

    for (const [label, value] of [
      ["Applied", "1"],
      ["Screening", "2"],
      ["Interview", "3"],
      ["Offer", "4"],
      ["Hired", "5"],
      ["Rejected", "6"],
    ]) {
      const term = screen.getByText(label);
      expect(term.parentElement).toHaveTextContent(value);
    }
  });

  it("treats zero data as a successful empty dashboard", async () => {
    vi.mocked(getDashboard).mockResolvedValue(emptyDashboard);
    render(<OrganisationDashboard organisationId={7} userId={4} />);

    expect(
      await screen.findByText("No recent application activity."),
    ).toBeInTheDocument();
    expect(screen.getByText("Candidates").parentElement).toHaveTextContent("0");
    expect(screen.getByText("Open jobs").parentElement).toHaveTextContent("0");
  });

  it.each([
    [401, "Your session is no longer valid. Please log in again."],
    [
      404,
      "This organisation is unavailable or you no longer have access to it.",
    ],
    [500, "The dashboard could not be loaded. Please try again."],
  ])("shows safe error feedback for %i", async (status, message) => {
    vi.mocked(getDashboard).mockRejectedValue(new ApiError(status, "Hidden"));
    render(<OrganisationDashboard organisationId={7} userId={4} />);

    expect(await screen.findByRole("alert")).toHaveTextContent(message);
    expect(screen.queryByText("Hidden")).not.toBeInTheDocument();
  });

  it("clears stale data when the organisation changes", async () => {
    let resolveSecond: ((dashboard: Dashboard) => void) | undefined;
    vi.mocked(getDashboard)
      .mockResolvedValueOnce(populatedDashboard)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveSecond = resolve;
          }),
      );
    const { rerender } = render(
      <OrganisationDashboard organisationId={7} userId={4} />,
    );
    await screen.findByText("Ada Lovelace");

    rerender(<OrganisationDashboard organisationId={8} userId={4} />);

    expect(screen.getByText("Loading dashboard…")).toBeInTheDocument();
    expect(screen.queryByText("Ada Lovelace")).not.toBeInTheDocument();
    resolveSecond?.(emptyDashboard);
    expect(
      await screen.findByText("No recent application activity."),
    ).toBeInTheDocument();
  });
});
