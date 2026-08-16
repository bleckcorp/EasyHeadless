import Link from "next/link";

export default function NotFound() {
  return (
    <section className="section">
      <h1>Page not found</h1>
      <p>The requested WordPress route was not found in EasyHeadless.</p>
      <Link className="button" href="/">
        Return home
      </Link>
    </section>
  );
}
