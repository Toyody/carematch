"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";

import { useAuth } from "@/features/identity/auth-context";
import {
  getOrganisation,
  type Organisation,
} from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";

import {
  listCandidates,
  type CandidateListQuery,
  type CandidatePage,
} from "./api";

interface CandidateListResult {
  error: unknown;
  key: string;
  organisation: Organisation | null;
  page: CandidatePage | null;
}

export function CandidateList({ organisationId }: { organisationId: number }) {
  const { isLoading: isAuthLoading, user } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryString = searchParams.toString();
  const userId = user?.id;
  const resultKey =
    userId === undefined ? null : `${userId}:${organisationId}:${queryString}`;
  const [result, setResult] = useState<CandidateListResult | null>(null);

  useEffect(() => {
    if (isAuthLoading || resultKey === null) {
      return;
    }

    let active = true;
    const query = queryFrom(new URLSearchParams(queryString));

    void Promise.all([
      getOrganisation(organisationId),
      listCandidates(organisationId, query),
    ])
      .then(([organisation, page]) => {
        if (active) {
          setResult({ error: null, key: resultKey, organisation, page });
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setResult({
            error,
            key: resultKey,
            organisation: null,
            page: null,
          });
        }
      });

    return () => {
      active = false;
    };
  }, [isAuthLoading, organisationId, queryString, resultKey]);

  if (isAuthLoading) {
    return <CandidateStatus message="Checking your session…" />;
  }

  if (user === null) {
    return <CandidateLoginRequired />;
  }

  const currentResult = result?.key === resultKey ? result : null;

  if (currentResult === null) {
    return <CandidateStatus message="Loading candidates…" />;
  }

  if (currentResult.error !== null) {
    return <CandidateError error={currentResult.error} />;
  }

  const organisation = currentResult.organisation;
  const page = currentResult.page;

  if (organisation === null || page === null) {
    return <CandidateError error={new Error("Missing candidate list data.")} />;
  }

  const mayWrite = ["admin", "recruiter"].includes(
    organisation.membership.role,
  );

  function handleFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const parameters = new URLSearchParams();

    for (const name of ["search", "occupation", "sort", "direction"]) {
      const value = String(form.get(name) ?? "").trim();

      if (value) {
        parameters.set(name, value);
      }
    }

    router.push(
      `/organisations/${organisationId}/candidates${parameters.size ? `?${parameters}` : ""}`,
    );
  }

  return (
    <main>
      <section className="foundation-card candidate-card">
        <p className="eyebrow">{organisation.name}</p>
        <header className="candidate-heading">
          <div>
            <h1>Candidates</h1>
            <p>{page.meta.total} candidates</p>
          </div>
          {mayWrite ? (
            <Link href={`/organisations/${organisationId}/candidates/new`}>
              Add candidate
            </Link>
          ) : null}
        </header>

        <form className="candidate-filters" onSubmit={handleFilters}>
          <label htmlFor="candidate-search">Search</label>
          <input
            defaultValue={searchParams.get("search") ?? ""}
            id="candidate-search"
            maxLength={255}
            name="search"
            placeholder="Name or email"
          />
          <label htmlFor="candidate-filter-occupation">Occupation</label>
          <input
            defaultValue={searchParams.get("occupation") ?? ""}
            id="candidate-filter-occupation"
            maxLength={255}
            name="occupation"
          />
          <label htmlFor="candidate-sort">Sort by</label>
          <select
            defaultValue={searchParams.get("sort") ?? "created_at"}
            id="candidate-sort"
            name="sort"
          >
            <option value="created_at">Creation date</option>
            <option value="name">Name</option>
          </select>
          <label htmlFor="candidate-direction">Direction</label>
          <select
            defaultValue={searchParams.get("direction") ?? "desc"}
            id="candidate-direction"
            name="direction"
          >
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
          <button type="submit">Apply</button>
        </form>

        {page.data.length === 0 ? (
          <p>No candidates match the current filters.</p>
        ) : (
          <ul className="candidate-list">
            {page.data.map((candidate) => (
              <li key={candidate.id}>
                <Link
                  href={`/organisations/${organisationId}/candidates/${candidate.id}`}
                >
                  {candidate.first_name} {candidate.last_name}
                </Link>
                <span>{candidate.email ?? "No email"}</span>
                <span>{candidate.occupation ?? "No occupation"}</span>
              </li>
            ))}
          </ul>
        )}

        <nav aria-label="Candidate pages" className="pagination">
          {page.meta.current_page > 1 ? (
            <Link
              href={pageHref(
                organisationId,
                searchParams,
                page.meta.current_page - 1,
              )}
            >
              Previous
            </Link>
          ) : null}
          <span>
            Page {page.meta.current_page} of {page.meta.last_page}
          </span>
          {page.meta.current_page < page.meta.last_page ? (
            <Link
              href={pageHref(
                organisationId,
                searchParams,
                page.meta.current_page + 1,
              )}
            >
              Next
            </Link>
          ) : null}
        </nav>

        <Link href={`/organisations/${organisationId}`}>
          Back to organisation workspace
        </Link>
      </section>
    </main>
  );
}

function queryFrom(parameters: URLSearchParams): CandidateListQuery {
  return Object.fromEntries(
    ["search", "occupation", "sort", "direction", "page", "per_page"]
      .map((key) => [key, parameters.get(key)])
      .filter((entry): entry is [string, string] => entry[1] !== null),
  );
}

function pageHref(
  organisationId: number,
  current: URLSearchParams,
  page: number,
) {
  const parameters = new URLSearchParams(current.toString());
  parameters.set("page", String(page));

  return `/organisations/${organisationId}/candidates?${parameters}`;
}

function CandidateStatus({ message }: { message: string }) {
  return (
    <main>
      <section className="foundation-card candidate-card">
        <h1>Candidates</h1>
        <p role="status">{message}</p>
      </section>
    </main>
  );
}

function CandidateLoginRequired() {
  return (
    <main>
      <section className="foundation-card candidate-card">
        <h1>Candidates</h1>
        <p>You must be signed in to view candidates.</p>
        <Link href="/login">Login</Link>
      </section>
    </main>
  );
}

function CandidateError({ error }: { error: unknown }) {
  const status = error instanceof ApiError ? error.status : null;

  return (
    <main>
      <section className="foundation-card candidate-card">
        <h1>{status === 401 ? "Session expired" : "Candidates unavailable"}</h1>
        <p role="alert">
          {status === 401
            ? "Your session is no longer valid. Please log in again."
            : "Candidates are unavailable or you no longer have access."}
        </p>
        <Link href={status === 401 ? "/login" : "/"}>
          {status === 401 ? "Login" : "Back to your organisations"}
        </Link>
      </section>
    </main>
  );
}
