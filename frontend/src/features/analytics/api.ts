import { apiRequest } from "@/lib/api/client";

export const APPLICATION_STATUSES = [
  "applied",
  "screening",
  "interview",
  "offer",
  "hired",
  "rejected",
] as const;

export type ApplicationStatus = (typeof APPLICATION_STATUSES)[number];

export interface Analytics {
  application_series: { applications: number; date: string }[];
  current_statuses: Record<ApplicationStatus, number>;
  funnel: {
    applied: number;
    hired: number;
    interview: number;
    offer: number;
    screening: number;
  };
  jobs: {
    applications: number;
    hired: number;
    interview: number;
    job_id: number;
    job_title: string;
    offer: number;
    rejected: number;
  }[];
  period: { from: string; timezone: "UTC"; to: string };
  summary: {
    applications: number;
    hired: number;
    new_candidates: number;
    new_jobs: number;
    rejected: number;
  };
  time_to_stage: {
    hired: TimeToStage;
    interview: TimeToStage;
  };
}

interface TimeToStage {
  median_days: number | null;
  sample_size: number;
}

interface AnalyticsResponse {
  data: Analytics;
}

export async function getAnalytics(
  organisationId: number,
  period: { from?: string; to?: string } = {},
): Promise<Analytics> {
  const query = new URLSearchParams();
  if (period.from) query.set("from", period.from);
  if (period.to) query.set("to", period.to);

  const response = await apiRequest<AnalyticsResponse>(
    `/organisations/${organisationId}/analytics${query.size ? `?${query}` : ""}`,
  );

  return response.data;
}
