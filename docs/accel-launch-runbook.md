# Accel Skills Hub Headless Launch Runbook

## Domain model

- `accelskillshub.com` — Next.js public corporate website on Vercel
- `learn.accelskillshub.com` — WordPress, Tutor LMS Pro, WooCommerce, registration, checkout, and learner dashboard

## 1. Clone and protect the LMS

1. Take a full Hostinger filesystem and database backup.
2. Clone production into a staging location.
3. Record current DNS values, payment callback URLs, cron configuration, SMTP configuration, and WooCommerce webhooks.
4. Create `learn.accelskillshub.com` and provision TLS before changing WordPress URLs.
5. Change `home` and `siteurl`, then run a serialized-safe WP-CLI search/replace:

   ```bash
   wp search-replace 'https://accelskillshub.com' 'https://learn.accelskillshub.com' --all-tables --precise --skip-columns=guid --dry-run
   wp search-replace 'https://accelskillshub.com' 'https://learn.accelskillshub.com' --all-tables --precise --skip-columns=guid
   wp cache flush
   wp rewrite flush
   ```

6. Keep the original backup and DNS values until the post-launch acceptance window closes.

## 2. Revalidate WordPress commerce and learning

Check a published free course and a published paid course end to end:

- course catalogue and detail
- student registration and login
- password reset
- cart, checkout, payment callback, and order email
- enrolment creation
- learner dashboard
- lesson access and progress
- Tutor and WooCommerce emails

No learner state, credentials, protected lessons, or checkout data should cross into Next.js.

## 3. Configure EasyHeadless 0.3

1. Install and activate the bridge on staging.
2. Set frontend URL to the Vercel preview URL and LMS URL to `https://learn.accelskillshub.com`.
3. Set login, registration, dashboard, and course catalogue links.
4. Enable Core, Portfolio, Tutor LMS, and Forms. Enable Church only on sites that use it.
5. Select the primary WordPress menu.
6. Build and order the portfolio from the Media Library.
7. Enter curated Tutor course IDs or leave empty for the latest-course fallback.
8. Add only approved Fluent Form IDs to the public allowlist.
9. Add exact preview and production origins to CORS.
10. Configure the Vercel revalidation endpoint and matching secret.
11. Leave headless routing disabled until the public site is accepted.

## 4. Configure Vercel

Use `apps/accel-skills-hub` as the project root and copy the variables from `.env.example`. Connect the staging bridge first. Verify preview mode, revalidation, contact submission, and all LMS links before assigning the main domain.

## 5. Redirect and SEO map

- WordPress pages redirect to the same public path.
- WordPress posts redirect from their legacy path to `/insights/{slug}`.
- Tutor course, cart, checkout, account, registration, dashboard, REST, admin, and authentication routes stay on `learn`.
- Next.js owns the public sitemap, canonicals, organization schema, and article schema.
- Exclude redirected WordPress pages/posts from the LMS sitemap while keeping Tutor courses.

## 6. Cutover

1. Complete staging acceptance on mobile and desktop.
2. Save the final pre-cutover database and filesystem backup.
3. Enable headless routing on WordPress.
4. Assign `accelskillshub.com` to the accepted Vercel deployment.
5. Validate TLS, DNS, canonical tags, sitemap, redirects, forms, registration, checkout, and callbacks.
6. Monitor error logs, payment events, SMTP delivery, and Core Web Vitals.
7. Roll back by restoring the recorded DNS values and disabling headless routing if a launch-blocking issue appears.

## Required client inputs

- Hostinger/DNS, WordPress administrator, and Vercel access
- final vector/transparent Accel, Accel Tech Hub, and CeeTee logos
- approved portfolio media, usage permissions, and alt-text guidance
- approved testimonials
- confirmed contact, social, privacy, and legal information
- approved Fluent Form IDs and destination addresses
- final copy approval
