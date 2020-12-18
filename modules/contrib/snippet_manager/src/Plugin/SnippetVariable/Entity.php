<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\snippet_manager\SnippetVariableBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Entity variable type.
 *
 * @SnippetVariable(
 *   id = "entity",
 *   title = @Translation("Entity"),
 *   category = @Translation("Entity"),
 *   deriver = "\Drupal\snippet_manager\Plugin\SnippetVariable\EntityDeriver",
 * )
 */
class Entity extends SnippetVariableBase implements ContainerFactoryPluginInterface {

  use MessengerTrait;

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
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs entity variable object.
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
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, LoggerChannelInterface $logger, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityType = $this->getDerivativeId();
    $this->entityDisplayRepository = $entity_display_repository;
    $this->logger = $logger;
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
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('logger.channel.snippet_manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $entity = NULL;
    if ($this->configuration['entity_id']) {
      $storage = $this->entityTypeManager->getStorage($this->entityType);
      $entity = $storage->load($this->configuration['entity_id']);

      if (!$entity) {
        $message = $this->t('Could not load entity: #%entity', ['%entity' => $this->configuration['entity_id']]);
        $this->messenger()->addError($message);
      }
    }

    $entity_type_definition = $this->entityTypeManager
      ->getDefinition($this->entityType);

    $form['entity_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $entity_type_definition->getLabel(),
      '#default_value' => $entity,
      '#maxlength' => 2048,
      '#target_type' => $this->entityType,
      '#description' => $this->t('Leave the field empty to load the entity dynamically from request.'),
    ];

    $form['view_mode'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->entityType),
      '#title' => $this->t('View mode'),
      '#default_value' => $this->configuration['view_mode'],
      '#required' => TRUE,
    ];

    if ($entity_type_definition->get('field_ui_base_route')) {
      $form['render_mode'] = [
        '#title' => $this->t('Render mode'),
        '#type' => 'radios',
        '#options' => [
          'entity' => $this->t('Entity'),
          'fields' => $this->t('Fields'),
        ],
        '#default_value' => $this->configuration['render_mode'],
        '#required' => TRUE,
      ];
    }

    $form['bypass_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bypass access checks'),
      '#description' => $this->t('If enabled, access permissions for rendering the entity are not checked.'),
      '#default_value' => $this->configuration['bypass_access'],
    ];

    return $form;
  }

  /**
   * Loads entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Loaded entity or null if the entity was not found.
   */
  protected function loadEntity() {
    if ($this->configuration['entity_id']) {
      $entity = $this->entityTypeManager->getStorage($this->entityType)
        ->load($this->configuration['entity_id']);
      if (!$entity) {
        $this->logger->error(
          'Could not load @entity_type: #@entity_id.',
          [
            '@entity_type' => $this->entityType,
            '@entity_id' => $this->configuration['entity_id'],
          ]
        );
      }
    }
    else {
      $entity = $this->routeMatch->getParameter($this->entityType);
    }
    if (is_object($entity)) {
      return $entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $cache_metadata = new CacheableMetadata();

    if (!$this->configuration['entity_id']) {
      $cache_metadata->addCacheContexts(['url']);
    }

    if (!$entity = $this->loadEntity()) {
      $cache_metadata->applyTo($build);
      return $build;
    }

    $access = $this->configuration['bypass_access'] ?
      AccessResult::allowed() : $entity->access('view', NULL, TRUE);

    $cache_metadata
      ->merge(CacheableMetadata::createFromObject($entity))
      ->merge(CacheableMetadata::createFromObject($access));

    if ($access->isAllowed()) {

      // We do not configure #cache for this mode assuming it will "bubble" when
      // entity object is rendered.
      if ($this->configuration['render_mode'] == 'entity') {
        $build = $this->entityTypeManager
          ->getViewBuilder($this->entityType)
          ->view($entity, $this->configuration['view_mode']);
      }
      else {
        $display_id = $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $this->configuration['view_mode'];
        /* @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display_mode */
        $display_mode = $this->entityTypeManager->getStorage('entity_view_display')->load($display_id);

        foreach ($display_mode->get('fieldDefinitions') as $field_name => $field_definition) {
          $build[$field_name] = $entity->{$field_name}->view($this->configuration['view_mode']);
          // The fields may be rendered individually so the cache should be
          // applied individually.
          $cache_metadata
            ->merge(CacheableMetadata::createFromRenderArray($build[$field_name]))
            ->applyTo($build[$field_name]);
        }
      }
    }

    $cache_metadata
      ->merge(CacheableMetadata::createFromRenderArray($build))
      ->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations() {
    $links = parent::getOperations();
    $entity = $this->loadEntity();
    if ($entity && $entity->hasLinkTemplate('edit-form')) {
      $label = $entity->getEntityType()->getSingularLabel();
      $links['edit_entity'] = [
        'title' => $this->t('Edit @label', ['@label' => $label]),
        'url' => $entity->toUrl('edit-form'),
      ];
    }
    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'entity_id' => NULL,
      'view_mode' => 'default',
      'render_mode' => 'entity',
      'bypass_access' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = [];
    if ($entity = $this->loadEntity()) {
      if ($entity instanceof ConfigEntityInterface) {
        $dependencies['config'][] = $entity->getConfigDependencyName();
      }
      elseif ($entity instanceof ContentEntityInterface) {
        $dependencies['content'][] = $entity->getConfigDependencyName();
      }
    }
    return $dependencies;
  }

}
