import { apiRequest } from "@/lib/api/client";

export type ApplicationStatus =
  "applied" | "screening" | "interview" | "offer" | "hired" | "rejected";

export interface RecruitmentApplication {
  applied_at: string;
  candidate: { first_name: string; id: number; last_name: string };
  created_at: string;
  created_by_user_id: number;
  id: number;
  job: { id: number; title: string };
  status: ApplicationStatus;
  updated_at: string;
}

export interface ApplicationListQuery {
  candidate_id?: string;
  direction?: string;
  job_id?: string;
  page?: string;
  per_page?: string;
  sort?: string;
  status?: string;
}

export interface ApplicationPage {
  data: RecruitmentApplication[];
  links: {
    first: string;
    last: string;
    next: string | null;
    prev: string | null;
  };
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
  };
}

interface ApplicationResponse {
  data: RecruitmentApplication;
}

export async function listApplications(
  organisationId: number,
  query: ApplicationListQuery = {},
): Promise<ApplicationPage> {
  const parameters = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value) parameters.set(key, value);
  }
  const suffix = parameters.size ? `?${parameters}` : "";
  return apiRequest<ApplicationPage>(
    `/organisations/${organisationId}/applications${suffix}`,
  );
}

export async function getApplication(
  organisationId: number,
  applicationId: number,
): Promise<RecruitmentApplication> {
  const response = await apiRequest<ApplicationResponse>(
    `/organisations/${organisationId}/applications/${applicationId}`,
  );
  return response.data;
}

export async function createApplication(
  organisationId: number,
  input: { candidate_id: number; job_id: number },
): Promise<RecruitmentApplication> {
  const response = await apiRequest<ApplicationResponse>(
    `/organisations/${organisationId}/applications`,
    { body: input, method: "POST", withCsrf: true },
  );
  return response.data;
}
