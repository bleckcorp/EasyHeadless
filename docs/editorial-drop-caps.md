# Adding another drop cap in an article

Elementor's native **Text Editor → Content → Drop Cap** switch decorates the beginning of that widget. It does not let you select paragraph four inside one large widget.

To introduce a drop cap at paragraph four:

1. Keep paragraphs one–three in the existing Text Editor widget.
2. Add another native Text Editor widget immediately below it.
3. Move paragraph four and the following text into the new widget, preserving formatting and without duplicating the text.
4. Select the new widget, open **Content**, and switch **Drop Cap** on.
5. Update the article, then check its frontend story page. Repeat with another Text Editor widget for any subsequent section opening.

No Elementor Pro or custom HTML widget is required. Keep the widgets in the same article container with matching text styling. Images and other widgets can remain between the text sections. Paragraph numbering is within the article, but the drop-cap switch always acts at the beginning of each widget.

In the WordPress block editor, select the desired Paragraph block and enable its Drop cap typography option, where available. The bridge also preserves a `has-drop-cap` class on a paragraph inserted using the editor's HTML view. This is an advanced alternative, not necessary for Elementor.

The bridge normalizes native Elementor drop-cap settings for the headless frontend and preserves the native span markup when Elementor has already generated it. The automatic opening drop cap remains unchanged. Existing articles are not edited automatically.

Reference: [Elementor's native Text Editor widget](https://github.com/elementor/elementor/blob/main/includes/widgets/text-editor.php).
