import { apiRequest } from "@/lib/api/client";

export interface AuditEvent {
  actor: { email: string; id: number; name: string };
  event_type: string;
  id: number;
  metadata: Record<string, boolean | number | string | string[] | null>;
  occurred_at: string;
  subject: { id: number; type: string };
}

export interface AuditEventPage {
  data: AuditEvent[];
  links: {
    first: string;
    last: string;
    next: string | null;
    prev: string | null;
  };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface AuditFilters {
  actor_user_id?: number;
  event_type?: string;
  occurred_from?: string;
  occurred_to?: string;
  page?: number;
  subject_id?: number;
  subject_type?: string;
}

export function listAuditEvents(
  organisationId: number,
  filters: AuditFilters = {},
): Promise<AuditEventPage> {
  const query = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== "") query.set(key, String(value));
  });
  const suffix = query.size > 0 ? `?${query.toString()}` : "";

  return apiRequest<AuditEventPage>(
    `/organisations/${organisationId}/audit-events${suffix}`,
  );
}
