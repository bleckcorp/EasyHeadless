(function ($) {
  'use strict';

  function setOperation(value) {
    $('#eh-portfolio-operation').val(value);
  }

  $(function () {
    var $portfolioForm = $('#eh-portfolio-form');
    var $bulkBar = $('.eh-bulk-bar');
    var $inspector = $('#eh-inspector');

    $('#eh-add-media').on('click', function () {
      if (!window.wp || !wp.media) return;
      var frame = wp.media({ title: 'Add portfolio images', library: { type: 'image' }, multiple: true });
      frame.on('select', function () {
        var ids = frame.state().get('selection').map(function (attachment) { return attachment.get('id'); });
        if (!ids.length) return;
        $('#eh-add-attachment-ids').val(ids.join(','));
        setOperation('add');
        $portfolioForm.trigger('submit');
      });
      frame.open();
    });

    $portfolioForm.on('click', '[data-operation]', function () {
      setOperation($(this).data('operation'));
    });

    $portfolioForm.on('change', 'input[name="selected[]"]', function () {
      var count = $portfolioForm.find('input[name="selected[]"]:checked').length;
      $('#eh-selected-count').text(count);
      $bulkBar.prop('hidden', count === 0);
    });

    function inspectCard($card) {
      if (!$card.length) return;
      $('#eh-item-id').val($card.data('id'));
      $('#eh-item-title').val($card.attr('data-title'));
      $('#eh-item-caption').val($card.attr('data-caption'));
      $('#eh-item-alt').val($card.attr('data-alt'));
      $('#eh-item-category').val($card.attr('data-category'));
      $('#eh-inspector-name').text($card.find('.eh-portfolio-card-body strong').text());
      $inspector.prop('hidden', false);
      $('#eh-item-title').trigger('focus');
    }

    $portfolioForm.on('dblclick', '.eh-portfolio-card', function (event) {
      if ($(event.target).is('input')) return;
      inspectCard($(this));
    });

    $portfolioForm.on('keydown', '.eh-portfolio-card', function (event) {
      if (event.key === 'Enter') inspectCard($(this));
    });

    $('#eh-edit-selected').on('click', function () {
      var $selected = $portfolioForm.find('input[name="selected[]"]:checked').first().closest('.eh-portfolio-card');
      inspectCard($selected);
    });

    $('#eh-close-inspector').on('click', function () {
      $inspector.prop('hidden', true);
    });
  });
})(jQuery);
