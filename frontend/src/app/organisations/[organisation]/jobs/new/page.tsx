import { CreateJob } from "@/features/job/create-job";

export default async function NewJobPage({
  params,
}: PageProps<"/organisations/[organisation]/jobs/new">) {
  const { organisation } = await params;
  return <CreateJob organisationId={Number(organisation)} />;
}
