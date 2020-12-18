<?php

namespace Drupal\snippet_manager;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface definition for 'snippet_variable' plugins.
 */
interface SnippetVariableInterface extends PluginFormInterface, PluginInspectionInterface, ConfigurableInterface, DependentPluginInterface {

  /**
   * Defines variable type.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Variable type.
   */
  public function getType();

  /**
   * Builds and returns variable content.
   *
   * @return array
   *   A renderable array.
   */
  public function build();

  /**
   * Returns variable operations.
   *
   * @return array
   *   List of operation links.
   */
  public function getOperations();

  /**
   * Acts on variables before they are deleted.
   */
  public function preDelete();

}
