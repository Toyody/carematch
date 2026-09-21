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
import { listApplications, type ApplicationPage } from "./api";
import { ApplicationList } from "./application-list";

const push = vi.fn();
let query = "";
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  useSearchParams: () => new URLSearchParams(query),
}));
vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({ listApplications: vi.fn() }));

const page: ApplicationPage = {
  data: [
    {
      applied_at: "2026-09-23T00:00:00+00:00",
      candidate: { first_name: "Ada", id: 7, last_name: "Lovelace" },
      created_at: "2026-09-23T00:00:00+00:00",
      created_by_user_id: 2,
      id: 9,
      job: { id: 8, title: "Nurse" },
      status: "applied",
      updated_at: "2026-09-23T00:00:00+00:00",
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
};

describe("ApplicationList", () => {
  beforeEach(() => {
    query = "";
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
    vi.mocked(listApplications).mockResolvedValue(page);
  });
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("lists summaries, exposes creation by role, and writes filters to the URL", async () => {
    render(<ApplicationList organisationId={4} />);
    expect(await screen.findByText("Ada Lovelace — Nurse")).toBeVisible();
    expect(screen.getByRole("link", { name: "Add application" })).toBeVisible();
    fireEvent.change(screen.getByLabelText("Job ID"), {
      target: { value: "8" },
    });
    fireEvent.change(screen.getByLabelText("Status"), {
      target: { value: "applied" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Apply" }));
    expect(push).toHaveBeenCalledWith(
      "/organisations/4/applications?job_id=8&status=applied&sort=applied_at&direction=desc",
    );
  });

  it("supports empty state and clears stale tenant data", async () => {
    vi.mocked(listApplications).mockImplementation(async (organisationId) =>
      organisationId === 4
        ? page
        : {
            ...page,
            data: [],
            meta: { ...page.meta, from: null, to: null, total: 0 },
          },
    );
    const { rerender } = render(<ApplicationList organisationId={4} />);
    await screen.findByText("Ada Lovelace — Nurse");
    rerender(<ApplicationList organisationId={5} />);
    expect(screen.queryByText("Ada Lovelace — Nurse")).not.toBeInTheDocument();
    expect(
      await screen.findByText("No applications match the current filters."),
    ).toBeVisible();
  });

  it("preserves filters when navigating between pages", async () => {
    query = "status=screening&sort=updated_at&direction=asc&page=1";
    vi.mocked(listApplications).mockResolvedValue({
      ...page,
      meta: { ...page.meta, last_page: 2 },
    });

    render(<ApplicationList organisationId={4} />);

    expect(await screen.findByRole("link", { name: "Next" })).toHaveAttribute(
      "href",
      "/organisations/4/applications?status=screening&sort=updated_at&direction=asc&page=2",
    );
    expect(listApplications).toHaveBeenCalledWith(4, {
      direction: "asc",
      page: "1",
      sort: "updated_at",
      status: "screening",
    });
  });

  it("uses a safe unavailable state", async () => {
    vi.mocked(listApplications).mockRejectedValue(
      new ApiError(404, "Not Found"),
    );
    render(<ApplicationList organisationId={4} />);
    expect(
      await screen.findByRole("heading", { name: "Applications unavailable" }),
    ).toBeVisible();
    await waitFor(() => expect(listApplications).toHaveBeenCalled());
  });
});
