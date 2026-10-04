"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import { useAuth } from "@/features/identity/auth-context";
import { getJob, type Job } from "@/features/job/api";
import { ApiError } from "@/lib/api/client";
import { listCandidateMatches, type CandidateMatchPage } from "./api";

export function CandidateMatches({
  jobId,
  organisationId,
}: {
  jobId: number;
  organisationId: number;
}) {
  const { isLoading, user } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryString = searchParams.toString();
  const key = user
    ? `${user.id}:${organisationId}:${jobId}:${queryString}`
    : null;
  const [result, setResult] = useState<{
    error: unknown;
    job: Job | null;
    key: string;
    page: CandidateMatchPage | null;
  } | null>(null);

  useEffect(() => {
    if (isLoading || key === null) return;
    let active = true;
    const query = new URLSearchParams(queryString);
    void Promise.all([
      getJob(organisationId, jobId),
      listCandidateMatches(organisationId, jobId, {
        max_distance_km: query.get("max_distance_km") ?? undefined,
        page: query.get("page") ?? undefined,
      }),
    ])
      .then(([job, page]) => {
        if (active) setResult({ error: null, job, key, page });
      })
      .catch((error: unknown) => {
        if (active) setResult({ error, job: null, key, page: null });
      });
    return () => {
      active = false;
    };
  }, [isLoading, jobId, key, organisationId, queryString]);

  function filter(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const value = String(
      new FormData(event.currentTarget).get("max_distance_km") ?? "",
    ).trim();
    const parameters = new URLSearchParams();
    if (value) parameters.set("max_distance_km", value);
    router.push(
      `/organisations/${organisationId}/jobs/${jobId}/matches${parameters.size ? `?${parameters}` : ""}`,
    );
  }

  if (isLoading || (user && result?.key !== key))
    return (
      <Shell title="Candidate matches">
        <p role="status">Loading candidate matches…</p>
      </Shell>
    );
  if (!user)
    return (
      <Shell title="Candidate matches">
        <p role="alert">You must be signed in to view matches.</p>
        <Link href="/login">Login</Link>
      </Shell>
    );
  if (result?.error) {
    const error = result.error;
    const message =
      error instanceof ApiError && error.status === 422
        ? (error.validationErrors.max_distance_km?.[0] ?? error.message)
        : error instanceof ApiError && error.status === 404
          ? "This job is unavailable or you no longer have access."
          : "Candidate matches could not be loaded.";
    return (
      <Shell title="Candidate matches">
        <p role="alert">{message}</p>
        <Link href={`/organisations/${organisationId}/jobs/${jobId}`}>
          Back to job
        </Link>
      </Shell>
    );
  }
  if (!result?.job || !result.page)
    return (
      <Shell title="Candidate matches">
        <p role="alert">Candidate matches could not be loaded.</p>
      </Shell>
    );

  const { job, page } = result;
  return (
    <Shell eyebrow={job.title} title="Candidate matches">
      <p>
        Decision support based on recorded qualifications, exact occupation
        compatibility and optional distance. Human review remains required.
      </p>
      <form onSubmit={filter}>
        <label htmlFor="match-radius">Maximum distance (km)</label>
        <input
          defaultValue={searchParams.get("max_distance_km") ?? ""}
          id="match-radius"
          max="1000"
          min="0.1"
          name="max_distance_km"
          step="0.1"
          type="number"
        />
        <button type="submit">Apply radius</button>
      </form>
      {page.data.length === 0 ? (
        <p>No candidates match the current radius.</p>
      ) : (
        <ol className="job-list" start={page.meta.from ?? 1}>
          {page.data.map((match) => (
            <li key={match.candidate.id}>
              <strong>
                Rank {match.rank}: {match.candidate.first_name}{" "}
                {match.candidate.last_name}
              </strong>
              <span>
                Qualifications: {label(match.qualification.status)} (
                {match.qualification.satisfied_count}/
                {match.qualification.required_count} satisfied)
              </span>
              <span>Occupation: {label(match.occupation_status)}</span>
              <span>
                Distance:{" "}
                {match.distance_km === null
                  ? "unavailable"
                  : `${match.distance_km.toFixed(1)} km`}
              </span>
              <span>
                Application: {match.application_status ?? "not applied"}
              </span>
              <Link
                href={`/organisations/${organisationId}/candidates/${match.candidate.id}`}
              >
                View candidate
              </Link>
            </li>
          ))}
        </ol>
      )}
      <nav aria-label="Match pages" className="pagination">
        {page.meta.current_page > 1 ? (
          <Link
            href={pageHref(
              organisationId,
              jobId,
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
              jobId,
              searchParams,
              page.meta.current_page + 1,
            )}
          >
            Next
          </Link>
        ) : null}
      </nav>
      <Link href={`/organisations/${organisationId}/jobs/${jobId}`}>
        Back to job
      </Link>
    </Shell>
  );
}

function label(value: string) {
  return value.replaceAll("_", " ");
}

function pageHref(
  organisationId: number,
  jobId: number,
  current: URLSearchParams,
  page: number,
) {
  const next = new URLSearchParams(current);
  next.set("page", String(page));
  return `/organisations/${organisationId}/jobs/${jobId}/matches?${next}`;
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
