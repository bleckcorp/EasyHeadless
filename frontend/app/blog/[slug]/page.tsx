import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getPost, getRoutes, metadataFromSeo } from "@/lib/easyheadless";

type PostProps = {
  params: Promise<{
    slug: string;
  }>;
};

export async function generateStaticParams() {
  const routes = await getRoutes();

  return routes
    .filter((route) => route.type === "post")
    .map((route) => ({
      slug: route.slug,
    }));
}

export async function generateMetadata({ params }: PostProps): Promise<Metadata> {
  const { slug } = await params;
  const post = await getPost(slug);

  if (!post) {
    return {};
  }

  return metadataFromSeo(post.seo, post.title);
}

export default async function BlogPost({ params }: PostProps) {
  const { slug } = await params;
  const post = await getPost(slug);

  if (!post) {
    notFound();
  }

  return (
    <article className="post-page">
      <h1>{post.title}</h1>
      {post.excerpt ? <p>{post.excerpt}</p> : null}
      <section className="section">
        <div className="post-body" dangerouslySetInnerHTML={{ __html: post.content }} />
      </section>
    </article>
  );
}
