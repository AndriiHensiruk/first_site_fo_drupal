<?php

namespace Drupal\snippet_manager;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;

/**
 * Defines a collection of snippet variables.
 */
class SnippetVariableCollection implements \IteratorAggregate {

  /**
   * The snippet.
   *
   * @var \Drupal\snippet_manager\SnippetInterface
   */
  protected $snippet;

  /**
   * Snippet variable plugin manager.
   *
   * @var \Drupal\snippet_manager\SnippetVariablePluginManager
   */
  protected $variableManager;

  /**
   * SnippetVariableCollection constructor.
   *
   * @param \Drupal\snippet_manager\SnippetInterface $snippet
   *   The snippet.
   */
  public function __construct(SnippetInterface $snippet) {
    $this->snippet = $snippet;
  }

  /**
   * Returns the snippet variable plugin manager.
   *
   * @return \Drupal\snippet_manager\SnippetVariablePluginManager
   *   Snippet variable plugin manager.
   */
  protected function variableManager() {
    if (!$this->variableManager) {
      $this->variableManager = \Drupal::service('plugin.manager.snippet_variable');
    }
    return $this->variableManager;
  }

  /**
   * Creates a pre-configured instance of a snippet variable plugin.
   *
   * @param string $name
   *   A name of the variable.
   *
   * @return \Drupal\snippet_manager\SnippetVariableInterface|null
   *   A fully configured snippet variable or null if plugin was not found.
   */
  public function createInstance($name) {
    $variable = $this->snippet->getVariable($name);
    try {
      $plugin = $this->variableManager()
        ->createInstance($variable['plugin_id'], $variable['configuration']);
      if ($plugin instanceof SnippetAwareInterface) {
        $plugin->setSnippet($this->snippet);
      }
    }
    catch (PluginNotFoundException $exception) {
      $plugin = NULL;
    }
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $instances = [];
    foreach ($this->snippet->get('variables') as $name => $variable) {
      $instances[$name] = $this->createInstance($name);
    }
    return new \ArrayIterator($instances);
  }

}
