import { AuditTrail } from "@/features/audit/audit-trail";

export default async function AuditTrailPage({
  params,
}: PageProps<"/organisations/[organisation]/audit-events">) {
  const { organisation } = await params;

  return <AuditTrail organisationId={Number(organisation)} />;
}
