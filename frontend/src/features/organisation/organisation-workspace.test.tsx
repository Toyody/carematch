import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { useAuth } from "@/features/identity/auth-context";
import { ApiError } from "@/lib/api/client";

import { getOrganisation, listOrganisations, type Organisation } from "./api";
import { OrganisationWorkspace } from "./organisation-workspace";

vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("./api", () => ({
  getOrganisation: vi.fn(),
  listOrganisations: vi.fn(),
}));

const northside: Organisation = {
  created_at: "2026-09-20T00:00:00+00:00",
  id: 11,
  membership: { role: "admin" },
  name: "Northside Health",
};

const southside: Organisation = {
  created_at: "2026-09-20T00:00:00+00:00",
  id: 22,
  membership: { role: "hiring_manager" },
  name: "Southside Care",
};

const logout = vi.fn();

describe("OrganisationWorkspace", () => {
  beforeEach(() => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout,
      register: vi.fn(),
      user: {
        created_at: "2026-09-20T00:00:00+00:00",
        email: "person@example.test",
        id: 7,
        name: "Care Match",
      },
    });
    vi.mocked(listOrganisations).mockResolvedValue([northside, southside]);
    vi.mocked(getOrganisation).mockImplementation(async (id) =>
      id === northside.id ? northside : southside,
    );
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("loads a directly addressed authorised organisation and its role", async () => {
    render(<OrganisationWorkspace organisationId={northside.id} />);

    expect(
      screen.getByText("Loading organisation workspace…"),
    ).toBeInTheDocument();
    expect(
      await screen.findByRole("heading", { name: northside.name }),
    ).toBeInTheDocument();
    expect(screen.getAllByText(/admin/)).toHaveLength(2);
    expect(
      screen.getByRole("link", { name: "Manage candidates" }),
    ).toHaveAttribute("href", `/organisations/${northside.id}/candidates`);
    expect(screen.getByRole("link", { name: "Manage jobs" })).toHaveAttribute(
      "href",
      `/organisations/${northside.id}/jobs`,
    );
    expect(getOrganisation).toHaveBeenCalledWith(northside.id);
    expect(listOrganisations).toHaveBeenCalledTimes(1);
  });

  it("shows all available organisations and routes switching through the URL", async () => {
    render(<OrganisationWorkspace organisationId={northside.id} />);

    expect(
      await screen.findByRole("link", { name: southside.name }),
    ).toHaveAttribute("href", `/organisations/${southside.id}`);
    expect(screen.getByText("hiring_manager")).toBeInTheDocument();
  });

  it("loads the new workspace when the route parameter changes", async () => {
    const { rerender } = render(
      <OrganisationWorkspace organisationId={northside.id} />,
    );
    await screen.findByRole("heading", { name: northside.name });

    rerender(<OrganisationWorkspace organisationId={southside.id} />);

    expect(
      await screen.findByRole("heading", { name: southside.name }),
    ).toBeInTheDocument();
    expect(getOrganisation).toHaveBeenLastCalledWith(southside.id);
  });

  it("shows a safe unavailable state for an inaccessible tenant without logging out", async () => {
    vi.mocked(getOrganisation).mockRejectedValue(
      new ApiError(404, "Not found."),
    );
    render(<OrganisationWorkspace organisationId={999} />);

    expect(
      await screen.findByRole("heading", { name: "Organisation unavailable" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Back to your organisations" }),
    ).toHaveAttribute("href", "/");
    expect(logout).not.toHaveBeenCalled();
  });

  it("shows list loading and a list-specific error", async () => {
    vi.mocked(listOrganisations).mockRejectedValue(new Error("unavailable"));
    render(<OrganisationWorkspace organisationId={northside.id} />);

    expect(screen.getByText("Loading organisations…")).toBeInTheDocument();
    expect(
      await screen.findByText(
        "Unable to load your organisations. Please try again.",
      ),
    ).toBeInTheDocument();
  });

  it("preserves normal authentication navigation for a guest", () => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout,
      register: vi.fn(),
      user: null,
    });
    render(<OrganisationWorkspace organisationId={northside.id} />);

    expect(screen.getByRole("link", { name: "Login" })).toHaveAttribute(
      "href",
      "/login",
    );
    expect(getOrganisation).not.toHaveBeenCalled();
  });

  it("reports an API 401 as an expired session rather than a tenant 404", async () => {
    vi.mocked(getOrganisation).mockRejectedValue(
      new ApiError(401, "Unauthenticated."),
    );
    render(<OrganisationWorkspace organisationId={northside.id} />);

    expect(
      await screen.findByRole("heading", { name: "Session expired" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Login" })).toHaveAttribute(
      "href",
      "/login",
    );
  });
});
