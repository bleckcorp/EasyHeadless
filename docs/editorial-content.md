# Article formatting in a headless publication

Publication story endpoints render native Elementor content on WordPress, then preserve its editorial HTML for the frontend. Film Efiko provides its own article styles; it does not load Elementor's browser JavaScript or copy arbitrary Elementor layouts.

## Drop caps anywhere in an article

- In Elementor, add a **Text Editor** widget where a new section starts, then enable **Drop Cap** in its Content controls. This decorates the first paragraph of that widget, even halfway through an article.
- To decorate a later paragraph inside the same widget, use its Text/HTML editor and set `<p class="has-drop-cap">Your paragraph…</p>`.
- In WordPress's block editor, use the Paragraph block's **Drop cap** switch.
- As an alternative, put `has-drop-cap` in a Text Editor widget's Advanced → CSS Classes field. The first non-empty paragraph in that widget receives the drop cap.

The opening paragraph gets an automatic drop cap. Existing explicit Elementor drop-cap spans are preserved without adding a duplicate letter. The response carries the `has-drop-cap` paragraph class, which the frontend must style with `::first-letter`.

## Content preservation and limits

Native Elementor rendering preserves text, inline emphasis, links, headings, images and captions. Animation invisibility is removed so article content does not wait for Elementor JavaScript that isn't installed on the headless frontend. If native rendering is unavailable or empty, the bridge uses normal WordPress content; if that is also empty, it extracts basic saved Text Editor, Heading and Image widgets from Elementor data.

Fallback extraction is not a replacement for Elementor: forms, sliders, third-party widgets, custom CSS, dynamic tags and interactive elements require dedicated frontend adapters. Test those before using them in articles. The fallback does not execute widgets or their JavaScript.

Member-only stories still expose only their configured paragraph preview to public requests. Full rendered content remains behind the existing reader access flow.
