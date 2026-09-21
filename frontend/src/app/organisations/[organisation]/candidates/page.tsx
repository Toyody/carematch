import { CandidateList } from "@/features/candidate/candidate-list";

export default async function CandidatesPage({
  params,
}: PageProps<"/organisations/[organisation]/candidates">) {
  const { organisation } = await params;

  return <CandidateList organisationId={Number(organisation)} />;
}
