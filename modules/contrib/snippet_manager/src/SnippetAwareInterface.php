<?php

namespace Drupal\snippet_manager;

/**
 * Provides an interface for objects that depend on a snippet.
 */
interface SnippetAwareInterface {

  /**
   * Sets the snippet for this object.
   *
   * @param \Drupal\snippet_manager\SnippetInterface $snippet
   *   The snippet.
   */
  public function setSnippet(SnippetInterface $snippet);

  /**
   * Gets the snippet from this object.
   *
   * @return \Drupal\snippet_manager\SnippetInterface
   *   The snippet.
   */
  public function getSnippet();

}
