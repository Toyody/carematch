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

export interface DashboardActivity {
  application_id: number;
  candidate: {
    first_name: string;
    id: number;
    last_name: string;
  };
  changed_at: string;
  from_status: ApplicationStatus | null;
  job: {
    id: number;
    title: string;
  };
  to_status: ApplicationStatus;
}

export interface Dashboard {
  application_counts: Record<ApplicationStatus, number>;
  candidate_count: number;
  open_job_count: number;
  recent_application_activity: DashboardActivity[];
}

interface DashboardResponse {
  data: Dashboard;
}

export async function getDashboard(organisationId: number): Promise<Dashboard> {
  const response = await apiRequest<DashboardResponse>(
    `/organisations/${organisationId}/dashboard`,
  );

  return response.data;
}
