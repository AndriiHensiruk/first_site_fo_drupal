<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\snippet_manager\SnippetVariableBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides entity form variable type.
 *
 * @SnippetVariable(
 *   id = "entity_form",
 *   title = @Translation("Entity form"),
 *   category = @Translation("Entity form"),
 *   deriver = "\Drupal\snippet_manager\Plugin\SnippetVariable\EntityFormDeriver",
 * )
 */
class EntityForm extends SnippetVariableBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * Constructs the object.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, EntityFormBuilderInterface $entity_form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityType = $this->getDerivativeId();
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $entity_type_definition = $this->entityTypeManager->getDefinition($this->entityType);
    $bundle_entity_type = $entity_type_definition->getBundleEntityType();

    // Not all entity types have bundles.
    if ($bundle_entity_type) {
      $bundles = $this->entityTypeManager->getStorage($bundle_entity_type)->loadMultiple();
      $options = [];
      foreach ($bundles as $bundle) {
        $options[$bundle->id()] = $bundle->label();
      }
      $form['bundle'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => $this->t('Bundle'),
        '#default_value' => $this->configuration['bundle'],
        '#required' => TRUE,
      ];
    }

    $options = $this->entityDisplayRepository->getFormModeOptions($this->entityType);
    foreach ($options as $key => $label) {
      // Form classes do not get automatically registered for all
      // configured form view modes.
      if (!$entity_type_definition->getFormClass($key)) {
        unset($options[$key]);
      }
    }

    $form['form_mode'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Form mode'),
      '#default_value' => $this->configuration['form_mode'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $entity_storage = $this->entityTypeManager
      ->getStorage($this->entityType);

    $values = [];

    $bundle_key = $this->entityTypeManager
      ->getDefinition($this->entityType)
      ->getKey('bundle');
    if ($bundle_key) {
      // This option may not be set if a user has not submitted the plugin
      // configuration form.
      if (!$this->configuration['bundle']) {
        return;
      }
      $values[$bundle_key] = $this->configuration['bundle'];
    }

    $build = [];

    $entity = $entity_storage->create($values);

    $access = $entity->access('create', NULL, TRUE);
    if ($access->isAllowed()) {
      $build = $this->entityFormBuilder
        ->getForm($entity, $this->configuration['form_mode']);
    }

    CacheableMetadata::createFromRenderArray($build)
      ->merge(CacheableMetadata::createFromObject($entity))
      ->merge(CacheableMetadata::createFromObject($access))
      ->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form_mode' => 'default',
      'bundle' => NULL,
    ];
  }

}
