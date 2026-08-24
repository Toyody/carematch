import { ApiHealth } from "@/components/system/api-health";

export default function Home() {
  return (
    <main>
      <section className="foundation-card" aria-labelledby="page-title">
        <p className="eyebrow">Phase 1 foundation</p>
        <h1 id="page-title">CareMatch</h1>
        <p>
          The local development environment is ready for the identity and
          multi-tenancy phase.
        </p>
        <ApiHealth />
      </section>
    </main>
  );
}
