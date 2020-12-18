<?php

namespace Drupal\snippet_manager\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a 'Snippet' filter.
 *
 * @Filter(
 *   id = "snippet_manager_snippet",
 *   title = @Translation("Replace snippet tokens with their rendered values"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class Snippet extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $text = preg_replace_callback('/\[snippet:([a-z0-9_]+)\]/', [__CLASS__, 'renderSnippet'], $text);
    return new FilterProcessResult($text);
  }

  /**
   * Renders a snippet found by preg_replace_callback().
   *
   * @param array $matches
   *   Search results.
   *
   * @return string
   *   Rendered snippet or empty sting if the snippet was not loaded.
   */
  public static function renderSnippet(array $matches) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $snippet = $entity_type_manager->getStorage('snippet')->load($matches[1]);
    if ($snippet) {
      $build = $entity_type_manager->getViewBuilder('snippet')->view($snippet);
      return \Drupal::service('renderer')->render($build);
    }
    else {
      \Drupal::service('logger.channel.snippet_manager')
        ->error('Could not render snippet: %snippet', ['%snippet' => $matches[1]]);
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Snippet tokens are replaced with their rendered values. The tokens should look like follows: <code>[snippet:example]</code> where <em>example</em> is a snippet ID.');
  }

}
