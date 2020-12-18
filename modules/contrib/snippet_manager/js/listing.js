/**
 * @file
 * Snippet filter.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Filters the snippets list.
   */
  Drupal.behaviors.snippetOverviewFilter = {
    attach: function () {

      var $searchFilter = $('[data-drupal-selector="sm-snippet-search"]');
      var $usageFilter = $('[data-drupal-selector="sm-snippet-usage"]');
      var $statusFilter = $('[data-drupal-selector="sm-snippet-status"]');
      var $resetButton = $('[data-drupal-selector="sm-snippet-reset"]');

      var $table = $('[data-drupal-selector="sm-snippet-list"]');
      var $rows = $table.find('tbody tr');

      $table.find('tbody').append('<tr class="empty-row"/>');
      var $emptyRow = $('.empty-row');
      $emptyRow
        .hide()
        .append('<td colspan="' + $table.find('th').length + '">' + Drupal.t('No snippets were found.') + '</td>');

      function filterSnippetList() {

        var query = $searchFilter.val();
        var regExp = new RegExp(query, 'i');

        var usage = $usageFilter.val().replace('_', '-');
        var status = $statusFilter.val();

        var searchCondition = function($row) {
          if (query.length >= 0) {
            var name = $row.find('td:eq(0)').text();
            var id = $row.find('td:eq(1)').text();
            return name.search(regExp) !== -1 || id.search(regExp) !== -1;
          }
          else {
            return true;
          }
        };

        var statusCondition = function($row) {
          return status.length === 0 || $row.has('td[data-status=' + status + ']').length === 1
        };

        var usageCondition = function($row) {
          if (usage === 'none') {
            var $result = $row
              .has('td[data-page="0"]')
              .has('td[data-block="0"]')
              .has('td[data-display-variant="0"]')
              .has('td[data-layout="0"]');
            return $result.length === 1;
          }
          return usage.length === 0 || $row.has('td[data-' + usage + '=1]').length === 1;
        };

        $rows.each(function (index, row) {
          var $row = $(row);
          $row.toggle(searchCondition($row) && usageCondition($row) && statusCondition($row));
        });

        $emptyRow.toggle($rows.filter(':visible').length === 0);
      }

      $searchFilter.keyup(Drupal.debounce(filterSnippetList, 100));
      $usageFilter.change(filterSnippetList);
      $statusFilter.change(filterSnippetList);

      $resetButton.click(function () {
        $searchFilter.val('');
        $usageFilter.val('');
        $statusFilter.val('');
        filterSnippetList();
      })

    }
  };

}(jQuery, Drupal));
