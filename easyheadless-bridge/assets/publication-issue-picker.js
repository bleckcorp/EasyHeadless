(function () {
  'use strict';
  document.querySelectorAll('.eh-issue-story-picker').forEach(function (picker) {
    var selected = JSON.parse(picker.dataset.selected || '[]');
    var multiple = picker.dataset.multiple === '1';
    var input = picker.querySelector('.eh-issue-search');
    var hidden = picker.querySelector('.eh-issue-selection');
    var list = picker.querySelector('.eh-issue-selected');
    var results = picker.querySelector('.eh-issue-search-results');
    var status = picker.querySelector('.eh-issue-search-status');
    var timer;
    var generation = 0;

    function render() {
      hidden.value = selected.map(function (item) { return item.id; }).join(', ');
      list.replaceChildren();
      selected.forEach(function (item) {
        var row = document.createElement('li');
        var remove = document.createElement('button');
        row.append(document.createTextNode(item.title + ' '));
        remove.type = 'button';
        remove.className = 'button-link-delete';
        remove.textContent = 'Remove';
        remove.setAttribute('aria-label', 'Remove ' + item.title);
        remove.addEventListener('click', function () {
          selected = selected.filter(function (entry) { return entry.id !== item.id; });
          render();
        });
        row.append(remove);
        list.append(row);
      });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      var current = ++generation;
      var term = input.value.trim();
      results.replaceChildren();
      status.textContent = '';
      if (term.length < 2) return;
      timer = setTimeout(function () {
        status.textContent = 'Searching articles…';
        wp.apiFetch({ path: '/wp/v2/posts?search=' + encodeURIComponent(term) + '&per_page=20&_fields=id,title&status=publish' }).then(function (posts) {
          if (current !== generation) return;
          results.replaceChildren();
          var available = posts.filter(function (post) { return !selected.some(function (item) { return item.id === post.id; }); });
          status.textContent = available.length ? 'Select an article below.' : 'No matching articles. Try another title.';
          available.forEach(function (post) {
            var title = document.createElement('div');
            title.innerHTML = post.title.rendered;
            var item = { id: post.id, title: title.textContent };
            var row = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'button';
            button.style.whiteSpace = 'normal';
            button.style.textAlign = 'left';
            button.textContent = item.title;
            button.addEventListener('click', function () {
              selected = multiple ? selected.concat(item) : [item];
              render();
              ++generation;
              results.replaceChildren();
              input.value = '';
              status.textContent = 'Article added. Save the issue to keep this selection.';
            });
            row.append(button);
            results.append(row);
          });
        }).catch(function () {
          if (current === generation) status.textContent = 'Search could not be completed. Please try again.';
        });
      }, 300);
    });
    render();
  });
})();
