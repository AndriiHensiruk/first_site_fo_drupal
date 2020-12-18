<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\snippet_manager\SnippetVariableBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides layout region variable type.
 *
 * @SnippetVariable(
 *   id = "layout_region",
 *   title = @Translation("Layout region"),
 *   category = @Translation("Other"),
 * )
 */
class LayoutRegion extends SnippetVariableBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $variable_name = $this->routeMatch->getParameter('variable');

    $default_label = $this->configuration['label'] ?:
      ucfirst(trim(str_replace('_', ' ', $variable_name)));

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $default_label,
      '#required' => TRUE,
    ];

    $delta = max(abs($this->configuration['weight']), 50);
    $form['weight'] = [
      '#type' => 'number',
      '#min' => -$delta,
      '#max' => $delta,
      '#default_value' => $this->configuration['weight'],
      '#title' => $this->t('Weight'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label' => '',
      'weight' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // This value is always overridden.
    // @see template_preprocess_snippet_layout()
    return NULL;
  }

}
