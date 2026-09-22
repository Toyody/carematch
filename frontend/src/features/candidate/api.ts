import { apiDownload, apiRequest, apiRequestForm } from "@/lib/api/client";

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

export interface CandidateDocument {
  created_at: string;
  id: number;
  mime_type: string;
  original_name: string;
  size_bytes: number;
  updated_at: string;
  uploaded_by_user_id: number;
}

interface CandidateDocumentResponse {
  data: CandidateDocument;
}

interface CandidateDocumentListResponse {
  data: CandidateDocument[];
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

export async function listCandidateDocuments(
  organisationId: number,
  candidateId: number,
): Promise<CandidateDocument[]> {
  const response = await apiRequest<CandidateDocumentListResponse>(
    `/organisations/${organisationId}/candidates/${candidateId}/documents`,
  );

  return response.data;
}

export async function uploadCandidateDocument(
  organisationId: number,
  candidateId: number,
  file: File,
): Promise<CandidateDocument> {
  const form = new FormData();
  form.set("document", file);
  const response = await apiRequestForm<CandidateDocumentResponse>(
    `/organisations/${organisationId}/candidates/${candidateId}/documents`,
    form,
  );

  return response.data;
}

export async function downloadCandidateDocument(
  organisationId: number,
  candidateId: number,
  documentId: number,
): Promise<Blob> {
  return apiDownload(
    `/organisations/${organisationId}/candidates/${candidateId}/documents/${documentId}/download`,
  );
}

export async function deleteCandidateDocument(
  organisationId: number,
  candidateId: number,
  documentId: number,
): Promise<void> {
  return apiRequest<void>(
    `/organisations/${organisationId}/candidates/${candidateId}/documents/${documentId}`,
    { method: "DELETE", withCsrf: true },
  );
}
