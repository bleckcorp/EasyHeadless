# Film Efiko Custom 0.6.12

Private client edition built from EasyHeadless. Build with `npm run package:film-efiko`; output is `dist/film-efiko-custom-0.6.12.zip`. The generic `npm run package:plugin` output remains generic EasyHeadless.

## Reader email branding

Under Publication → Reader sign-in email, choose the email logo from WordPress Media Library (image-only, single selection), review the black-header preview, then save publication settings. The existing public logo URL remains editable as a fallback; removing it uses the publication name. Remote images are referenced by public URL, not attached as CID files; recipients may need to enable images in their email client. Use a light/transparent PNG suitable for a black header.

The existing responsive HTML template is fixed: black branding, orange secure button, expiry instructions and fallback link. Admins edit subject, heading, message, button label and footer text, not the HTML design. Previous rich-text messages retain paragraph breaks and text; their styling/images are not rendered in this client edition. Generic EasyHeadless retains its previous rich-text editor.

Automated tests cover Media Library selection callbacks, preview/removal, image-only filtering, retained option names and fixed email markup. The local email preview uses a fake link and no mail transport. Actual WordPress Media Library permissions, saving and delivery should be confirmed on staging before live installation.

## Scope

- Branded Film Efiko Custom plugin entry, native sidebar menu and toolbar shortcuts.
- Church and Tutor LMS cannot be enabled in this edition and are absent from module/capability UI. Church post types/ACF groups and Church/Tutor-specific read routes are not registered.
- Core APIs, Publication, portfolio and forms remain available. Existing WordPress options, reader data, API paths, article metadata and issue records are preserved.
- Homepage Sections uses an accessible section selector and one visible editor panel. All panels remain in the same WordPress options form; switching tabs does not disable inputs or discard selections. Without JavaScript, every panel remains editable. Arrow keys, Home and End navigate tabs.
- Third-party `admin_notices`, `all_admin_notices` and `user_admin_notices` callbacks are suppressed only on EasyHeadless and issue-editor screens in this edition. Notices on other WordPress screens and stored notices are unchanged. WordPress settings save/error feedback remains available.
- Native Issues editing keeps the WordPress sidebar navigation; the plugin's inner navigation remains on plugin pages.
- Generic remote updater installation is blocked to prevent replacing this edition with an unbranded build. Update using the client ZIP.

## Install / rollback

1. Back up the current plugin folder and database; test on staging first.
2. Upload the ZIP in WordPress Plugins → Add Plugin → Upload Plugin and choose to replace the installed EasyHeadless Bridge plugin. The ZIP deliberately retains `easyheadless-bridge/easyheadless-bridge.php` so WordPress updates the existing installation instead of enabling two conflicting copies.
3. Confirm the plugin name Film Efiko Custom and version 0.6.12; visit Homepage Sections, Issues, Publication and Modules.
4. Confirm existing story IDs/category selections, styles and reader settings before saving any edits. No DNS changes, automatic migration/cutover or headless-routing changes occur during replacement.
5. Roll back by uploading the backed-up generic ZIP/folder. No content deletion or option-key migration is performed.

The curation editor renders existing titles exactly as stored. It does not silently rewrite editorial labels, links, selections, categories or publication dates.

## Verification

Run `npm run test:php`, `npm run test:film-efiko`, `npm run lint:php` and `npm run package:film-efiko`.
The local browser fixture at `/tests/php/film-efiko-admin.php?preview=1` renders the production PHP editor and production CSS/JavaScript with lightweight WordPress stubs. It is not a live WordPress save or installation test.

Design direction: generated desktop editor concept, native WordPress shell, neutral surfaces, orange selected-section border, black save action, two-column inputs. The final implementation retains the existing full inner navigation, plugin version, WordPress's native menu dimensions and saved field help text; these are intentional differences from the concept. QA checks cover typography, spacing, field alignment, selected state, native navigation, keyboard switching, preserved inputs and responsive overflow.
