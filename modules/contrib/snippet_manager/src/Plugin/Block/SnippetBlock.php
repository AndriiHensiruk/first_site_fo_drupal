<?php

namespace Drupal\snippet_manager\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Snippet' block.
 *
 * @Block(
 *   id = "snippet",
 *   admin_label = @Translation("Snippet"),
 *   category = @Translation("Snippet"),
 *   deriver = "Drupal\snippet_manager\Plugin\Block\SnippetBlockDeriver"
 * )
 */
class SnippetBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SnippetBlock instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $snippet_id = $this->getDerivativeId();
    $snippet = $this->entityTypeManager->getStorage('snippet')->load($snippet_id);
    $build = $this->entityTypeManager->getViewBuilder('snippet')->view($snippet);
    $build['#contextual_links']['snippet']['route_parameters']['snippet'] = $snippet_id;
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Blocks for disabled snippets won't be registered but we still check access
   * in case there is custom access control implementation for the snippet.
   *
   * @see \Drupal\snippet_manager\Plugin\Block\SnippetBlockDeriver::getDerivativeDefinitions()
   */
  public function blockAccess(AccountInterface $account) {
    $snippet_id = $this->getDerivativeId();
    $snippet = $this->entityTypeManager->getStorage('snippet')->load($snippet_id);
    return $snippet->access('view', NULL, TRUE);
  }

}
