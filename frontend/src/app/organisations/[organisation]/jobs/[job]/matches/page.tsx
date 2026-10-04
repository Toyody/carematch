import { CandidateMatches } from "@/features/matching/candidate-matches";

export default async function CandidateMatchesPage({
  params,
}: PageProps<"/organisations/[organisation]/jobs/[job]/matches">) {
  const { job, organisation } = await params;

  return (
    <CandidateMatches
      jobId={Number(job)}
      organisationId={Number(organisation)}
    />
  );
}
