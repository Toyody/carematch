import { isPublicDemo } from "@/lib/public-demo";

export function PublicDemoBanner() {
  if (!isPublicDemo) {
    return null;
  }

  return (
    <aside className="public-demo-banner" role="status">
      Portfolio demo — use synthetic data only.
    </aside>
  );
}
