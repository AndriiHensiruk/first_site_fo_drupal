/**
 * @file
 * Snippet CKEditor plugin.
 */
(function (Drupal) {

  'use strict';

  CKEDITOR.plugins.add( 'snippet_manager_snippet', {
    requires: 'menubutton',
    icons: 'snippet',

    init: function (editor) {

      var snippets = editor.config.snippets;

      var items = {};
      items['label'] = {
        label: Drupal.t('Select a snippet'),
        state: CKEDITOR.TRISTATE_ON,
        group: 'default'
      };
      Object.keys(editor.config.snippets).forEach(function (key) {
        items[key] = {
          label: snippets[key],
          state: CKEDITOR.TRISTATE_OFF,
          group: 'default',
          onClick: function() {
            editor.insertText('[snippet:' + key + ']')
          }
        };
      });

      editor.addMenuGroup('default', 1);
      editor.addMenuItems(items);

      var options = {
        label: Drupal.t('Snippet'),
        icon: 'snippet',
        onMenu: function () {
          var activeItems = {};
          Object.keys(items).forEach(function (key) {
            activeItems[key] = CKEDITOR.TRISTATE_OFF;
          });
          return activeItems;
        }
      };
      editor.ui.add( 'snippet', CKEDITOR.UI_MENUBUTTON, options);
    }

  });

} (Drupal));
