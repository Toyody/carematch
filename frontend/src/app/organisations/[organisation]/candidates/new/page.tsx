import { CreateCandidate } from "@/features/candidate/create-candidate";

export default async function NewCandidatePage({
  params,
}: PageProps<"/organisations/[organisation]/candidates/new">) {
  const { organisation } = await params;

  return <CreateCandidate organisationId={Number(organisation)} />;
}
