import { apiRequest } from "@/lib/api/client";

export type OrganisationRole = "admin" | "recruiter" | "hiring_manager";

export interface Organisation {
  created_at: string;
  id: number;
  membership: {
    role: OrganisationRole;
  };
  name: string;
}

interface OrganisationResponse {
  data: Organisation;
}

interface OrganisationListResponse {
  data: Organisation[];
}

export interface CreateOrganisationInput {
  name: string;
}

export async function createOrganisation(
  input: CreateOrganisationInput,
): Promise<Organisation> {
  const response = await apiRequest<OrganisationResponse>("/organisations", {
    body: input,
    method: "POST",
    withCsrf: true,
  });

  return response.data;
}

export async function listOrganisations(): Promise<Organisation[]> {
  const response = await apiRequest<OrganisationListResponse>("/organisations");

  return response.data;
}

export async function getOrganisation(id: number): Promise<Organisation> {
  const response = await apiRequest<OrganisationResponse>(
    `/organisations/${id}`,
  );

  return response.data;
}

export function acceptOrganisationInvitation(token: string): Promise<void> {
  return apiRequest<void>("/organisation-invitations/accept", {
    body: { token },
    method: "POST",
    withCsrf: true,
  });
}
