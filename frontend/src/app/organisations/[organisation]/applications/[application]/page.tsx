import { ApplicationDetail } from "@/features/application/application-detail";

export default async function ApplicationPage({
  params,
}: PageProps<"/organisations/[organisation]/applications/[application]">) {
  const { application, organisation } = await params;
  return (
    <ApplicationDetail
      applicationId={Number(application)}
      organisationId={Number(organisation)}
    />
  );
}
