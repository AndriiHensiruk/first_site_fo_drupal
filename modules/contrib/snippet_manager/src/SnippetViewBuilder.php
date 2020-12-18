<?php

namespace Drupal\snippet_manager;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View builder for snippets.
 */
class SnippetViewBuilder implements EntityViewBuilderInterface, EntityHandlerInterface {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new SnippetViewBuilder.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * Builds the render array for the provided snippet.
   *
   * @param \Drupal\Core\Entity\EntityInterface $snippet
   *   The snippet to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the snippet.
   * @param string $langcode
   *   (optional) For which language the entity should be rendered.
   * @param array $context
   *   (optional) Twig context.
   *
   * @return array
   *   A render array for the snippet.
   */
  public function view(EntityInterface $snippet, $view_mode = 'full', $langcode = NULL, array $context = []) {

    $method = $view_mode == 'source' ? 'ViewSource' : 'ViewDefault';
    $build = $this->{$method}($snippet, $context);

    $build['snippet']['#cache'] = [
      'keys' => ['entity_view', 'snippet', $snippet->id(), $view_mode],
      'tags' => Cache::mergeTags($this->getCacheTags(), $snippet->getCacheTags()),
    ];

    $this->moduleHandler->alter(['snippet_view'], $build, $snippet, $view_mode);

    return $build;
  }

  /**
   * Builds the render array for the source view mode.
   *
   * @param \Drupal\snippet_manager\SnippetInterface $snippet
   *   The snippet to render.
   *
   * @return array
   *   Render array to display HTML source code of the snippet.
   */
  protected function viewSource(SnippetInterface $snippet) {

    $build['content']['#type'] = 'textarea';
    $build['content']['#codemirror'] = [
      'readOnly' => TRUE,
      'toolbar' => FALSE,
      'lineNumbers' => TRUE,
      'foldGutter' => TRUE,
    ];

    Timer::start('snippet');

    $value = $this->view($snippet);
    // Render the snippet immediately to calculate render time.
    $build['content']['#value'] = (string) render($value);

    $render_time = Timer::read('snippet');

    $build['content']['#attributes']['data-drupal-selector'] = 'snippet-html-source';

    $build['render_time_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'snippet-render-time'],
    ];

    $build['render_time_wrapper']['render_time'] = [
      '#markup' => $this->t('Render time: %time ms', ['%time' => $render_time]),
    ];

    $build['#attached']['library'][] = 'snippet_manager/html_source';

    return $build;
  }

  /**
   * Builds render array.
   *
   * @param \Drupal\snippet_manager\SnippetInterface $snippet
   *   A snippet.
   * @param array $context
   *   Twig context.
   *
   * @return array
   *   Snippet content as render array.
   */
  protected function viewDefault(SnippetInterface $snippet, array $context) {

    $default_context = static::getDefaultContext();

    // The context is an array of processed Twig variables.
    $default_context += $this->moduleHandler->invokeAllDeprecated(
      'hook_snippet_context has been deprecated',
      'snippet_context',
      [$snippet]
    );

    foreach ($snippet->getPluginCollection() as $variable_name => $plugin) {
      $default_context[$variable_name] = $plugin ? $plugin->build() : '';
    }

    // Context passed to the view builder should take precedence.
    $context += $default_context;

    $this->moduleHandler->alterDeprecated(
      'hook_snippet_context_alter has been deprecated',
      'snippet_context',
      $context,
      $snippet
    );

    $template = $snippet->get('template');

    $build['snippet'] = [
      '#type' => 'inline_template',
      '#template' => check_markup($template['value'], $template['format']),
      '#context' => $context,
    ];

    if ($snippet->get('css')['status'] || $snippet->get('js')['status']) {
      $build['snippet']['#attached']['library'][] = 'snippet_manager/snippet_' . $snippet->id();
    }

    return $build;
  }

  /**
   * Returns default Twig context.
   *
   * @return array
   *   Twig context.
   */
  protected static function getDefaultContext() {

    $context = [];

    $theme = \Drupal::theme()->getActiveTheme();
    $context['theme'] = $theme->getName();
    $context['theme_directory'] = $theme->getPath();

    $context['base_path'] = base_path();
    $context['front_page'] = Url::fromRoute('<front>');
    $context['is_front'] = \Drupal::service('path.matcher')->isFrontPage();
    $context['language'] = \Drupal::languageManager()->getCurrentLanguage();

    $user = \Drupal::currentUser();
    $context['is_admin'] = $user->hasPermission('access administration pages');
    $context['logged_in'] = $user->isAuthenticated();

    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['snippet_view'];
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $entities = NULL) {
    // @see \Drupal\Core\Entity\EntityViewBuilder::resetCache()
    if (isset($entities)) {
      $tags = [];
      foreach ($entities as $entity) {
        $tags = Cache::mergeTags($tags, $entity->getCacheTags());
        $tags = Cache::mergeTags($tags, $entity->getEntityType()->getListCacheTags());
      }
      Cache::invalidateTags($tags);
    }
    else {
      Cache::invalidateTags($this->getCacheTags());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultiple(array $entities = [], $view_mode = 'full', $langcode = NULL) {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function viewFieldItem(FieldItemInterface $item, $display_options = []) {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function viewField(FieldItemListInterface $items, $display_options = []) {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode) {
    throw new \LogicException();
  }

}
