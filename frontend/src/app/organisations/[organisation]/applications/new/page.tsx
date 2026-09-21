import { CreateApplication } from "@/features/application/create-application";

export default async function NewApplicationPage({
  params,
}: PageProps<"/organisations/[organisation]/applications/new">) {
  const { organisation } = await params;
  return <CreateApplication organisationId={Number(organisation)} />;
}
