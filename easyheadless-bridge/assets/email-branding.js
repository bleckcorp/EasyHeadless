(function () {
    'use strict';
    function init() {
        document.querySelectorAll('[data-eh-email-logo]').forEach(function (root) {
            var input = root.querySelector('input');
            var preview = root.querySelector('[data-logo-preview]');
            var status = root.querySelector('[data-logo-status]');
            var frame;
            function update() {
                var url;
                try { url = new URL(input.value); } catch (e) { url = null; }
                var valid = url && (url.protocol === 'https:' || url.protocol === 'http:');
                preview.hidden = !valid;
                if (valid) { preview.src = url.href; } else { preview.removeAttribute('src'); }
                status.textContent = 'Logo changed. Save publication settings to apply.';
            }
            preview.addEventListener('error', function () {
                preview.hidden = true;
                status.textContent = 'This image could not be loaded. Choose another image or check its public URL.';
            });
            input.addEventListener('input', update);
            root.querySelector('[data-logo-remove]').addEventListener('click', function () { input.value = ''; update(); });
            root.querySelector('[data-logo-select]').addEventListener('click', function () {
                if (!window.wp || !window.wp.media) {
                    status.textContent = 'Media Library is unavailable. Paste a public image URL instead.';
                    return;
                }
                if (!frame) {
                    frame = window.wp.media({title: 'Choose email logo', button: {text: 'Use this logo'}, library: {type: 'image'}, multiple: false});
                    frame.on('select', function () {
                        var attachment = frame.state().get('selection').first().toJSON();
                        input.value = attachment.url;
                        update();
                    });
                }
                frame.open();
            });
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());
