import { apiRequest } from "@/lib/api/client";

export interface Candidate {
  availability: string | null;
  created_at: string;
  email: string | null;
  first_name: string;
  id: number;
  last_name: string;
  location: string | null;
  notes: string | null;
  occupation: string | null;
  phone: string | null;
  updated_at: string;
}

export interface CandidateInput {
  availability: string | null;
  email: string | null;
  first_name: string;
  last_name: string;
  location: string | null;
  notes: string | null;
  occupation: string | null;
  phone: string | null;
}

export interface CandidateListQuery {
  direction?: string;
  occupation?: string;
  page?: string;
  per_page?: string;
  search?: string;
  sort?: string;
}

export interface CandidatePage {
  data: Candidate[];
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

interface CandidateResponse {
  data: Candidate;
}

export async function listCandidates(
  organisationId: number,
  query: CandidateListQuery = {},
): Promise<CandidatePage> {
  const parameters = new URLSearchParams();

  for (const [key, value] of Object.entries(query)) {
    if (value) {
      parameters.set(key, value);
    }
  }

  const queryString = parameters.toString();

  return apiRequest<CandidatePage>(
    `/organisations/${organisationId}/candidates${queryString ? `?${queryString}` : ""}`,
  );
}

export async function getCandidate(
  organisationId: number,
  candidateId: number,
): Promise<Candidate> {
  const response = await apiRequest<CandidateResponse>(
    `/organisations/${organisationId}/candidates/${candidateId}`,
  );

  return response.data;
}

export async function createCandidate(
  organisationId: number,
  input: CandidateInput,
): Promise<Candidate> {
  const response = await apiRequest<CandidateResponse>(
    `/organisations/${organisationId}/candidates`,
    { body: input, method: "POST", withCsrf: true },
  );

  return response.data;
}

export async function updateCandidate(
  organisationId: number,
  candidateId: number,
  input: Partial<CandidateInput>,
): Promise<Candidate> {
  const response = await apiRequest<CandidateResponse>(
    `/organisations/${organisationId}/candidates/${candidateId}`,
    { body: input, method: "PATCH", withCsrf: true },
  );

  return response.data;
}
