import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useAuth } from "@/features/identity/auth-context";
import { getOrganisation } from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";
import { getApplication } from "./api";
import { ApplicationDetail } from "./application-detail";

vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({ getApplication: vi.fn() }));

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
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "",
      id: 4,
      membership: { role: "hiring_manager" },
      name: "North",
    });
    vi.mocked(getApplication).mockResolvedValue(application);
  });
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("loads a direct URL read-only representation for every active role", async () => {
    render(<ApplicationDetail applicationId={9} organisationId={4} />);
    expect(
      await screen.findByRole("heading", { name: "Ada Lovelace" }),
    ).toBeVisible();
    expect(screen.getByText("Nurse")).toBeVisible();
    expect(screen.getByText("applied")).toBeVisible();
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
  });

  it("clears stale details and fails safely for a new tenant", async () => {
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
      await screen.findByRole("heading", { name: "Application unavailable" }),
    ).toBeVisible();
  });
});
