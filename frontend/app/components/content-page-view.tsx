import { notFound } from "next/navigation";
import type { EasyHeadlessPost } from "@/lib/easyheadless";
import { LandingSections } from "@/app/components/landing-sections";

export function ContentPageView({ page }: { page: EasyHeadlessPost | null }) {
  if (!page) {
    notFound();
  }

  const sections = Array.isArray(page.acf.sections) ? page.acf.sections : [];

  return (
    <article className="content-page">
      <section className="hero">
        <div>
          <h1>{page.title}</h1>
          {page.excerpt ? <p>{page.excerpt}</p> : null}
        </div>
        {page.featuredImage?.url ? (
          <div className="hero__image">
            <img src={page.featuredImage.url} alt={page.featuredImage.alt || ""} />
          </div>
        ) : null}
      </section>

      {sections.length ? <LandingSections sections={sections} /> : null}

      {page.content ? (
        <section className="section">
          <div className="content-body" dangerouslySetInnerHTML={{ __html: page.content }} />
        </section>
      ) : null}
    </article>
  );
}
