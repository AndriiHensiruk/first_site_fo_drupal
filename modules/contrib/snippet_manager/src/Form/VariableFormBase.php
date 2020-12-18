<?php

namespace Drupal\snippet_manager\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a base class for variable forms.
 *
 * @property \Drupal\snippet_manager\SnippetInterface $entity
 */
abstract class VariableFormBase extends EntityForm {

  /**
   * The variable plugin.
   *
   * @var \Drupal\snippet_manager\SnippetVariableInterface
   */
  protected $plugin;

  /**
   * The variable definition.
   *
   * @var array
   */
  protected $variable;

  /**
   * Initialize the form state and the entity before the first form build.
   */
  protected function init(FormStateInterface $form_state) {
    parent::init($form_state);
    if ($this->operation != 'variable_add') {
      $this->variable = $this->entity->getVariable($this->getVariableName());
      if (!$this->variable) {
        throw new NotFoundHttpException();
      }
    }
  }

  /**
   * Plugin getter.
   *
   * The plugin cannot be set in the constructor because form $entity is not
   * ready at that moment.
   */
  protected function getPlugin() {
    if (!$this->plugin) {
      $this->plugin = $this->entity->getPluginCollection()->createInstance($this->getVariableName());
      if (!$this->plugin) {
        $message = $this->t('The %plugin plugin does not exist.', ['%plugin' => $this->variable['plugin_id']]);
        $this->messenger()->addWarning($message);
      }
    }
    return $this->plugin;
  }

  /**
   * Returns name of the variable.
   */
  protected function getVariableName() {
    return $this->getRouteMatch()->getParameter('variable');
  }

}
