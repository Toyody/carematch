import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { useAuth } from "@/features/identity/auth-context";

import { acceptOrganisationInvitation } from "./api";
import { InvitationAcceptance } from "./invitation-acceptance";

vi.mock("@/features/identity/auth-context", () => ({ useAuth: vi.fn() }));
vi.mock("./api", () => ({ acceptOrganisationInvitation: vi.fn() }));

const mockedUseAuth = vi.mocked(useAuth);
const mockedAccept = vi.mocked(acceptOrganisationInvitation);

afterEach(cleanup);

describe("InvitationAcceptance", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.location.hash = "#token=raw-token";
  });

  it("keeps the invitation page available while a guest signs in", async () => {
    mockedUseAuth.mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: null,
    });

    render(<InvitationAcceptance />);

    expect(screen.getByRole("link", { name: "login" })).toHaveAttribute(
      "target",
      "_blank",
    );
    expect(
      screen.getByRole("button", { name: "I have signed in" }),
    ).toBeVisible();
    await waitFor(() => expect(window.location.hash).toBe(""));
  });

  it("accepts with the token and shows success for an authenticated user", async () => {
    mockedUseAuth.mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: {
        created_at: "2026-09-20T00:00:00+00:00",
        email: "invitee@example.test",
        id: 2,
        name: "Invitee",
      },
    });
    mockedAccept.mockResolvedValue(undefined);

    render(<InvitationAcceptance />);
    fireEvent.click(screen.getByRole("button", { name: "Accept invitation" }));

    await waitFor(() => expect(mockedAccept).toHaveBeenCalledWith("raw-token"));
    expect(await screen.findByRole("status")).toHaveTextContent(
      "Your organisation invitation has been accepted.",
    );
    expect(
      screen.getByRole("link", { name: "View your organisations" }),
    ).toHaveAttribute("href", "/");
  });

  it("shows the safe API error when acceptance fails", async () => {
    mockedUseAuth.mockReturnValue({
      error: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      user: {
        created_at: "2026-09-20T00:00:00+00:00",
        email: "wrong@example.test",
        id: 3,
        name: "Wrong user",
      },
    });
    mockedAccept.mockRejectedValue(new Error("request failed"));

    window.location.hash = "#token=invalid-token";
    render(<InvitationAcceptance />);
    fireEvent.click(screen.getByRole("button", { name: "Accept invitation" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "The request could not be completed. Please try again.",
    );
  });
});
