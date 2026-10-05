import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useAuth } from "@/features/identity/auth-context";
import { getJob } from "@/features/job/api";
import { getOrganisation } from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";
import { listCandidateMatches, type CandidateMatchPage } from "./api";
import { CandidateMatches } from "./candidate-matches";

const push = vi.fn();
let parameters = new URLSearchParams();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  useSearchParams: () => parameters,
}));
vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/job/api", () => ({ getJob: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({ listCandidateMatches: vi.fn() }));

const page = (distance: number | null = 2.4): CandidateMatchPage => ({
  data: [
    {
      application_status: "screening",
      candidate: {
        first_name: "Synthetic",
        id: 7,
        last_name: "Nurse",
        location: "Melbourne CBD",
        occupation: "Registered Nurse",
      },
      distance_km: distance,
      factors: [
        { status: "satisfied", type: "qualification" },
        { status: "match", type: "occupation" },
        {
          status: distance === null ? "unavailable" : "available",
          type: "distance",
        },
      ],
      occupation_status: "match",
      qualification: {
        attention_required_count: 0,
        not_satisfied_count: 0,
        required_count: 2,
        satisfied_count: 2,
        status: "satisfied",
      },
      rank: 1,
    },
  ],
  links: { first: "", last: "", next: null, prev: null },
  meta: {
    current_page: 1,
    from: 1,
    last_page: 1,
    path: "",
    per_page: 20,
    to: 1,
    total: 1,
  },
});

describe("CandidateMatches", () => {
  beforeEach(() => {
    parameters = new URLSearchParams();
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: { created_at: "", email: "u@example.test", id: 3, name: "User" },
    });
    vi.mocked(getJob).mockResolvedValue({
      closes_at: null,
      created_at: "",
      description: null,
      employment_type: null,
      id: 9,
      location: "Melbourne CBD",
      occupation: "Registered Nurse",
      opened_at: null,
      status: "open",
      title: "Acute care nurse",
      updated_at: "",
    });
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "",
      id: 4,
      membership: { role: "admin" },
      name: "Synthetic tenant",
    });
    vi.mocked(listCandidateMatches).mockResolvedValue(page());
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("shows loading, ranked factors, distance and application status", async () => {
    vi.mocked(useAuth).mockReturnValueOnce({
      error: null,
      isLoading: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: null,
    });
    const { rerender } = render(
      <CandidateMatches jobId={9} organisationId={4} />,
    );
    expect(screen.getByText("Loading candidate matches…")).toBeVisible();
    rerender(<CandidateMatches jobId={9} organisationId={4} />);

    expect(await screen.findByText(/Rank 1: Synthetic Nurse/)).toBeVisible();
    expect(screen.getByText(/Qualifications: satisfied/)).toBeVisible();
    expect(screen.getByText("Occupation: match")).toBeVisible();
    expect(screen.getByText("Distance: 2.4 km")).toBeVisible();
    expect(screen.getByText("Application: screening")).toBeVisible();
  });

  it("does not load tenant data for an unauthenticated visitor", () => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: null,
    });

    render(<CandidateMatches jobId={9} organisationId={4} />);

    expect(
      screen.getByText("You must be signed in to view matches."),
    ).toBeVisible();
    expect(screen.getByRole("link", { name: "Login" })).toHaveAttribute(
      "href",
      "/login",
    );
    expect(getJob).not.toHaveBeenCalled();
    expect(listCandidateMatches).not.toHaveBeenCalled();
  });

  it("shows unavailable distance and applies a radius", async () => {
    vi.mocked(listCandidateMatches).mockResolvedValue(page(null));
    render(<CandidateMatches jobId={9} organisationId={4} />);
    expect(await screen.findByText("Distance: unavailable")).toBeVisible();
    fireEvent.change(screen.getByLabelText("Maximum distance (km)"), {
      target: { value: "15" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Apply radius" }));
    expect(push).toHaveBeenCalledWith(
      "/organisations/4/jobs/9/matches?max_distance_km=15",
    );
  });

  it("shows empty and API validation states", async () => {
    vi.mocked(listCandidateMatches).mockResolvedValueOnce({
      ...page(),
      data: [],
      meta: { ...page().meta, from: null, to: null, total: 0 },
    });
    const { unmount } = render(
      <CandidateMatches jobId={9} organisationId={4} />,
    );
    expect(
      await screen.findByText("No candidates match the current radius."),
    ).toBeVisible();
    unmount();

    vi.mocked(listCandidateMatches).mockRejectedValueOnce(
      new ApiError(422, "Invalid", {
        max_distance_km: [
          "Job coordinates are required when filtering by distance.",
        ],
      }),
    );
    render(<CandidateMatches jobId={9} organisationId={4} />);
    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Job coordinates are required",
    );
  });
});
