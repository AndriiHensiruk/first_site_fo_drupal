<?php

namespace Drupal\snippet_manager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use RuntimeException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension.
 */
class SnippetManagerTwigExtension extends AbstractExtension {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SnippetManagerRouteSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('snippet', function ($snippet_id, array $context = []) {
        $snippet = $this->entityTypeManager->getStorage('snippet')->load($snippet_id);
        if (!$snippet || !$snippet->access('view')) {
          throw new RuntimeException(sprintf('Could not load snippet %s.', $snippet_id));
        }
        /** @var \Drupal\snippet_manager\SnippetViewBuilder $view_builder */
        $view_builder = $this->entityTypeManager->getViewBuilder('snippet');
        return $view_builder->view($snippet, 'full', NULL, $context);
      }),
    ];
  }

}
