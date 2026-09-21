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
  listApplications,
  type ApplicationListQuery,
  type ApplicationPage,
} from "./api";

export function ApplicationList({
  organisationId,
}: {
  organisationId: number;
}) {
  const { isLoading, user } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryString = searchParams.toString();
  const key = user ? `${user.id}:${organisationId}:${queryString}` : null;
  const [result, setResult] = useState<{
    error: unknown;
    key: string;
    organisation: Organisation | null;
    page: ApplicationPage | null;
  } | null>(null);

  useEffect(() => {
    if (isLoading || key === null) return;
    let active = true;
    void Promise.all([
      getOrganisation(organisationId),
      listApplications(
        organisationId,
        queryFrom(new URLSearchParams(queryString)),
      ),
    ]).then(
      ([organisation, page]) =>
        active && setResult({ error: null, key, organisation, page }),
      (error: unknown) =>
        active && setResult({ error, key, organisation: null, page: null }),
    );
    return () => {
      active = false;
    };
  }, [isLoading, key, organisationId, queryString]);

  function filters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const parameters = new URLSearchParams();
    for (const name of [
      "job_id",
      "candidate_id",
      "status",
      "sort",
      "direction",
    ]) {
      const value = String(data.get(name) ?? "").trim();
      if (value) parameters.set(name, value);
    }
    router.push(
      `/organisations/${organisationId}/applications${parameters.size ? `?${parameters}` : ""}`,
    );
  }

  if (isLoading || (user && result?.key !== key))
    return (
      <Shell title="Applications">
        <p role="status">Loading applications…</p>
      </Shell>
    );
  if (!user)
    return (
      <Shell title="Applications">
        <p>You must be signed in to view applications.</p>
        <Link href="/login">Login</Link>
      </Shell>
    );
  if (result?.error || !result?.organisation || !result.page)
    return <LoadError error={result?.error} />;
  const { organisation, page } = result;
  const mayCreate = ["admin", "recruiter"].includes(
    organisation.membership.role,
  );

  return (
    <Shell eyebrow={organisation.name} title="Applications">
      <header className="candidate-heading">
        <p>{page.meta.total} applications</p>
        {mayCreate ? (
          <Link href={`/organisations/${organisationId}/applications/new`}>
            Add application
          </Link>
        ) : null}
      </header>
      <form className="application-filters" onSubmit={filters}>
        <label htmlFor="application-job">Job ID</label>
        <input
          defaultValue={searchParams.get("job_id") ?? ""}
          id="application-job"
          inputMode="numeric"
          name="job_id"
        />
        <label htmlFor="application-candidate">Candidate ID</label>
        <input
          defaultValue={searchParams.get("candidate_id") ?? ""}
          id="application-candidate"
          inputMode="numeric"
          name="candidate_id"
        />
        <label htmlFor="application-status">Status</label>
        <select
          defaultValue={searchParams.get("status") ?? ""}
          id="application-status"
          name="status"
        >
          <option value="">All</option>
          {[
            "applied",
            "screening",
            "interview",
            "offer",
            "hired",
            "rejected",
          ].map((status) => (
            <option key={status}>{status}</option>
          ))}
        </select>
        <label htmlFor="application-sort">Sort by</label>
        <select
          defaultValue={searchParams.get("sort") ?? "applied_at"}
          id="application-sort"
          name="sort"
        >
          <option value="applied_at">Applied time</option>
          <option value="updated_at">Updated time</option>
        </select>
        <label htmlFor="application-direction">Direction</label>
        <select
          defaultValue={searchParams.get("direction") ?? "desc"}
          id="application-direction"
          name="direction"
        >
          <option value="asc">Ascending</option>
          <option value="desc">Descending</option>
        </select>
        <button type="submit">Apply</button>
      </form>
      {page.data.length === 0 ? (
        <p>No applications match the current filters.</p>
      ) : (
        <ul className="application-list">
          {page.data.map((application) => (
            <li key={application.id}>
              <Link
                href={`/organisations/${organisationId}/applications/${application.id}`}
              >
                {application.candidate.first_name}{" "}
                {application.candidate.last_name} — {application.job.title}
              </Link>
              <span>{application.status}</span>
              <span>{new Date(application.applied_at).toLocaleString()}</span>
            </li>
          ))}
        </ul>
      )}
      <nav aria-label="Application pages" className="pagination">
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
    </Shell>
  );
}

function Shell({
  children,
  eyebrow,
  title,
}: {
  children: React.ReactNode;
  eyebrow?: string;
  title: string;
}) {
  return (
    <main>
      <section className="foundation-card application-card">
        {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
        <h1>{title}</h1>
        {children}
      </section>
    </main>
  );
}
function LoadError({ error }: { error: unknown }) {
  const unauthorized = error instanceof ApiError && error.status === 401;
  return (
    <Shell
      title={unauthorized ? "Session expired" : "Applications unavailable"}
    >
      <p role="alert">
        {unauthorized
          ? "Your session is no longer valid. Please log in again."
          : "Applications are unavailable or you no longer have access."}
      </p>
      <Link href={unauthorized ? "/login" : "/"}>
        {unauthorized ? "Login" : "Back to your organisations"}
      </Link>
    </Shell>
  );
}
function queryFrom(parameters: URLSearchParams): ApplicationListQuery {
  return Object.fromEntries(
    [
      "job_id",
      "candidate_id",
      "status",
      "sort",
      "direction",
      "page",
      "per_page",
    ]
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
  return `/organisations/${organisationId}/applications?${parameters}`;
}
