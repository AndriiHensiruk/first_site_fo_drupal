<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\snippet_manager\SnippetVariableBase;

/**
 * Provides page title variable type.
 *
 * @SnippetVariable(
 *   id = "display_variant:title",
 *   title = @Translation("Page title"),
 *   category = @Translation("Display variant"),
 * )
 */
class DisplayVariantTitle extends SnippetVariableBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return ['#markup' => '[page_title]'];
  }

}
