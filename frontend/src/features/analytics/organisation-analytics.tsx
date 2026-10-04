"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";

import { useAuth } from "@/features/identity/auth-context";
import { ApiError } from "@/lib/api/client";

import { APPLICATION_STATUSES, getAnalytics, type Analytics } from "./api";

interface AnalyticsResult {
  analytics: Analytics | null;
  error: unknown;
  key: string;
}

export function OrganisationAnalytics({
  organisationId,
}: {
  organisationId: number;
}) {
  const { isLoading: authLoading, user } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryString = searchParams.toString();
  const key = user ? `${user.id}:${organisationId}:${queryString}` : null;
  const [result, setResult] = useState<AnalyticsResult | null>(null);

  useEffect(() => {
    if (authLoading || key === null) return;
    let active = true;
    const query = new URLSearchParams(queryString);

    void getAnalytics(organisationId, {
      from: query.get("from") ?? undefined,
      to: query.get("to") ?? undefined,
    }).then(
      (analytics) => {
        if (active) setResult({ analytics, error: null, key });
      },
      (error: unknown) => {
        if (active) setResult({ analytics: null, error, key });
      },
    );

    return () => {
      active = false;
    };
  }, [authLoading, key, organisationId, queryString]);

  function applyPeriod(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const from = String(form.get("from") ?? "").trim();
    const to = String(form.get("to") ?? "").trim();
    const query = new URLSearchParams();
    if (from) query.set("from", from);
    if (to) query.set("to", to);
    router.push(
      `/organisations/${organisationId}/analytics${query.size ? `?${query}` : ""}`,
    );
  }

  const current = result?.key === key ? result : null;
  if (authLoading || (user && current === null)) {
    return <Shell status="Loading analytics…" />;
  }
  if (!user) {
    return (
      <Shell>
        <p role="alert">You must be signed in to view analytics.</p>
        <Link href="/login">Login</Link>
      </Shell>
    );
  }
  if (current?.error) {
    return (
      <AnalyticsError error={current.error} organisationId={organisationId} />
    );
  }
  if (!current?.analytics) {
    return (
      <AnalyticsError
        error={new Error("Missing analytics data")}
        organisationId={organisationId}
      />
    );
  }

  const analytics = current.analytics;
  return (
    <Shell>
      <p className="eyebrow">Organisation reporting</p>
      <h1>Recruitment analytics</h1>
      <p>
        Operational metrics for Applications submitted in the selected UTC
        period. Later recorded stage progress is included for that cohort.
      </p>

      <form className="analytics-period" onSubmit={applyPeriod}>
        <label htmlFor="analytics-from">From</label>
        <input
          defaultValue={analytics.period.from}
          id="analytics-from"
          name="from"
          type="date"
        />
        <label htmlFor="analytics-to">To</label>
        <input
          defaultValue={analytics.period.to}
          id="analytics-to"
          name="to"
          type="date"
        />
        <button type="submit">Apply period</button>
      </form>
      <p>
        Showing {analytics.period.from} to {analytics.period.to} (
        {analytics.period.timezone}).
      </p>

      <section aria-labelledby="analytics-summary-heading">
        <h2 id="analytics-summary-heading">Period summary</h2>
        <dl className="analytics-metrics">
          <Metric
            label="New candidates"
            value={analytics.summary.new_candidates}
          />
          <Metric label="New jobs" value={analytics.summary.new_jobs} />
          <Metric label="Applications" value={analytics.summary.applications} />
          <Metric label="Hired outcome" value={analytics.summary.hired} />
          <Metric label="Rejected outcome" value={analytics.summary.rejected} />
        </dl>
      </section>

      <section aria-labelledby="analytics-funnel-heading">
        <h2 id="analytics-funnel-heading">Reached-stage funnel</h2>
        <p>
          Applications submitted in this period that eventually reached each
          stage.
        </p>
        <ol className="analytics-funnel">
          {(
            ["applied", "screening", "interview", "offer", "hired"] as const
          ).map((stage) => (
            <li key={stage}>
              <span>{label(stage)}</span>
              <strong>{analytics.funnel[stage]}</strong>
            </li>
          ))}
        </ol>
        <p>Rejected is a separate outcome: {analytics.summary.rejected}</p>
      </section>

      <section aria-labelledby="analytics-current-heading">
        <h2 id="analytics-current-heading">Current cohort status</h2>
        <dl className="analytics-statuses">
          {APPLICATION_STATUSES.map((status) => (
            <div key={status}>
              <dt>{label(status)}</dt>
              <dd>{analytics.current_statuses[status]}</dd>
            </div>
          ))}
        </dl>
      </section>

      <section aria-labelledby="analytics-time-heading">
        <h2 id="analytics-time-heading">Median time to stage</h2>
        <div className="analytics-metrics">
          <Duration
            label="Interview"
            metric={analytics.time_to_stage.interview}
          />
          <Duration label="Hired" metric={analytics.time_to_stage.hired} />
        </div>
      </section>

      <section aria-labelledby="analytics-trend-heading">
        <h2 id="analytics-trend-heading">Daily Applications</h2>
        <div className="analytics-table-scroll">
          <table>
            <thead>
              <tr>
                <th scope="col">UTC date</th>
                <th scope="col">Applications</th>
              </tr>
            </thead>
            <tbody>
              {analytics.application_series.map((point) => (
                <tr key={point.date}>
                  <th scope="row">{point.date}</th>
                  <td>{point.applications}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section aria-labelledby="analytics-jobs-heading">
        <h2 id="analytics-jobs-heading">Jobs in this Application cohort</h2>
        {analytics.jobs.length === 0 ? (
          <p>No Applications were submitted in this period.</p>
        ) : (
          <div className="analytics-table-scroll">
            <table>
              <thead>
                <tr>
                  <th scope="col">Job</th>
                  <th scope="col">Applications</th>
                  <th scope="col">Interview</th>
                  <th scope="col">Offer</th>
                  <th scope="col">Hired</th>
                  <th scope="col">Rejected</th>
                </tr>
              </thead>
              <tbody>
                {analytics.jobs.map((job) => (
                  <tr key={job.job_id}>
                    <th scope="row">
                      <Link
                        href={`/organisations/${organisationId}/jobs/${job.job_id}`}
                      >
                        {job.job_title}
                      </Link>
                    </th>
                    <td>{job.applications}</td>
                    <td>{job.interview}</td>
                    <td>{job.offer}</td>
                    <td>{job.hired}</td>
                    <td>{job.rejected}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <Link href={`/organisations/${organisationId}`}>
        Back to organisation
      </Link>
    </Shell>
  );
}

function Metric({ label: text, value }: { label: string; value: number }) {
  return (
    <div>
      <dt>{text}</dt>
      <dd>{value}</dd>
    </div>
  );
}

function Duration({
  label: text,
  metric,
}: {
  label: string;
  metric: { median_days: number | null; sample_size: number };
}) {
  return (
    <div>
      <h3>{text}</h3>
      <p>
        {metric.median_days === null
          ? "No qualifying Applications"
          : `${metric.median_days} days`}
      </p>
      <small>Sample size: {metric.sample_size}</small>
    </div>
  );
}

function AnalyticsError({
  error,
  organisationId,
}: {
  error: unknown;
  organisationId: number;
}) {
  const status = error instanceof ApiError ? error.status : null;
  const validation =
    error instanceof ApiError
      ? Object.values(error.validationErrors).flat()[0]
      : undefined;
  const message =
    status === 401
      ? "Your session is no longer valid. Please log in again."
      : status === 404
        ? "This organisation is unavailable or you no longer have access to it."
        : status === 422
          ? (validation ?? "The reporting period is invalid.")
          : "Analytics could not be loaded. Please try again.";

  return (
    <Shell>
      <h1>Recruitment analytics</h1>
      <p role="alert">{message}</p>
      {status === 401 ? (
        <Link href="/login">Login</Link>
      ) : (
        <Link href={`/organisations/${organisationId}`}>
          Back to organisation
        </Link>
      )}
    </Shell>
  );
}

function label(value: string) {
  return value.charAt(0).toUpperCase() + value.slice(1).replaceAll("_", " ");
}

function Shell({
  children,
  status,
}: {
  children?: React.ReactNode;
  status?: string;
}) {
  return (
    <main>
      <section
        aria-label="Recruitment analytics"
        className="foundation-card analytics-card"
      >
        {status ? (
          <>
            <h1>Recruitment analytics</h1>
            <p role="status">{status}</p>
          </>
        ) : (
          children
        )}
      </section>
    </main>
  );
}
