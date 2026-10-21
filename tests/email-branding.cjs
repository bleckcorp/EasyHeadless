const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function element(extra = {}) {
    return Object.assign({listeners: {}, addEventListener(event, fn) { this.listeners[event] = fn; }, removeAttribute(name) { delete this[name]; }}, extra);
}
const input = element({value: ''}), preview = element({hidden: true}), status = element({textContent: ''});
const select = element(), remove = element();
const fields = {'input': input, '[data-logo-preview]': preview, '[data-logo-status]': status, '[data-logo-select]': select, '[data-logo-remove]': remove};
let mediaOptions, selection, opens = 0;
const context = {URL, document: {readyState: 'complete', querySelectorAll: () => [{querySelector: key => fields[key]}]}, window: {wp: {media(options) {
    mediaOptions = options;
    return {on(event, callback) { selection = callback; }, open() { opens++; }, state() { return {get: () => ({first: () => ({toJSON: () => ({url: 'https://cms.example/uploads/logo.png'})})})}; }};
}}}};
vm.runInNewContext(fs.readFileSync('easyheadless-bridge/assets/email-branding.js', 'utf8'), context);
select.listeners.click(); selection();
assert.equal(mediaOptions.library.type, 'image'); assert.equal(mediaOptions.multiple, false);
assert.equal(input.value, 'https://cms.example/uploads/logo.png'); assert.equal(preview.hidden, false);
assert.match(status.textContent, /Save publication settings/);
select.listeners.click(); assert.equal(opens, 2);
remove.listeners.click(); assert.equal(input.value, ''); assert.equal(preview.hidden, true);
input.value = 'javascript:alert(1)'; input.listeners.input(); assert.equal(preview.hidden, true);
preview.listeners.error(); assert.match(status.textContent, /could not be loaded/);
console.log('Email logo picker selection, reuse, removal and unsafe URL tests passed.');
