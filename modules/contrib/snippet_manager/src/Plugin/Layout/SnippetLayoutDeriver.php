<?php

namespace Drupal\snippet_manager\Plugin\Layout;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Registers layouts for snippet config entity.
 */
class SnippetLayoutDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The snippet storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $snippetStorage;

  /**
   * Constructs a Snippet object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $snippet_storage
   *   The snippet storage.
   */
  public function __construct(EntityStorageInterface $snippet_storage) {
    $this->snippetStorage = $snippet_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('snippet')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    /** @var \Drupal\snippet_manager\SnippetInterface[] $snippets */
    $snippets = $this->snippetStorage->loadMultiple();

    foreach ($snippets as $snippet_id => $snippet) {
      $layout = $snippet->get('layout');
      if ($snippet->status() && $layout['status']) {
        /** @var \Drupal\Core\Layout\LayoutDefinition $base_plugin_definition */
        $definition = clone $base_plugin_definition;
        $definition->setLabel($layout['label'] ?: $snippet->label());
        $definition->setRegions($snippet->getLayoutRegions());
        $definition->setDefaultRegion($layout['default_region']);
        $definition->setIconPath('images/layout.svg');
        $this->derivatives[$snippet_id] = $definition;
      }
    }

    return $this->derivatives;
  }

}
