<?php

namespace Drupal\snippet_manager;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Snippet entity type.
 *
 * @see \Drupal\snippet_manager\Entity\Snippet.
 */
class SnippetAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\snippet_manager\SnippetInterface $entity */
    if ($operation == 'view' && $entity->status()) {
      return AccessResult::allowed()->addCacheableDependency($entity);
    }
    return parent::checkAccess($entity, $operation, $account);
  }

}
