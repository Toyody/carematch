import { apiRequest } from "@/lib/api/client";

export type AiStatus =
  "applied" | "failed" | "processing" | "queued" | "ready" | "review_ready";

export type CvField =
  "email" | "first_name" | "last_name" | "location" | "occupation" | "phone";

export interface CvExtraction {
  applied_at: string | null;
  candidate_document_id: number;
  candidate_id: number;
  created_at: string;
  draft: Record<CvField, string | null> | null;
  failure_code: string | null;
  id: number;
  prompt_version: string;
  schema_version: string;
  status: AiStatus;
}

export interface MatchExplanation {
  candidate_id: number;
  created_at: string;
  disclaimer: string;
  factors: Array<{ explanation: string; type: string }> | null;
  failure_code: string | null;
  id: number;
  job_id: number;
  stale: boolean;
  status: AiStatus;
  summary: string | null;
}

interface DataResponse<T> {
  data: T;
}

export async function requestCvExtraction(
  organisationId: number,
  candidateId: number,
  documentId: number,
  idempotencyKey: string,
): Promise<CvExtraction> {
  const response = await apiRequest<DataResponse<CvExtraction>>(
    `/organisations/${organisationId}/candidates/${candidateId}/documents/${documentId}/ai-extractions`,
    {
      body: {},
      method: "POST",
      withCsrf: true,
      headers: { "Idempotency-Key": idempotencyKey },
    },
  );
  return response.data;
}

export async function getCvExtraction(
  organisationId: number,
  candidateId: number,
  documentId: number,
  extractionId: number,
): Promise<CvExtraction> {
  const response = await apiRequest<DataResponse<CvExtraction>>(
    `/organisations/${organisationId}/candidates/${candidateId}/documents/${documentId}/ai-extractions/${extractionId}`,
  );
  return response.data;
}

export async function applyCvExtraction(
  organisationId: number,
  candidateId: number,
  documentId: number,
  extractionId: number,
  fields: Partial<Record<CvField, string | null>>,
) {
  const response = await apiRequest<
    DataResponse<import("@/features/candidate/api").Candidate>
  >(
    `/organisations/${organisationId}/candidates/${candidateId}/documents/${documentId}/ai-extractions/${extractionId}/apply`,
    { body: { fields }, method: "POST", withCsrf: true },
  );
  return response.data;
}

export async function requestMatchExplanation(
  organisationId: number,
  jobId: number,
  candidateId: number,
): Promise<MatchExplanation> {
  const response = await apiRequest<DataResponse<MatchExplanation>>(
    `/organisations/${organisationId}/jobs/${jobId}/matches/${candidateId}/ai-explanations`,
    { body: {}, method: "POST", withCsrf: true },
  );
  return response.data;
}

export async function getMatchExplanation(
  organisationId: number,
  jobId: number,
  candidateId: number,
  explanationId: number,
): Promise<MatchExplanation> {
  const response = await apiRequest<DataResponse<MatchExplanation>>(
    `/organisations/${organisationId}/jobs/${jobId}/matches/${candidateId}/ai-explanations/${explanationId}`,
  );
  return response.data;
}
