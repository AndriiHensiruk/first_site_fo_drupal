<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\snippet_manager\SnippetVariableBase;

/**
 * Provides main content variable type.
 *
 * @SnippetVariable(
 *   id = "display_variant:main_content",
 *   title = @Translation("Main content"),
 *   category = @Translation("Display variant"),
 * )
 */
class DisplayVariantMainContent extends SnippetVariableBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return ['#markup' => '[main_content]'];
  }

}
