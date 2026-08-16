import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";
import { getSite } from "@/lib/easyheadless";

export async function generateMetadata(): Promise<Metadata> {
  const site = await getSite();

  return {
    title: {
      default: site.name,
      template: `%s | ${site.name}`,
    },
    description: site.description,
  };
}

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const site = await getSite();

  return (
    <html lang="en">
      <body>
        <div className="site-shell">
          <header className="site-header">
            <div className="site-header__inner">
              <Link className="brand" href="/">
                {site.contact.companyName || site.name}
              </Link>
              <nav className="site-nav" aria-label="Primary navigation">
                <Link href="/">Home</Link>
                <Link href="/blog">Blog</Link>
                {site.contact.email ? <a href={`mailto:${site.contact.email}`}>Contact</a> : null}
              </nav>
            </div>
          </header>
          <main className="site-main">{children}</main>
          <footer className="site-footer">
            <div className="site-footer__inner">
              <span>{site.contact.companyName || site.name}</span>
              <span>{site.contact.phone || site.contact.email}</span>
            </div>
          </footer>
        </div>
      </body>
    </html>
  );
}
