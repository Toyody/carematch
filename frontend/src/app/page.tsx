"use client";

import Link from "next/link";
import { useState } from "react";

import { ApiHealth } from "@/components/system/api-health";
import { useAuth } from "@/features/identity/auth-context";
import { OrganisationPanel } from "@/features/organisation/organisation-panel";
import { isPublicDemo } from "@/lib/public-demo";

export default function Home() {
  const { error, isLoading, logout, user } = useAuth();
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [logoutError, setLogoutError] = useState<string | null>(null);

  async function handleLogout() {
    setIsLoggingOut(true);
    setLogoutError(null);

    try {
      await logout();
    } catch {
      setLogoutError("Unable to log out. Please try again.");
    } finally {
      setIsLoggingOut(false);
    }
  }

  return (
    <main>
      <section aria-labelledby="page-title" className="foundation-card">
        <p className="eyebrow">Portfolio workspace</p>
        <h1 id="page-title">CareMatch</h1>
        <p>
          Tenant-safe healthcare recruitment workflows in one focused workspace.
        </p>

        {isLoading ? <p role="status">Checking your session…</p> : null}
        {!isLoading && user ? (
          <div className="auth-summary">
            <p>{user.name}</p>
            <p>{user.email}</p>
            <button
              disabled={isLoggingOut}
              onClick={handleLogout}
              type="button"
            >
              {isLoggingOut ? "Logging out…" : "Logout"}
            </button>
          </div>
        ) : null}
        {!isLoading && !user ? (
          <nav aria-label="Authentication">
            <Link href="/login">Login</Link>
            {!isPublicDemo ? <Link href="/register">Register</Link> : null}
          </nav>
        ) : null}
        {error ? <p role="alert">{error}</p> : null}
        {logoutError ? <p role="alert">{logoutError}</p> : null}

        {!isLoading && user ? <OrganisationPanel /> : null}

        <ApiHealth />
      </section>
    </main>
  );
}
