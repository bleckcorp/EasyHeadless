(function () {
  'use strict';
  function initialize() {
    document.querySelectorAll('[data-eh-curation-editor]').forEach(function (form) {
      var tabs = Array.from(form.querySelectorAll('[data-eh-section-tab]'));
      var panels = Array.from(form.querySelectorAll('[data-eh-section-panel]'));
      if (!tabs.length || !panels.length) return;
      form.classList.add('is-enhanced');
      form.querySelector('.eh-curation-tabs').setAttribute('role', 'tablist');
      var tablist = form.querySelector('.eh-curation-tabs');
      var mobile = window.matchMedia('(max-width:782px)');
      function orientation() { tablist.setAttribute('aria-orientation', mobile.matches ? 'horizontal' : 'vertical'); }
      orientation();
      if (mobile.addEventListener) mobile.addEventListener('change', orientation);
      else mobile.addListener(orientation);
      function activate(tab, focus) {
        tabs.forEach(function (item) {
          var active = item === tab;
          item.setAttribute('role', 'tab');
          item.setAttribute('aria-selected', String(active));
          item.tabIndex = active ? 0 : -1;
        });
        panels.forEach(function (panel) {
          panel.setAttribute('role', 'tabpanel');
          panel.hidden = panel.dataset.ehSectionPanel !== tab.dataset.ehSectionTab;
        });
        if (focus) tab.focus();
      }
      tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activate(tab, false); });
        tab.addEventListener('keydown', function (event) {
          var next = event.key === 'ArrowDown' || event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowUp' || event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : -1;
          if (next < 0) return;
          event.preventDefault();
          activate(tabs[next], true);
        });
      });
      activate(tabs.find(function (tab) { return tab.dataset.ehSectionTab === 'hot-hot-latest'; }) || tabs[0], false);
      form.addEventListener('input', function (event) {
        form.querySelector('[data-eh-save-status]').textContent = 'Unsaved changes';
        if (!event.target.matches('[data-eh-section-title]')) return;
        var panel = event.target.closest('[data-eh-section-panel]');
        var tab = tabs.find(function (item) { return item.dataset.ehSectionTab === panel.dataset.ehSectionPanel; });
        var title = event.target.value.trim() || 'Untitled section';
        tab.textContent = title;
        panel.querySelector('[data-eh-panel-title]').textContent = title;
      });
      // Hidden panels retain every input: a single submission preserves all sections.
      form.addEventListener('submit', function () { form.querySelector('[data-eh-save-status]').textContent = 'Saving homepage…'; });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
  else initialize();
}());
