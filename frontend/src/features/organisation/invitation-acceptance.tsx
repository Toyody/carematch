"use client";

import Link from "next/link";
import { useState, useSyncExternalStore } from "react";

import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";

import { acceptOrganisationInvitation } from "./api";

let invitationToken: string | null = null;

function captureTokenFromHash() {
  const tokenFromLocation = tokenFromHash();

  if (tokenFromLocation !== "" || invitationToken === null) {
    invitationToken = tokenFromLocation;
  }

  if (tokenFromLocation) {
    window.history.replaceState(
      null,
      "",
      `${window.location.pathname}${window.location.search}`,
    );
  }
}

function subscribeToHashChanges(callback: () => void) {
  const handleHashChange = () => {
    captureTokenFromHash();
    callback();
  };

  handleHashChange();
  window.addEventListener("hashchange", handleHashChange);

  return () => {
    window.removeEventListener("hashchange", handleHashChange);
    invitationToken = null;
  };
}

function tokenFromHash(): string {
  const fragment = new URLSearchParams(window.location.hash.slice(1));

  return fragment.get("token") ?? "";
}

function tokenSnapshot(): string | null {
  return invitationToken;
}

export function InvitationAcceptance() {
  const { isLoading, user } = useAuth();
  const token = useSyncExternalStore(
    subscribeToHashChanges,
    tokenSnapshot,
    () => null,
  );
  const [error, setError] = useState<unknown>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [accepted, setAccepted] = useState(false);

  async function acceptInvitation() {
    if (!token) {
      return;
    }

    setError(null);
    setIsSubmitting(true);

    try {
      await acceptOrganisationInvitation(token);
      setAccepted(true);
    } catch (caughtError) {
      setError(caughtError);
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading || token === null) {
    return (
      <section className="foundation-card auth-card">
        <h1>Organisation invitation</h1>
        <p role="status">Checking your session…</p>
      </section>
    );
  }

  if (token === "") {
    return (
      <section className="foundation-card auth-card">
        <h1>Organisation invitation</h1>
        <p role="alert">This invitation link is invalid or incomplete.</p>
      </section>
    );
  }

  if (user === null) {
    return (
      <section className="foundation-card auth-card">
        <h1>Organisation invitation</h1>
        <p>
          Log in or register with the email address that received this
          invitation.
        </p>
        <p>
          Open{" "}
          <Link href="/login" target="_blank">
            login
          </Link>{" "}
          or{" "}
          <Link href="/register" target="_blank">
            registration
          </Link>{" "}
          in a new tab, then return here.
        </p>
        <button onClick={() => window.location.reload()} type="button">
          I have signed in
        </button>
      </section>
    );
  }

  return (
    <section className="foundation-card auth-card">
      <h1>Organisation invitation</h1>
      {accepted ? (
        <div role="status">
          <p>Your organisation invitation has been accepted.</p>
          <Link href="/">View your organisations</Link>
        </div>
      ) : (
        <>
          <p>Accept this invitation as {user.email}.</p>
          <FormError error={error} />
          <button
            disabled={isSubmitting}
            onClick={acceptInvitation}
            type="button"
          >
            {isSubmitting ? "Accepting…" : "Accept invitation"}
          </button>
        </>
      )}
    </section>
  );
}
