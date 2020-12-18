<?php

namespace Drupal\snippet_manager\Plugin\DisplayVariant;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Registers display variants for snippet config entity.
 */
class SnippetDisplayVariantDeriver extends DeriverBase implements ContainerDeriverInterface {

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
      $config = $snippet->get('display_variant');
      if ($snippet->status() && $config['status']) {
        $this->derivatives[$snippet_id] = $base_plugin_definition;
        $this->derivatives[$snippet_id]['admin_label'] = $config['admin_label'] ?: $snippet->label();
      }
    }

    return $this->derivatives;
  }

}
