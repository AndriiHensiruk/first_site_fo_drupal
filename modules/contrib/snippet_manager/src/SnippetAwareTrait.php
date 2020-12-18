<?php

namespace Drupal\snippet_manager;

/**
 * Provides an interface for objects that depend on a snippet.
 *
 * @see \Drupal\snippet_manager\SnippetAwareInterface
 */
trait SnippetAwareTrait {

  /**
   * The snippet.
   *
   * @var \Drupal\snippet_manager\SnippetInterface
   */
  protected $snippet;

  /**
   * {@inheritdoc}
   */
  public function getSnippet() {
    return $this->snippet;
  }

  /**
   * {@inheritdoc}
   */
  public function setSnippet(SnippetInterface $snippet) {
    $this->snippet = $snippet;
  }

}
