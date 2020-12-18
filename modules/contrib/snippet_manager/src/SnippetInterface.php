<?php

namespace Drupal\snippet_manager;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a snippet entity type.
 */
interface SnippetInterface extends ConfigEntityInterface {

  /**
   * Variable getter.
   */
  public function getVariable($name);

  /**
   * Variable setter.
   */
  public function setVariable($name, $variable);

  /**
   * Removes variable.
   */
  public function removeVariable($name);

  /**
   * Determines if the variable already exists.
   *
   * @param string $name
   *   The name of the variable.
   *
   * @return bool
   *   TRUE if the variable exists, FALSE otherwise.
   */
  public function variableExists($name);

  /**
   * Returns layout regions sorted by weight.
   *
   * @return array
   *   Layout regions.
   */
  public function getLayoutRegions();

  /**
   * Returns a collection of snippet variables.
   *
   * @return \Drupal\snippet_manager\SnippetVariableCollection
   *   The collection if initialized snippet variables.
   */
  public function getPluginCollection();

}
