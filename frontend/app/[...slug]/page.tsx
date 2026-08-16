import type { Metadata } from "next";
import { ContentPageView } from "@/app/components/content-page-view";
import { getPage, getRoutes, metadataFromSeo } from "@/lib/easyheadless";

type PageProps = {
  params: Promise<{
    slug: string[];
  }>;
};

export async function generateStaticParams() {
  const routes = await getRoutes();

  return routes
    .filter((route) => route.type === "page" && route.path !== "/")
    .map((route) => ({
      slug: route.path.replace(/^\//, "").split("/"),
    }));
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const path = `/${slug.join("/")}`;
  const page = await getPage(path);

  if (!page) {
    return {};
  }

  return metadataFromSeo(page.seo, page.title);
}

export default async function ContentPage({ params }: PageProps) {
  const { slug } = await params;
  const path = `/${slug.join("/")}`;
  const page = await getPage(path);

  return <ContentPageView page={page} />;
}
