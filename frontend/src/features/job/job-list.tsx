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
import { listJobs, type JobListQuery, type JobPage } from "./api";

export function JobList({ organisationId }: { organisationId: number }) {
  const { isLoading, user } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryString = searchParams.toString();
  const key = user ? `${user.id}:${organisationId}:${queryString}` : null;
  const [result, setResult] = useState<{
    error: unknown;
    key: string;
    organisation: Organisation | null;
    page: JobPage | null;
  } | null>(null);

  useEffect(() => {
    if (isLoading || key === null) return;
    let active = true;
    void Promise.all([
      getOrganisation(organisationId),
      listJobs(organisationId, queryFrom(new URLSearchParams(queryString))),
    ])
      .then(([organisation, page]) => {
        if (active) setResult({ error: null, key, organisation, page });
      })
      .catch((error: unknown) => {
        if (active) setResult({ error, key, organisation: null, page: null });
      });
    return () => {
      active = false;
    };
  }, [isLoading, key, organisationId, queryString]);

  function filters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const parameters = new URLSearchParams();
    for (const name of [
      "search",
      "status",
      "occupation",
      "employment_type",
      "sort",
      "direction",
    ]) {
      const value = String(data.get(name) ?? "").trim();
      if (value) parameters.set(name, value);
    }
    router.push(
      `/organisations/${organisationId}/jobs${parameters.size ? `?${parameters}` : ""}`,
    );
  }

  if (isLoading || (user && result?.key !== key))
    return (
      <Shell title="Jobs">
        <p role="status">Loading jobs…</p>
      </Shell>
    );
  if (!user)
    return (
      <Shell title="Jobs">
        <p>You must be signed in to view jobs.</p>
        <Link href="/login">Login</Link>
      </Shell>
    );
  if (result?.error) return <ListError error={result.error} />;
  if (!result?.organisation || !result.page)
    return <ListError error={new Error("Missing job data")} />;
  const { organisation, page } = result;
  const mayWrite = ["admin", "recruiter"].includes(
    organisation.membership.role,
  );

  return (
    <Shell title="Jobs" eyebrow={organisation.name}>
      <header className="candidate-heading">
        <p>{page.meta.total} jobs</p>
        {mayWrite ? (
          <Link href={`/organisations/${organisationId}/jobs/new`}>
            Add job
          </Link>
        ) : null}
      </header>
      <form className="job-filters" onSubmit={filters}>
        <label htmlFor="job-search">Search title</label>
        <input
          defaultValue={searchParams.get("search") ?? ""}
          id="job-search"
          name="search"
        />
        <label htmlFor="job-status">Status</label>
        <select
          defaultValue={searchParams.get("status") ?? ""}
          id="job-status"
          name="status"
        >
          <option value="">All</option>
          {["draft", "open", "closed", "archived"].map((value) => (
            <option key={value}>{value}</option>
          ))}
        </select>
        <label htmlFor="job-occupation-filter">Occupation</label>
        <input
          defaultValue={searchParams.get("occupation") ?? ""}
          id="job-occupation-filter"
          name="occupation"
        />
        <label htmlFor="job-employment-filter">Employment type</label>
        <input
          defaultValue={searchParams.get("employment_type") ?? ""}
          id="job-employment-filter"
          name="employment_type"
        />
        <label htmlFor="job-sort">Sort by</label>
        <select
          defaultValue={searchParams.get("sort") ?? "created_at"}
          id="job-sort"
          name="sort"
        >
          <option value="created_at">Creation date</option>
          <option value="opened_at">Opening date</option>
        </select>
        <label htmlFor="job-direction">Direction</label>
        <select
          defaultValue={searchParams.get("direction") ?? "desc"}
          id="job-direction"
          name="direction"
        >
          <option value="asc">Ascending</option>
          <option value="desc">Descending</option>
        </select>
        <button type="submit">Apply</button>
      </form>
      {page.data.length === 0 ? (
        <p>No jobs match the current filters.</p>
      ) : (
        <ul className="job-list">
          {page.data.map((job) => (
            <li key={job.id}>
              <Link href={`/organisations/${organisationId}/jobs/${job.id}`}>
                {job.title}
              </Link>
              <span>{job.status}</span>
              <span>{job.occupation ?? "No occupation"}</span>
              <span>{job.employment_type ?? "No employment type"}</span>
              <span>
                {job.opened_at
                  ? new Date(job.opened_at).toLocaleDateString()
                  : "No opening date"}
              </span>
            </li>
          ))}
        </ul>
      )}
      <nav aria-label="Job pages" className="pagination">
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
      <section className="foundation-card job-card">
        {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
        <h1>{title}</h1>
        {children}
      </section>
    </main>
  );
}
function ListError({ error }: { error: unknown }) {
  const unauthorized = error instanceof ApiError && error.status === 401;
  return (
    <Shell title={unauthorized ? "Session expired" : "Jobs unavailable"}>
      <p role="alert">
        {unauthorized
          ? "Your session is no longer valid. Please log in again."
          : "Jobs are unavailable or you no longer have access."}
      </p>
      <Link href={unauthorized ? "/login" : "/"}>
        {unauthorized ? "Login" : "Back to your organisations"}
      </Link>
    </Shell>
  );
}
function queryFrom(parameters: URLSearchParams): JobListQuery {
  return Object.fromEntries(
    [
      "search",
      "status",
      "occupation",
      "employment_type",
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
  return `/organisations/${organisationId}/jobs?${parameters}`;
}
