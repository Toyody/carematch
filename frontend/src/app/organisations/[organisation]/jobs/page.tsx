import { JobList } from "@/features/job/job-list";

export default async function JobsPage({
  params,
}: PageProps<"/organisations/[organisation]/jobs">) {
  const { organisation } = await params;
  return <JobList organisationId={Number(organisation)} />;
}
