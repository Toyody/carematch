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
import {
  getApplication,
  getApplicationHistory,
  transitionApplication,
} from "./api";
import { ApplicationDetail } from "./application-detail";

vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({
  getApplication: vi.fn(),
  getApplicationHistory: vi.fn(),
  transitionApplication: vi.fn(),
}));

const application = {
  applied_at: "2026-09-23T00:00:00+00:00",
  candidate: { first_name: "Ada", id: 7, last_name: "Lovelace" },
  created_at: "2026-09-23T00:00:00+00:00",
  created_by_user_id: 2,
  id: 9,
  job: { id: 8, title: "Nurse" },
  status: "applied" as const,
  updated_at: "2026-09-23T00:00:00+00:00",
};

const initialHistory = [
  {
    changed_by_user_id: 2,
    created_at: "2026-09-23T00:00:00+00:00",
    from_status: null,
    id: 1,
    note: null,
    to_status: "applied" as const,
  },
];

describe("ApplicationDetail", () => {
  beforeEach(() => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: { created_at: "", email: "u@example.test", id: 1, name: "User" },
    });
    mockRole("admin");
    vi.mocked(getApplication).mockResolvedValue(application);
    vi.mocked(getApplicationHistory).mockResolvedValue(initialHistory);
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("loads direct detail, current status, and the natural initial history event", async () => {
    render(<ApplicationDetail applicationId={9} organisationId={4} />);
    expect(
      await screen.findByRole("heading", { name: "Ada Lovelace" }),
    ).toBeVisible();
    expect(screen.getByText("Nurse")).toBeVisible();
    expect(screen.getByText("applied")).toBeVisible();
    expect(screen.getByText("Application created / Applied")).toBeVisible();
    expect(screen.getByText("Changed by user 2")).toBeVisible();
  });

  it.each([
    ["admin", "applied", "Move to Screening", "Reject"],
    ["recruiter", "screening", "Move to Interview", "Reject"],
    ["hiring_manager", "interview", "Move to Offer", "Reject"],
  ] as const)(
    "shows valid controls for %s at %s",
    async (role, status, primary, reject) => {
      mockRole(role);
      vi.mocked(getApplication).mockResolvedValue({ ...application, status });
      render(<ApplicationDetail applicationId={9} organisationId={4} />);
      expect(
        await screen.findByRole("button", { name: primary }),
      ).toBeVisible();
      expect(screen.getByRole("button", { name: reject })).toBeVisible();
    },
  );

  it.each([
    ["hiring_manager", "applied"],
    ["hiring_manager", "screening"],
    ["hiring_manager", "offer"],
    ["admin", "hired"],
    ["recruiter", "rejected"],
  ] as const)("hides transitions for %s at %s", async (role, status) => {
    mockRole(role);
    vi.mocked(getApplication).mockResolvedValue({ ...application, status });
    render(<ApplicationDetail applicationId={9} organisationId={4} />);
    expect(
      await screen.findByText(
        "No pipeline actions are available for this status and role.",
      ),
    ).toBeVisible();
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
  });

  it("submits the optional note and adopts server status and refreshed history", async () => {
    const screening = { ...application, status: "screening" as const };
    const history = [
      ...initialHistory,
      {
        changed_by_user_id: 1,
        created_at: "2026-09-23T01:00:00+00:00",
        from_status: "applied" as const,
        id: 2,
        note: "Passed review",
        to_status: "screening" as const,
      },
    ];
    vi.mocked(transitionApplication).mockResolvedValue(screening);
    vi.mocked(getApplicationHistory)
      .mockResolvedValueOnce(initialHistory)
      .mockResolvedValueOnce(history);
    render(<ApplicationDetail applicationId={9} organisationId={4} />);
    fireEvent.change(await screen.findByLabelText("Optional note"), {
      target: { value: "Passed review" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Move to Screening" }));

    await waitFor(() =>
      expect(transitionApplication).toHaveBeenCalledWith(4, 9, {
        note: "Passed review",
        to_status: "screening",
      }),
    );
    expect(await screen.findByText("applied → screening")).toBeVisible();
    expect(screen.getByText("Passed review")).toBeVisible();
    expect(screen.getByRole("status")).toHaveTextContent(
      "Application moved to screening.",
    );
    expect(screen.getByLabelText("Optional note")).toHaveValue("");
    expect(
      screen.getByRole("button", { name: "Move to Interview" }),
    ).toBeVisible();
  });

  it("shows a conflict and refetches authoritative application and history", async () => {
    const screening = { ...application, status: "screening" as const };
    vi.mocked(transitionApplication).mockRejectedValue(
      new ApiError(
        409,
        "The requested application status transition is not allowed.",
      ),
    );
    vi.mocked(getApplication)
      .mockResolvedValueOnce(application)
      .mockResolvedValueOnce(screening);
    vi.mocked(getApplicationHistory)
      .mockResolvedValueOnce(initialHistory)
      .mockResolvedValueOnce(initialHistory);
    render(<ApplicationDetail applicationId={9} organisationId={4} />);
    fireEvent.click(
      await screen.findByRole("button", { name: "Move to Screening" }),
    );

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "The requested application status transition is not allowed.",
    );
    expect(
      screen.getByRole("button", { name: "Move to Interview" }),
    ).toBeVisible();
    expect(getApplication).toHaveBeenCalledTimes(2);
    expect(getApplicationHistory).toHaveBeenCalledTimes(2);
  });

  it.each([
    [403, "Your Organisation role cannot perform this application transition."],
    [419, "Your session has expired."],
  ])("handles mutation error %i", async (status, message) => {
    vi.mocked(transitionApplication).mockRejectedValue(
      new ApiError(status, message),
    );
    render(<ApplicationDetail applicationId={9} organisationId={4} />);
    fireEvent.click(await screen.findByRole("button", { name: "Reject" }));
    expect(await screen.findByRole("alert")).toHaveTextContent(message);
  });

  it("clears stale details and history and fails safely for a new tenant", async () => {
    vi.mocked(getApplication).mockImplementation(async (organisationId) => {
      if (organisationId === 5) throw new ApiError(404, "Not Found");
      return application;
    });
    const { rerender } = render(
      <ApplicationDetail applicationId={9} organisationId={4} />,
    );
    await screen.findByRole("heading", { name: "Ada Lovelace" });
    rerender(<ApplicationDetail applicationId={9} organisationId={5} />);
    expect(screen.queryByText("Nurse")).not.toBeInTheDocument();
    expect(
      screen.queryByText("Application created / Applied"),
    ).not.toBeInTheDocument();
    expect(
      await screen.findByRole("heading", { name: "Application unavailable" }),
    ).toBeVisible();
  });
});

function mockRole(role: "admin" | "hiring_manager" | "recruiter") {
  vi.mocked(getOrganisation).mockResolvedValue({
    created_at: "",
    id: 4,
    membership: { role },
    name: "North",
  });
}
