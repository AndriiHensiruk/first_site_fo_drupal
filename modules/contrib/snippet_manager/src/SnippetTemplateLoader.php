<?php

namespace Drupal\snippet_manager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Loads templates from the snippet storage.
 */
class SnippetTemplateLoader implements LoaderInterface {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new Snippet template loader instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    if (strpos($name, '@snippet/') !== FALSE) {
      $snippet_id = explode('/', $name)[1];
      $snippet = $this->entityTypeManager->getStorage('snippet')->load($snippet_id);
      return $snippet && $snippet->access('view');
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceContext($name) {
    $snippet_id = explode('/', $name)[1];

    /** @var \Drupal\snippet_manager\SnippetInterface $snippet */
    $snippet = $this->entityTypeManager->getStorage('snippet')->load($snippet_id);
    if (!$snippet || !$snippet->access('view')) {
      throw new LoaderError(sprintf('Could not load snippet "%s".', $snippet_id));
    }

    $template = $snippet->get('template');
    $contents = (string) check_markup($template['value'], $template['format']);
    return new Source($contents, $name);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove this once we drop support for Twig 1.
   */
  public function getSource($name) {
    $snippet_id = explode('/', $name)[1];

    /** @var \Drupal\snippet_manager\SnippetInterface $snippet */
    $snippet = $this->entityTypeManager->getStorage('snippet')->load($snippet_id);
    if (!$snippet || !$snippet->access('view')) {
      throw new LoaderError(sprintf('Could not load snippet "%s".', $snippet_id));
    }

    $template = $snippet->get('template');
    return (string) check_markup($template['value'], $template['format']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheKey($name) {
    return $name;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Add 'changed' property to Snippets.
   */
  public function isFresh($name, $time) {
    return TRUE;
  }

}
