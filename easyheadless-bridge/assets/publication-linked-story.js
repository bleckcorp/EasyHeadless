(function (blocks, blockEditor, components, data, element) {
    var el = element.createElement;

    blocks.registerBlockType('easyheadless/linked-story', {
        title: 'Linked Film Efiko story',
        icon: 'admin-links',
        category: 'embed',
        attributes: {
            postId: { type: 'integer', default: 0 },
            variant: { type: 'string', default: 'card' }
        },
        edit: function (props) {
            var posts = data.useSelect(function (select) {
                return select('core').getEntityRecords('postType', 'post', { per_page: 100, status: 'publish', orderby: 'date', order: 'desc' });
            }, []);
            var choices = [{ label: 'Choose a published story', value: 0 }].concat((posts || []).map(function (post) {
                return { label: post.title.rendered.replace(/<[^>]+>/g, ''), value: post.id };
            }));
            return el('div', blockEditor.useBlockProps({ className: 'easyheadless-linked-story-editor' }),
                el(components.SelectControl, {
                    label: 'Linked story',
                    value: props.attributes.postId,
                    options: choices,
                    onChange: function (value) { props.setAttributes({ postId: parseInt(value, 10) || 0 }); }
                }),
                el(components.SelectControl, {
                    label: 'Display',
                    value: props.attributes.variant,
                    options: [
                        { label: 'Featured image only', value: 'image' },
                        { label: 'Featured image and headline', value: 'card' }
                    ],
                    onChange: function (value) { props.setAttributes({ variant: value }); }
                })
            );
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.element));
