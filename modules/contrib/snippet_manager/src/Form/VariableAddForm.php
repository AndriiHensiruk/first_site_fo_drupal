<?php

namespace Drupal\snippet_manager\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form builder for "Add variable" form.
 *
 * @property \Drupal\snippet_manager\SnippetVariablePluginManager $variableManager
 */
class VariableAddForm extends VariableFormBase {

  /**
   * The variable manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $variableManager;

  /**
   * Constructs form object object.
   */
  public function __construct(PluginManagerInterface $variable_manager) {
    $this->variableManager = $variable_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.snippet_variable')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $variable_definitions = $this->variableManager->getDefinitions();

    $options = [];
    foreach ($variable_definitions as $plugin_id => $definition) {
      $options[(string) $definition['category']][$plugin_id] = (string) $definition['title'];
    }

    ksort($options);
    foreach ($options as &$sub_options) {
      asort($sub_options);
    }

    $form['plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of the variable'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Name of the variable'),
      '#machine_name' => [
        'exists' => [$this->entity, 'variableExists'],
      ],
      '#size' => 25,
      '#description' => $this->t('Can only contain lowercase letters, numbers, and underscores.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = parent::actionsElement($form, $form_state);

    $element['submit']['#value'] = $this->t('Save and continue');
    $element['delete']['#access'] = FALSE;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    // Intilize variable plugin to obtain default configuration.
    $plugin = $this->variableManager->createInstance($form_state->getValue('plugin_id'));

    $variable = [
      'plugin_id' => $form_state->getValue('plugin_id'),
      // A user does not have to submit variable edit form (step 2). So we need
      // to ensure proper default configuration for the variable.
      'configuration' => $plugin->defaultConfiguration(),
    ];

    $this->entity->setVariable(
      $form_state->getValue('name'),
      $variable
    );

    $result = $this->entity->save();

    $this->messenger()->addStatus($this->t('The variable has been created.'));

    $redirect_url = Url::fromRoute(
      'snippet_manager.variable_edit_form',
      [
        'snippet' => $this->entity->id(),
        'variable' => $form_state->getValue('name'),
      ]
    );
    $form_state->setRedirectUrl($redirect_url);

    return $result;
  }

}
