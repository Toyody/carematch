import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

import Home from "@/app/page";
import { ApiError } from "@/lib/api/client";

import { getCurrentUser, logout, type User } from "./api";
import { AuthProvider } from "./auth-context";

vi.mock("./api", () => ({
  getCurrentUser: vi.fn(),
  login: vi.fn(),
  logout: vi.fn(),
  register: vi.fn(),
}));

const user: User = {
  created_at: "2026-08-30T00:00:00Z",
  email: "person@example.com",
  id: 7,
  name: "Care Match",
};

function renderHome() {
  vi.stubGlobal(
    "fetch",
    vi.fn().mockRejectedValue(new Error("health unavailable")),
  );
  return render(
    <AuthProvider>
      <Home />
    </AuthProvider>,
  );
}

describe("authentication state", () => {
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
    vi.unstubAllGlobals();
  });

  it("shows session discovery loading", () => {
    vi.mocked(getCurrentUser).mockReturnValue(new Promise(() => undefined));
    renderHome();

    expect(screen.getByText("Checking your session…")).toBeInTheDocument();
  });

  it("shows the authenticated user", async () => {
    vi.mocked(getCurrentUser).mockResolvedValue(user);
    renderHome();

    expect(await screen.findByText(user.name)).toBeInTheDocument();
    expect(screen.getByText(user.email)).toBeInTheDocument();
    expect(
      screen.queryByRole("link", { name: "Login" }),
    ).not.toBeInTheDocument();
  });

  it("shows login and registration for an unauthenticated visitor", async () => {
    vi.mocked(getCurrentUser).mockRejectedValue(
      new ApiError(401, "Unauthenticated."),
    );
    renderHome();

    expect(await screen.findByRole("link", { name: "Login" })).toHaveAttribute(
      "href",
      "/login",
    );
    expect(screen.getByRole("link", { name: "Register" })).toHaveAttribute(
      "href",
      "/register",
    );
  });

  it("clears the authenticated UI after logout", async () => {
    vi.mocked(getCurrentUser).mockResolvedValue(user);
    vi.mocked(logout).mockResolvedValue(undefined);
    renderHome();

    fireEvent.click(await screen.findByRole("button", { name: "Logout" }));

    expect(
      await screen.findByRole("link", { name: "Login" }),
    ).toBeInTheDocument();
    expect(screen.queryByText(user.email)).not.toBeInTheDocument();
  });
});
