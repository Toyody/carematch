import { CandidateDetail } from "@/features/candidate/candidate-detail";

export default async function CandidatePage({
  params,
}: PageProps<"/organisations/[organisation]/candidates/[candidate]">) {
  const { candidate, organisation } = await params;

  return (
    <CandidateDetail
      candidateId={Number(candidate)}
      organisationId={Number(organisation)}
    />
  );
}
