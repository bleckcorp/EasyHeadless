(function () {
  'use strict';
  function enhance(root) {
    if (root.dataset.ehEnhanced) return;
    root.dataset.ehEnhanced = 'true';
    const select = root.querySelector('[data-eh-issue-select]');
    const search = root.querySelector('[data-eh-issue-search]');
    const options = Array.from(select.options).map(option => option.cloneNode(true));
    const status = root.querySelector('[data-eh-issue-search-status]');
    const preview = root.querySelector('[data-eh-issue-preview]');
    const cover = root.querySelector('[data-eh-issue-cover]');
    const access = root.querySelector('[data-eh-reader-access]');
    const restricted = root.querySelector('[data-eh-restricted-fields]');
    const presentation = root.querySelector('[data-eh-presentation]');
    root.querySelector('[data-eh-issue-search-wrap]').hidden = false;

    function issuePreview() {
      const option = options.find(item => item.value === select.value);
      preview.hidden = !option || option.value === '0';
      if (preview.hidden) return;
      root.querySelector('[data-eh-issue-title]').textContent = option.dataset.title;
      root.querySelector('[data-eh-issue-detail]').textContent = [option.dataset.volume, option.dataset.date, option.dataset.status].filter(Boolean).join(' · ');
      cover.hidden = !option.dataset.cover;
      if (option.dataset.cover) cover.src = option.dataset.cover;
      else cover.removeAttribute('src');
    }
    cover.addEventListener('error', () => { cover.hidden = true; });
    search.addEventListener('input', () => {
      const value = select.value;
      const query = search.value.trim().toLocaleLowerCase();
      const matches = options.filter(option => option.value !== '0' && [option.dataset.title, option.dataset.volume].join(' ').toLocaleLowerCase().includes(query));
      const included = new Set(matches.map(option => option.value));
      // Keep the current assignment available even when a search doesn't match it.
      const filtered = options.filter(option => option.value === '0' || option.value === value || included.has(option.value));
      select.replaceChildren(...filtered.map(option => option.cloneNode(true)));
      select.value = value;
      status.textContent = query ? `${matches.length} matching issue${matches.length === 1 ? '' : 's'}.` : '';
    });
    select.addEventListener('change', issuePreview);
    function readerAccess() { restricted.hidden = access.value !== 'members'; }
    access.addEventListener('change', readerAccess);
    function presentationHelp() {
      root.querySelector('[data-eh-presentation-help]').textContent = {
        inherit: "Uses the presentation configured for this story's category.",
        nation: 'The standard editorial article layout.',
        'new-yorker': 'Centered split hero with white text on black. The article body stays white.',
        'new-yorker-white': 'The same centered split hero with black text on white. The article body stays white.',
      }[presentation.value];
    }
    presentation.addEventListener('change', presentationHelp);
    issuePreview(); readerAccess(); presentationHelp();
  }
  function init() { document.querySelectorAll('[data-eh-publication-editor]').forEach(enhance); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
