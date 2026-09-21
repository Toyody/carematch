import Link from "next/link";

import type { Organisation } from "./api";

interface OrganisationSelectorProps {
  currentOrganisationId?: number;
  organisations: Organisation[];
}

export function OrganisationSelector({
  currentOrganisationId,
  organisations,
}: OrganisationSelectorProps) {
  if (organisations.length === 0) {
    return <p>You do not belong to an organisation yet.</p>;
  }

  return (
    <nav aria-label="Organisation workspaces">
      <ul className="organisation-list">
        {organisations.map((organisation) => {
          const isCurrent = organisation.id === currentOrganisationId;

          return (
            <li data-current={isCurrent || undefined} key={organisation.id}>
              <Link
                aria-current={isCurrent ? "page" : undefined}
                href={`/organisations/${organisation.id}`}
              >
                {organisation.name}
              </Link>
              <span>
                {organisation.membership.role}
                {isCurrent ? " · Current" : null}
              </span>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
