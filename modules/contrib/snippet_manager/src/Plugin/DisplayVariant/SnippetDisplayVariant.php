<?php

namespace Drupal\snippet_manager\Plugin\DisplayVariant;

use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a page display variant that render a snippet to as the main content.
 *
 * @PageDisplayVariant(
 *   id = "snippet_display_variant",
 *   admin_label = @Translation("Snippet page"),
 *   deriver = "Drupal\snippet_manager\Plugin\DisplayVariant\SnippetDisplayVariantDeriver",
 * )
 */
class SnippetDisplayVariant extends VariantBase implements PageVariantInterface, ContainerFactoryPluginInterface {

  /**
   * The render array representing the main content.
   *
   * @var array
   */
  protected $mainContent;

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   *
   * @var string|array
   */
  protected $title = '';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Snippet renderer.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * SnippetPageVariant constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->renderer = $renderer;
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
      $container->get('logger.channel.snippet_manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setMainContent(array $main_content) {
    $this->mainContent = $main_content;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    /** @var \Drupal\snippet_manager\SnippetInterface $snippet */
    $snippet = $this->entityTypeManager->getStorage('snippet')->load($this->getDerivativeId());
    if ($snippet) {

      /** @var \Drupal\snippet_manager\SnippetViewBuilder $view_builder */
      $view_builder = $this->entityTypeManager->getViewBuilder('snippet');

      $build['content'] = $view_builder->view($snippet);

      $variables = $snippet->get('variables');
      foreach ($variables as $variable_name => $variable) {
        if ($variable['plugin_id'] == 'display_variant:title') {
          $build['content']['snippet']['#context'][$variable_name] = $this->title;
        }
        elseif ($variable['plugin_id'] == 'display_variant:main_content') {
          $build['content']['snippet']['#context'][$variable_name] = $this->mainContent;
        }
      }

    }
    else {
      $this->logger->error('Could not load snippet: #%snippet', ['%snippet' => $this->configuration['snippet']]);
    }

    return $build;
  }

}
