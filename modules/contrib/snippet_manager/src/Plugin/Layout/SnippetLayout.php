<?php

namespace Drupal\snippet_manager\Plugin\Layout;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a layout based on snippet template.
 *
 * @Layout(
 *   id = "snippet_layout",
 *   category = @Translation("Snippets"),
 *   deriver = "Drupal\snippet_manager\Plugin\Layout\SnippetLayoutDeriver",
 * )
 */
class SnippetLayout extends LayoutDefault implements ContainerFactoryPluginInterface {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

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
   *   The manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
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
      $container->get('logger.channel.snippet_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {

    $snippet = $this->entityTypeManager
      ->getStorage('snippet')
      ->load($this->getDerivativeId());

    if (!$snippet) {
      $this->logger->error('Could not load snippet: #%snippet', ['%snippet' => $this->getDerivativeId()]);
      return [];
    }

    // We cannot build the snippet at this point because regions needs to be
    // processed when form display is rendering.
    // @see template_preprocess_snippet_layout().
    $build = $regions;
    $build['#theme'] = 'snippet_layout';
    $build['#snippet'] = $snippet;

    return $build;
  }

}
