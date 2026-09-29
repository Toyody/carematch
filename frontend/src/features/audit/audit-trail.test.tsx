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

import { listAuditEvents } from "./api";
import { AuditTrail } from "./audit-trail";

vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("@/features/organisation/api", () => ({ getOrganisation: vi.fn() }));
vi.mock("./api", () => ({ listAuditEvents: vi.fn() }));

const page = {
  data: [
    {
      actor: { email: "admin@example.test", id: 2, name: "Demo Admin" },
      event_type: "candidate.updated",
      id: 10,
      metadata: { changed_fields: ["occupation"] },
      occurred_at: "2026-09-28T10:00:00+00:00",
      subject: { id: 42, type: "candidate" },
    },
  ],
  links: { first: "", last: "", next: "/next", prev: null },
  meta: { current_page: 1, last_page: 2, per_page: 20, total: 21 },
};

describe("AuditTrail", () => {
  beforeEach(() => {
    vi.mocked(useAuth).mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: {
        created_at: "2026-01-01",
        email: "admin@example.test",
        id: 2,
        name: "Demo Admin",
      },
    });
    vi.mocked(getOrganisation).mockResolvedValue({
      created_at: "2026-01-01",
      id: 7,
      membership: { role: "admin" },
      name: "Demo",
    });
    vi.mocked(listAuditEvents).mockResolvedValue(page);
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("shows loading then renders actor, human event label, subject and safe metadata", async () => {
    render(<AuditTrail organisationId={7} />);
    expect(screen.getByText("Loading audit events…")).toBeInTheDocument();
    expect(await screen.findByText("Candidate updated")).toBeInTheDocument();
    expect(
      screen.getByText(/Demo Admin \(admin@example.test\)/),
    ).toBeInTheDocument();
    expect(screen.getByText(/candidate #42/)).toBeInTheDocument();
    expect(screen.getByText(/changed fields: occupation/)).toBeInTheDocument();
  });

  it("supports filters and pagination", async () => {
    render(<AuditTrail organisationId={7} />);
    await screen.findByText("Candidate updated");
    fireEvent.change(screen.getByLabelText("Event type"), {
      target: { value: "job.opened" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await waitFor(() =>
      expect(listAuditEvents).toHaveBeenLastCalledWith(
        7,
        expect.objectContaining({ event_type: "job.opened" }),
      ),
    );
    fireEvent.click(screen.getByRole("button", { name: "Next" }));
    await waitFor(() =>
      expect(listAuditEvents).toHaveBeenLastCalledWith(
        7,
        expect.objectContaining({ page: 2 }),
      ),
    );
  });

  it("shows an empty state and an API error", async () => {
    vi.mocked(listAuditEvents).mockResolvedValueOnce({
      ...page,
      data: [],
      meta: { ...page.meta, total: 0, last_page: 1 },
    });
    const { rerender } = render(<AuditTrail organisationId={7} />);
    expect(await screen.findByText("No audit events yet.")).toBeInTheDocument();

    vi.mocked(listAuditEvents).mockRejectedValueOnce(new Error("unavailable"));
    rerender(<AuditTrail organisationId={8} />);
    expect(await screen.findByRole("alert")).toBeInTheDocument();
  });

  it.each(["recruiter", "hiring_manager"] as const)(
    "hides the list for %s",
    async (role) => {
      vi.mocked(getOrganisation).mockResolvedValue({
        created_at: "2026-01-01",
        id: 7,
        membership: { role },
        name: "Demo",
      });
      render(<AuditTrail organisationId={7} />);
      expect(
        await screen.findByText(
          "Only Organisation Admins can view the audit trail.",
        ),
      ).toBeInTheDocument();
      expect(listAuditEvents).not.toHaveBeenCalled();
    },
  );
});
