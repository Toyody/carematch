import { ApplicationList } from "@/features/application/application-list";

export default async function ApplicationsPage({
  params,
}: PageProps<"/organisations/[organisation]/applications">) {
  const { organisation } = await params;
  return <ApplicationList organisationId={Number(organisation)} />;
}
