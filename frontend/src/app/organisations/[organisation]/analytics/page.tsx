import { OrganisationAnalytics } from "@/features/analytics/organisation-analytics";

export default async function AnalyticsPage({
  params,
}: PageProps<"/organisations/[organisation]/analytics">) {
  const { organisation } = await params;

  return <OrganisationAnalytics organisationId={Number(organisation)} />;
}
