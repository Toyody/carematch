import { apiRequest } from "@/lib/api/client";

export type QualificationMatchStatus =
  "attention_required" | "not_satisfied" | "satisfied";
export type OccupationMatchStatus = "match" | "mismatch" | "unknown";

export interface CandidateMatch {
  application_status: string | null;
  candidate: {
    first_name: string;
    id: number;
    last_name: string;
    location: string | null;
    occupation: string | null;
  };
  distance_km: number | null;
  factors: Array<{
    status: string;
    type: "distance" | "occupation" | "qualification";
  }>;
  occupation_status: OccupationMatchStatus;
  qualification: {
    attention_required_count: number;
    not_satisfied_count: number;
    required_count: number;
    satisfied_count: number;
    status: QualificationMatchStatus;
  };
  rank: number;
}

export interface CandidateMatchPage {
  data: CandidateMatch[];
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

export async function listCandidateMatches(
  organisationId: number,
  jobId: number,
  query: { max_distance_km?: string; page?: string } = {},
): Promise<CandidateMatchPage> {
  const parameters = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value) parameters.set(key, value);
  }
  const suffix = parameters.size ? `?${parameters}` : "";

  return apiRequest<CandidateMatchPage>(
    `/organisations/${organisationId}/jobs/${jobId}/matches${suffix}`,
  );
}
