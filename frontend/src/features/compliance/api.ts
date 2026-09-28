import { apiRequest } from "@/lib/api/client";

export type QualificationStatus = "expired" | "expiring" | "missing" | "valid";
export type QualificationCoverageStatus =
  "attention_required" | "not_satisfied" | "satisfied";

export interface QualificationDefinition {
  category: string | null;
  created_at: string;
  description: string | null;
  id: number;
  is_active: boolean;
  name: string;
  updated_at: string;
}

export interface CandidateQualification {
  created_at: string;
  credential_number: string | null;
  expires_on: string | null;
  id: number;
  issued_on: string | null;
  issuer: string | null;
  qualification_definition_id: number;
  qualification_name: string;
  status: Exclude<QualificationStatus, "missing">;
  updated_at: string;
}

export interface JobQualificationRequirement {
  created_at: string;
  id: number;
  qualification_definition_id: number;
  qualification_name: string;
}

export interface QualificationCoverage {
  requirements: {
    candidate_qualification_id: number | null;
    expires_on: string | null;
    name: string;
    qualification_definition_id: number;
    status: QualificationStatus;
  }[];
  status: QualificationCoverageStatus;
}

export interface QualificationExpiry {
  candidate_id: number;
  candidate_name: string;
  candidate_qualification_id: number;
  expires_on: string;
  qualification_definition_id: number;
  qualification_name: string;
  status: "expired" | "expiring";
}

interface Collection<T> {
  data: T[];
}
interface Item<T> {
  data: T;
}

export async function listQualificationDefinitions(organisationId: number) {
  return (
    await apiRequest<Collection<QualificationDefinition>>(
      `/organisations/${organisationId}/qualifications`,
    )
  ).data;
}

export async function createQualificationDefinition(
  organisationId: number,
  input: { category: string | null; description: string | null; name: string },
) {
  return (
    await apiRequest<Item<QualificationDefinition>>(
      `/organisations/${organisationId}/qualifications`,
      { body: input, method: "POST", withCsrf: true },
    )
  ).data;
}

export async function updateQualificationDefinition(
  organisationId: number,
  definition: QualificationDefinition,
) {
  return (
    await apiRequest<Item<QualificationDefinition>>(
      `/organisations/${organisationId}/qualifications/${definition.id}`,
      {
        body: {
          category: definition.category,
          description: definition.description,
          is_active: definition.is_active,
          name: definition.name,
        },
        method: "PATCH",
        withCsrf: true,
      },
    )
  ).data;
}

export async function listCandidateQualifications(
  organisationId: number,
  candidateId: number,
) {
  return (
    await apiRequest<Collection<CandidateQualification>>(
      `/organisations/${organisationId}/candidates/${candidateId}/qualifications`,
    )
  ).data;
}

export type CandidateQualificationInput = {
  credential_number: string | null;
  expires_on: string | null;
  issued_on: string | null;
  issuer: string | null;
  qualification_definition_id: number;
};

export async function createCandidateQualification(
  organisationId: number,
  candidateId: number,
  input: CandidateQualificationInput,
) {
  return (
    await apiRequest<Item<CandidateQualification>>(
      `/organisations/${organisationId}/candidates/${candidateId}/qualifications`,
      { body: input, method: "POST", withCsrf: true },
    )
  ).data;
}

export async function updateCandidateQualification(
  organisationId: number,
  candidateId: number,
  credentialId: number,
  input: CandidateQualificationInput,
) {
  return (
    await apiRequest<Item<CandidateQualification>>(
      `/organisations/${organisationId}/candidates/${candidateId}/qualifications/${credentialId}`,
      { body: input, method: "PATCH", withCsrf: true },
    )
  ).data;
}

export async function deleteCandidateQualification(
  organisationId: number,
  candidateId: number,
  credentialId: number,
) {
  return apiRequest<void>(
    `/organisations/${organisationId}/candidates/${candidateId}/qualifications/${credentialId}`,
    { method: "DELETE", withCsrf: true },
  );
}

export async function listJobQualificationRequirements(
  organisationId: number,
  jobId: number,
) {
  return (
    await apiRequest<Collection<JobQualificationRequirement>>(
      `/organisations/${organisationId}/jobs/${jobId}/qualification-requirements`,
    )
  ).data;
}

export async function addJobQualificationRequirement(
  organisationId: number,
  jobId: number,
  definitionId: number,
) {
  return (
    await apiRequest<Item<JobQualificationRequirement>>(
      `/organisations/${organisationId}/jobs/${jobId}/qualification-requirements`,
      {
        body: { qualification_definition_id: definitionId },
        method: "POST",
        withCsrf: true,
      },
    )
  ).data;
}

export async function deleteJobQualificationRequirement(
  organisationId: number,
  jobId: number,
  requirementId: number,
) {
  return apiRequest<void>(
    `/organisations/${organisationId}/jobs/${jobId}/qualification-requirements/${requirementId}`,
    { method: "DELETE", withCsrf: true },
  );
}

export async function getQualificationCoverage(
  organisationId: number,
  jobId: number,
  candidateId: number,
) {
  return (
    await apiRequest<Item<QualificationCoverage>>(
      `/organisations/${organisationId}/jobs/${jobId}/candidates/${candidateId}/qualification-coverage`,
    )
  ).data;
}

export async function listQualificationExpiries(organisationId: number) {
  return (
    await apiRequest<Collection<QualificationExpiry>>(
      `/organisations/${organisationId}/qualification-expiries`,
    )
  ).data;
}
