"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { useAuth } from "@/features/identity/auth-context";
import { ApiError } from "@/lib/api/client";

import { getOrganisation, listOrganisations, type Organisation } from "./api";
import { OrganisationSelector } from "./organisation-selector";

export function OrganisationWorkspace({
  organisationId,
}: {
  organisationId: number;
}) {
  const { isLoading: isAuthLoading, user } = useAuth();
  const [workspaceResult, setWorkspaceResult] = useState<{
    error: unknown;
    key: string;
    organisation: Organisation | null;
  } | null>(null);
  const [listResult, setListResult] = useState<{
    error: boolean;
    organisations: Organisation[];
    userId: number;
  } | null>(null);
  const userId = user?.id;
  const workspaceKey =
    userId === undefined ? null : `${userId}:${organisationId}`;

  useEffect(() => {
    if (isAuthLoading || workspaceKey === null) {
      return;
    }

    let active = true;

    void getOrganisation(organisationId)
      .then((selectedOrganisation) => {
        if (active) {
          setWorkspaceResult({
            error: null,
            key: workspaceKey,
            organisation: selectedOrganisation,
          });
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setWorkspaceResult({
            error,
            key: workspaceKey,
            organisation: null,
          });
        }
      });

    return () => {
      active = false;
    };
  }, [isAuthLoading, organisationId, workspaceKey]);

  useEffect(() => {
    if (isAuthLoading || userId === undefined) {
      return;
    }

    let active = true;

    void listOrganisations()
      .then((availableOrganisations) => {
        if (active) {
          setListResult({
            error: false,
            organisations: availableOrganisations,
            userId,
          });
        }
      })
      .catch(() => {
        if (active) {
          setListResult({ error: true, organisations: [], userId });
        }
      });

    return () => {
      active = false;
    };
  }, [isAuthLoading, userId]);

  if (isAuthLoading) {
    return <WorkspaceCard status="Checking your session…" />;
  }

  if (user === null) {
    return (
      <main>
        <section className="foundation-card workspace-card">
          <h1>Organisation workspace</h1>
          <p>You must be signed in to access an organisation workspace.</p>
          <nav aria-label="Authentication">
            <Link href="/login">Login</Link>
            <Link href="/register">Register</Link>
          </nav>
        </section>
      </main>
    );
  }

  const currentWorkspaceResult =
    workspaceResult?.key === workspaceKey ? workspaceResult : null;
  const currentListResult = listResult?.userId === user.id ? listResult : null;
  const isWorkspaceLoading = currentWorkspaceResult === null;
  const organisation = currentWorkspaceResult?.organisation ?? null;
  const workspaceError = currentWorkspaceResult?.error ?? null;
  const isListLoading = currentListResult === null;
  const organisations = currentListResult?.organisations ?? [];
  const listError = currentListResult?.error ?? false;
  const status = errorStatus(workspaceError);

  return (
    <main>
      <section className="foundation-card workspace-card">
        <p className="eyebrow">Organisation workspace</p>
        {isWorkspaceLoading ? (
          <p role="status">Loading organisation workspace…</p>
        ) : null}
        {status === 404 ? (
          <div role="alert">
            <h1>Organisation unavailable</h1>
            <p>
              This organisation is unavailable or you no longer have access to
              it.
            </p>
          </div>
        ) : null}
        {status === 401 ? (
          <div role="alert">
            <h1>Session expired</h1>
            <p>Your session is no longer valid. Please log in again.</p>
            <Link href="/login">Login</Link>
          </div>
        ) : null}
        {workspaceError !== null && status !== 401 && status !== 404 ? (
          <div role="alert">
            <h1>Unable to load workspace</h1>
            <p>Please try again.</p>
          </div>
        ) : null}
        {organisation ? (
          <header>
            <h1>{organisation.name}</h1>
            <p>
              Your role: <strong>{organisation.membership.role}</strong>
            </p>
            <Link href={`/organisations/${organisationId}/candidates`}>
              Manage candidates
            </Link>
          </header>
        ) : null}

        <section aria-labelledby="workspace-switcher-title">
          <h2 id="workspace-switcher-title">Switch organisation</h2>
          {isListLoading ? <p role="status">Loading organisations…</p> : null}
          {listError ? (
            <p role="alert">
              Unable to load your organisations. Please try again.
            </p>
          ) : null}
          {!isListLoading && !listError ? (
            <OrganisationSelector
              currentOrganisationId={organisationId}
              organisations={organisations}
            />
          ) : null}
        </section>

        <Link href="/">Back to your organisations</Link>
      </section>
    </main>
  );
}

function WorkspaceCard({ status }: { status: string }) {
  return (
    <main>
      <section className="foundation-card workspace-card">
        <h1>Organisation workspace</h1>
        <p role="status">{status}</p>
      </section>
    </main>
  );
}

function errorStatus(error: unknown): number | null {
  return error instanceof ApiError ? error.status : null;
}
