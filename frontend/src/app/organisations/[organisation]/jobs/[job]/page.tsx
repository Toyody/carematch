import { JobDetail } from "@/features/job/job-detail";

export default async function JobPage({
  params,
}: PageProps<"/organisations/[organisation]/jobs/[job]">) {
  const { job, organisation } = await params;
  return (
    <JobDetail jobId={Number(job)} organisationId={Number(organisation)} />
  );
}
