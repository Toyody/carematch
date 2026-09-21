import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { useAuth } from "@/features/identity/auth-context";
import {
  getOrganisation,
  type OrganisationRole,
} from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";

import { listCandidates, type Candidate, type CandidatePage } from "./api";
import { CandidateList } from "./candidate-list";

const push = vi.fn();
let parameters = new URLSearchParams();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  useSearchParams: () => parameters,
}));
vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({
  getOrganisation: vi.fn(),
}));
vi.mock("./api", () => ({ listCandidates: vi.fn() }));

const candidate: Candidate = {
  availability: null,
  created_at: "2026-09-21T00:00:00+00:00",
  email: "aiko@example.test",
  first_name: "Aiko",
  id: 9,
  last_name: "Tanaka",
  location: "Tokyo",
  notes: null,
  occupation: "Nurse",
  phone: null,
  updated_at: "2026-09-21T00:00:00+00:00",
};

function organisation(id = 11, role: OrganisationRole = "admin") {
  return {
    created_at: "2026-09-20T00:00:00+00:00",
    id,
    membership: { role },
    name: id === 11 ? "Northside Health" : "Southside Care",
  };
}

function page(data: Candidate[] = [candidate]): CandidatePage {
  return {
    data,
    links: { first: "", last: "", next: null, prev: null },
    meta: {
      current_page: 1,
      from: data.length ? 1 : null,
      last_page: 1,
      path: "",
      per_page: 20,
      to: data.length || null,
      total: data.length,
    },
  };
}

describe("CandidateList", () => {
  beforeEach(() => {
    parameters = new URLSearchParams();
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
    vi.mocked(getOrganisation).mockResolvedValue(organisation());
    vi.mocked(listCandidates).mockResolvedValue(page());
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("shows loading and then multiple candidate results", async () => {
    const second = { ...candidate, first_name: "Ben", id: 10 };
    vi.mocked(listCandidates).mockResolvedValue(page([candidate, second]));
    render(<CandidateList organisationId={11} />);

    expect(screen.getByText("Loading candidates…")).toBeInTheDocument();
    expect(
      await screen.findByRole("link", { name: "Aiko Tanaka" }),
    ).toHaveAttribute("href", "/organisations/11/candidates/9");
    expect(screen.getByRole("link", { name: "Ben Tanaka" })).toBeVisible();
    expect(screen.getAllByText("aiko@example.test")).toHaveLength(2);
    expect(screen.getAllByText("Nurse")).toHaveLength(2);
  });

  it("shows a valid empty state", async () => {
    vi.mocked(listCandidates).mockResolvedValue(page([]));
    render(<CandidateList organisationId={11} />);

    expect(
      await screen.findByText("No candidates match the current filters."),
    ).toBeInTheDocument();
  });

  it("shows a safe tenant error and a distinct session error", async () => {
    vi.mocked(listCandidates).mockRejectedValueOnce(
      new ApiError(404, "Not Found"),
    );
    const { unmount } = render(<CandidateList organisationId={11} />);

    expect(
      await screen.findByRole("heading", { name: "Candidates unavailable" }),
    ).toBeInTheDocument();
    unmount();

    vi.mocked(listCandidates).mockRejectedValueOnce(
      new ApiError(401, "Unauthenticated"),
    );
    render(<CandidateList organisationId={11} />);

    expect(
      await screen.findByRole("heading", { name: "Session expired" }),
    ).toBeInTheDocument();
  });

  it("writes search, occupation, and sorting to the route", async () => {
    render(<CandidateList organisationId={11} />);
    await screen.findByRole("heading", { name: "Candidates" });

    fireEvent.change(screen.getByLabelText("Search"), {
      target: { value: "Aiko" },
    });
    fireEvent.change(screen.getByLabelText("Occupation"), {
      target: { value: "Nurse" },
    });
    fireEvent.change(screen.getByLabelText("Sort by"), {
      target: { value: "name" },
    });
    fireEvent.change(screen.getByLabelText("Direction"), {
      target: { value: "asc" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Apply" }));

    expect(push).toHaveBeenCalledWith(
      "/organisations/11/candidates?search=Aiko&occupation=Nurse&sort=name&direction=asc",
    );
  });

  it("preserves filters in pagination navigation", async () => {
    parameters = new URLSearchParams("search=Aiko&sort=name");
    const paginated = page();
    paginated.meta.last_page = 2;
    vi.mocked(listCandidates).mockResolvedValue(paginated);
    render(<CandidateList organisationId={11} />);

    expect(await screen.findByRole("link", { name: "Next" })).toHaveAttribute(
      "href",
      "/organisations/11/candidates?search=Aiko&sort=name&page=2",
    );
    expect(listCandidates).toHaveBeenCalledWith(11, {
      search: "Aiko",
      sort: "name",
    });
  });

  it("hides create controls for a hiring manager", async () => {
    vi.mocked(getOrganisation).mockResolvedValue(
      organisation(11, "hiring_manager"),
    );
    render(<CandidateList organisationId={11} />);

    await screen.findByRole("heading", { name: "Candidates" });
    expect(
      screen.queryByRole("link", { name: "Add candidate" }),
    ).not.toBeInTheDocument();
  });

  it("does not retain another tenant's candidates when switching organisations", async () => {
    vi.mocked(getOrganisation).mockImplementation(async (id) =>
      organisation(id),
    );
    vi.mocked(listCandidates).mockImplementation(async (id) =>
      page([
        id === 11 ? candidate : { ...candidate, first_name: "South", id: 20 },
      ]),
    );
    const { rerender } = render(<CandidateList organisationId={11} />);
    await screen.findByRole("link", { name: "Aiko Tanaka" });

    rerender(<CandidateList organisationId={22} />);

    expect(screen.queryByText("Aiko Tanaka")).not.toBeInTheDocument();
    expect(
      await screen.findByRole("link", { name: "South Tanaka" }),
    ).toHaveAttribute("href", "/organisations/22/candidates/20");
    await waitFor(() =>
      expect(listCandidates).toHaveBeenLastCalledWith(22, {}),
    );
  });
});
