import { ComplianceWorkspace } from "@/features/compliance/compliance-workspace";

export default async function QualificationsPage({
  params,
}: PageProps<"/organisations/[organisation]/qualifications">) {
  const { organisation } = await params;
  return <ComplianceWorkspace organisationId={Number(organisation)} />;
}
