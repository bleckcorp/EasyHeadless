import type { Metadata } from "next";
import { ContentPageView } from "@/app/components/content-page-view";
import { getPage, metadataFromSeo } from "@/lib/easyheadless";

export async function generateMetadata(): Promise<Metadata> {
  const page = await getPage("/");

  if (!page) {
    return {};
  }

  return metadataFromSeo(page.seo, page.title);
}

export default async function HomePage() {
  const page = await getPage("/");

  return <ContentPageView page={page} />;
}
