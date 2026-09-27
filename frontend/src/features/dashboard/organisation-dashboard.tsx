"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { ApiError } from "@/lib/api/client";

import {
  APPLICATION_STATUSES,
  getDashboard,
  type Dashboard,
  type DashboardActivity,
} from "./api";

interface DashboardResult {
  dashboard: Dashboard | null;
  error: unknown;
  key: string;
}

export function OrganisationDashboard({
  organisationId,
  userId,
}: {
  organisationId: number;
  userId: number;
}) {
  const key = `${userId}:${organisationId}`;
  const [result, setResult] = useState<DashboardResult | null>(null);

  useEffect(() => {
    let active = true;

    void getDashboard(organisationId).then(
      (dashboard) => {
        if (active) setResult({ dashboard, error: null, key });
      },
      (error: unknown) => {
        if (active) setResult({ dashboard: null, error, key });
      },
    );

    return () => {
      active = false;
    };
  }, [key, organisationId]);

  const currentResult = result?.key === key ? result : null;

  if (currentResult === null) {
    return <DashboardSection status="Loading dashboard…" />;
  }

  if (currentResult.error !== null) {
    return <DashboardError error={currentResult.error} />;
  }

  if (currentResult.dashboard === null) {
    return <DashboardError error={new Error("Missing dashboard data.")} />;
  }

  const dashboard = currentResult.dashboard;

  return (
    <section aria-labelledby="dashboard-heading" className="dashboard">
      <h2 id="dashboard-heading">Dashboard</h2>
      <div className="dashboard-metrics">
        <Metric label="Candidates" value={dashboard.candidate_count} />
        <Metric label="Open jobs" value={dashboard.open_job_count} />
      </div>

      <section aria-labelledby="application-counts-heading">
        <h3 id="application-counts-heading">Applications by status</h3>
        <dl className="dashboard-statuses">
          {APPLICATION_STATUSES.map((status) => (
            <div key={status}>
              <dt>{formatStatus(status)}</dt>
              <dd>{dashboard.application_counts[status]}</dd>
            </div>
          ))}
        </dl>
      </section>

      <section aria-labelledby="recent-activity-heading">
        <h3 id="recent-activity-heading">Recent application activity</h3>
        {dashboard.recent_application_activity.length === 0 ? (
          <p>No recent application activity.</p>
        ) : (
          <ol className="dashboard-activity">
            {dashboard.recent_application_activity.map((activity, index) => (
              <ActivityItem
                activity={activity}
                key={`${activity.application_id}:${activity.changed_at}:${index}`}
                organisationId={organisationId}
              />
            ))}
          </ol>
        )}
      </section>
    </section>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <dl className="dashboard-metric">
      <div>
        <dt>{label}</dt>
        <dd>{value}</dd>
      </div>
    </dl>
  );
}

function ActivityItem({
  activity,
  organisationId,
}: {
  activity: DashboardActivity;
  organisationId: number;
}) {
  const transition = activity.from_status
    ? `${formatStatus(activity.from_status)} to ${formatStatus(activity.to_status)}`
    : formatStatus(activity.to_status);

  return (
    <li>
      <Link
        href={`/organisations/${organisationId}/applications/${activity.application_id}`}
      >
        {activity.candidate.first_name} {activity.candidate.last_name}
      </Link>
      <span>{activity.job.title}</span>
      <span>{transition}</span>
      <time dateTime={activity.changed_at}>
        {new Date(activity.changed_at).toLocaleString()}
      </time>
    </li>
  );
}

function DashboardSection({ status }: { status: string }) {
  return (
    <section aria-labelledby="dashboard-heading" className="dashboard">
      <h2 id="dashboard-heading">Dashboard</h2>
      <p role="status">{status}</p>
    </section>
  );
}

function DashboardError({ error }: { error: unknown }) {
  const status = error instanceof ApiError ? error.status : null;
  const message =
    status === 401
      ? "Your session is no longer valid. Please log in again."
      : status === 404
        ? "This organisation is unavailable or you no longer have access to it."
        : "The dashboard could not be loaded. Please try again.";

  return (
    <section aria-labelledby="dashboard-heading" className="dashboard">
      <h2 id="dashboard-heading">Dashboard</h2>
      <p role="alert">{message}</p>
      {status === 401 ? <Link href="/login">Login</Link> : null}
    </section>
  );
}

function formatStatus(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1).replaceAll("_", " ");
}
