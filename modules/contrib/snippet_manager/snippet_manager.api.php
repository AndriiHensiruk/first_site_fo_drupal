<?php

/**
 * @file
 * Hooks specific to the Snippet manager module.
 */

use Drupal\snippet_manager\SnippetInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Provides render context for a given snippet entity.
 *
 * @param \Drupal\snippet_manager\SnippetInterface $snippet
 *   The snippet entity.
 *
 * @return array
 *   Snippet context items
 *
 * @deprecated Use hook_snippet_view_alter() instead.
 */
function hook_snippet_context(SnippetInterface $snippet) {
  $context = [];
  if ($snippet->id() == 'foo') {
    $context['bar'] = [
      '#type' => 'details',
      '#title' => t('Click me'),
      '#value' => t('Hello world!'),
    ];
  }
  return $context;
}

/**
 * Alters snippet context before rendering.
 *
 * @param array $context
 *   Snippet context to be altered.
 * @param \Drupal\snippet_manager\SnippetInterface $snippet
 *   The snippet entity.
 *
 * @deprecated Use hook_snippet_view_alter() instead.
 */
function hook_snippet_context_alter(array &$context, SnippetInterface $snippet) {
  if ($snippet->id() == 'foo') {
    $context['bar'] = 'New value';
  }
}

/**
 * Alters the result of \Drupal\snippet_manager\SnippetViewBuilder::view().
 *
 * @param array &$build
 *   A renderable array of data.
 * @param \Drupal\snippet_manager\SnippetInterface $snippet
 *   The snippet entity.
 * @param string $view_mode
 *   (optional) The view mode to render the snippet.
 */
function hook_snippet_view_alter(array &$build, SnippetInterface $snippet, $view_mode) {
  if ($snippet->id() == 'foo') {
    $build['snippet']['#context']['bar'] = 'Bar value';
  }
}

/**
 * @} End of "addtogroup hooks".
 */
