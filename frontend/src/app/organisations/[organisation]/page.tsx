import { OrganisationWorkspace } from "@/features/organisation/organisation-workspace";

export default async function OrganisationPage({
  params,
}: PageProps<"/organisations/[organisation]">) {
  const { organisation } = await params;
  const organisationId = Number(organisation);

  return <OrganisationWorkspace organisationId={organisationId} />;
}
