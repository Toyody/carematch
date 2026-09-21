import { apiRequest } from "@/lib/api/client";

export type JobStatus = "archived" | "closed" | "draft" | "open";
export type JobTransition = "archive" | "close" | "open";

export interface Job {
  closes_at: string | null;
  created_at: string;
  description: string | null;
  employment_type: string | null;
  id: number;
  location: string | null;
  occupation: string | null;
  opened_at: string | null;
  status: JobStatus;
  title: string;
  updated_at: string;
}

export interface JobInput {
  closes_at: string | null;
  description: string | null;
  employment_type: string | null;
  location: string | null;
  occupation: string | null;
  opened_at: string | null;
  title: string;
}

export interface JobListQuery {
  direction?: string;
  employment_type?: string;
  occupation?: string;
  page?: string;
  per_page?: string;
  search?: string;
  sort?: string;
  status?: string;
}

export interface JobPage {
  data: Job[];
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

interface JobResponse {
  data: Job;
}

export async function listJobs(
  organisationId: number,
  query: JobListQuery = {},
): Promise<JobPage> {
  const parameters = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value) parameters.set(key, value);
  }
  const queryString = parameters.toString();
  return apiRequest<JobPage>(
    `/organisations/${organisationId}/jobs${queryString ? `?${queryString}` : ""}`,
  );
}

export async function getJob(
  organisationId: number,
  jobId: number,
): Promise<Job> {
  const response = await apiRequest<JobResponse>(
    `/organisations/${organisationId}/jobs/${jobId}`,
  );
  return response.data;
}

export async function createJob(
  organisationId: number,
  input: JobInput,
): Promise<Job> {
  const response = await apiRequest<JobResponse>(
    `/organisations/${organisationId}/jobs`,
    {
      body: input,
      method: "POST",
      withCsrf: true,
    },
  );
  return response.data;
}

export async function updateJob(
  organisationId: number,
  jobId: number,
  input: Partial<JobInput>,
): Promise<Job> {
  const response = await apiRequest<JobResponse>(
    `/organisations/${organisationId}/jobs/${jobId}`,
    {
      body: input,
      method: "PATCH",
      withCsrf: true,
    },
  );
  return response.data;
}

export async function transitionJob(
  organisationId: number,
  jobId: number,
  transition: JobTransition,
): Promise<Job> {
  const response = await apiRequest<JobResponse>(
    `/organisations/${organisationId}/jobs/${jobId}/${transition}`,
    {
      method: "POST",
      withCsrf: true,
    },
  );
  return response.data;
}
