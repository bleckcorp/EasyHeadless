import Link from "next/link";
import { getPosts } from "@/lib/easyheadless";

export const metadata = {
  title: "Blog",
};

export default async function BlogIndex() {
  const posts = await getPosts();

  return (
    <section className="section">
      <h1>Blog</h1>
      <div className="post-list">
        {posts.items.map((post) => (
          <article className="card" key={post.id}>
            <h2>
              <Link href={`/blog/${post.slug}`}>{post.title}</Link>
            </h2>
            {post.excerpt ? <p>{post.excerpt}</p> : null}
          </article>
        ))}
      </div>
    </section>
  );
}
