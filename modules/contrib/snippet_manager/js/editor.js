/**
 * @file
 * Snippet manager behaviors.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.snippetManager = {
    attach: function () {

      var $textArea = $('textarea[data-codemirror]')
        .once('snippet_manager_editor');

      if ($textArea.length !== 1) {
        return;
      }

      var doc = $('.CodeMirror')[0].CodeMirror.getDoc();

      // Insert variables into editor.
      $('[data-drupal-selector="snippet-variable"]').click(function () {
        doc.replaceSelection('{{ ' + $(this).text() + ' }}', doc.getCursor());
        return false;
      });

    }
  };

}(jQuery, Drupal));
