import Link from "next/link";

export type LandingSection = {
  type?: string;
  heading?: string;
  copy?: string;
  cta_label?: string;
  cta_url?: string;
};

export function LandingSections({ sections }: { sections: LandingSection[] }) {
  return (
    <>
      {sections.map((section, index) => (
        <section className="section" key={`${section.type || "section"}-${index}`}>
          {section.heading ? <h2>{section.heading}</h2> : null}
          {section.copy ? <p>{section.copy}</p> : null}
          {section.cta_label && section.cta_url ? (
            <Link className="button" href={section.cta_url}>
              {section.cta_label}
            </Link>
          ) : null}
        </section>
      ))}
    </>
  );
}
