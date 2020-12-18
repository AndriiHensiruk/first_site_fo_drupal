<?php

namespace Drupal\snippet_manager\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\snippet_manager\SnippetInterface;
use Drupal\snippet_manager\SnippetVariableCollection;

/**
 * Defines snippet entity type.
 *
 * @ConfigEntityType(
 *   id = "snippet",
 *   label = @Translation("Snippet"),
 *   handlers = {
 *     "view_builder" = "Drupal\snippet_manager\SnippetViewBuilder",
 *     "list_builder" = "Drupal\snippet_manager\SnippetListBuilder",
 *     "access" = "Drupal\snippet_manager\SnippetAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\snippet_manager\Form\GeneralForm",
 *       "edit" = "Drupal\snippet_manager\Form\GeneralForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *       "duplicate" = "Drupal\snippet_manager\Form\DuplicateForm",
 *       "variable_add" = "Drupal\snippet_manager\Form\VariableAddForm",
 *       "variable_edit" = "Drupal\snippet_manager\Form\VariableEditForm",
 *       "variable_delete" = "Drupal\snippet_manager\Form\VariableDeleteForm",
 *       "template_edit" = "Drupal\snippet_manager\Form\TemplateForm",
 *       "css_edit" = "Drupal\snippet_manager\Form\CssForm",
 *       "js_edit" = "Drupal\snippet_manager\Form\JsForm"
 *     }
 *   },
 *   config_prefix = "snippet",
 *   admin_permission = "administer snippets",
 *   links = {
 *     "collection" = "/admin/structure/snippet",
 *     "canonical" = "/admin/structure/snippet/{snippet}",
 *     "source" = "/admin/structure/snippet/{snippet}/source",
 *     "add-form" = "/admin/structure/snippet/add",
 *     "edit-form" = "/admin/structure/snippet/{snippet}/edit",
 *     "template-edit-form" = "/admin/structure/snippet/{snippet}/edit/template",
 *     "delete-form" = "/admin/structure/snippet/{snippet}/delete",
 *     "duplicate-form" = "/admin/structure/snippet/{snippet}/duplicate",
 *     "enable" = "/admin/structure/snippet/{snippet}/enable",
 *     "disable" = "/admin/structure/snippet/{snippet}/disable"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "page",
 *     "block",
 *     "display_variant",
 *     "layout",
 *     "template",
 *     "css",
 *     "js",
 *     "variables",
 *   }
 * )
 *
 * @property \Drupal\snippet_manager\SnippetInterface $original;
 */
class Snippet extends ConfigEntityBase implements SnippetInterface {

  /**
   * Snippet page settings.
   *
   * @var array
   */
  protected $page = [
    'status' => FALSE,
    'title' => NULL,
    'path' => NULL,
    'display_variant' => NULL,
    'theme' => NULL,
    'access' => [
      'type' => 'all',
      'permission' => NULL,
      'role' => [],
    ],
  ];

  /**
   * Snippet block settings.
   *
   * @var array
   */
  protected $block = [
    'status' => FALSE,
    'name' => NULL,
  ];

  /**
   * Display variant settings.
   *
   * @var array
   *
   * @todo Turn it into camel case.
   *
   * @see https://www.drupal.org/node/2411967
   */
  protected $display_variant = [
    'status' => FALSE,
    'admin_label' => NULL,
  ];

  /**
   * Snippet layout settings.
   *
   * @var array
   */
  protected $layout = [
    'status' => FALSE,
    'label' => NULL,
    'default_region' => NULL,
  ];

  /**
   * Snippet template.
   *
   * @var array
   */
  protected $template = [
    'value' => NULL,
    'format' => NULL,
  ];

  /**
   * Snippet variables.
   *
   * @var array
   */
  protected $variables = [];

  /**
   * Snippet CSS.
   *
   * @var array
   */
  protected $css = [
    'status' => FALSE,
    'preprocess' => TRUE,
    'value' => NULL,
    'group' => 'component',
  ];

  /**
   * Snippet JavaScript.
   *
   * @var array
   */
  protected $js = [
    'status' => FALSE,
    'preprocess' => TRUE,
    'value' => NULL,
  ];

  /**
   * Snippet variable plugin manager.
   *
   * @var \Drupal\snippet_manager\SnippetVariablePluginManager
   */
  protected $variableManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->template['format'] = $this->template['format'] ?: self::getDefaultFormat();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $filter_storage = \Drupal::entityTypeManager()->getStorage('filter_format');

    $filter_format = $filter_storage->load($this->template['format']);
    $this->addDependency('config', $filter_format->getConfigDependencyName());

    $page = $this->get('page');
    if ($page['status'] && $page['theme']) {
      $this->addDependency('theme', $page['theme']);
    }

    foreach ($this->getPluginCollection() as $plugin) {
      $plugin && $this->calculatePluginDependencies($plugin);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // Sort variables by name to make their listing and configuration export
    // more consistent.
    ksort($this->variables);
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    $original = isset($this->original) ? $this->original : NULL;
    $status_changed = !$original || $this->status() != $this->original->status();

    // Rebuild the router if this is a new snippet, or its page settings has
    // been updated, or its status has been changed.
    if ($status_changed || $this->get('page') != $original->get('page')) {
      \Drupal::service('router.builder')->setRebuildNeeded();
    }

    if ($status_changed || $this->get('block') != $original->get('block')) {
      \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
    }

    if (\Drupal::moduleHandler()->moduleExists('layout_discovery')) {
      $layout_status_changed = !$original || $this->get('layout')['status'] != $original->get('layout')['status'];
      if ($status_changed || $layout_status_changed || $this->get('layout')['status']) {
        \Drupal::service('plugin.manager.core.layout')->clearCachedDefinitions();
      }
    }

    if ($status_changed || $this->get('display_variant') != $original->get('display_variant')) {
      \Drupal::service('plugin.manager.display_variant')->clearCachedDefinitions();
    }

    // Update attached library.
    \Drupal::service('snippet_manager.snippet_library_builder')->updateAssets($this, $original);
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    /** @var \Drupal\snippet_manager\SnippetInterface[] $entities */
    parent::preDelete($storage, $entities);
    foreach ($entities as $entity) {
      if ($entity->get('page')['status']) {
        \Drupal::service('router.builder')->setRebuildNeeded();
      }
      if ($entity->get('block')['status']) {
        \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
      }
      if ($entity->get('layout')['status']) {
        \Drupal::service('plugin.manager.core.layout')->clearCachedDefinitions();
      }
      foreach ($entity->getPluginCollection() as $plugin) {
        $plugin && $plugin->preDelete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getVariable($name) {
    return isset($this->variables[$name]) ? $this->variables[$name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setVariable($name, $variable) {
    $this->variables[$name] = $variable;
  }

  /**
   * {@inheritdoc}
   */
  public function removeVariable($name) {
    unset($this->variables[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function variableExists($name) {
    return isset($this->variables[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutRegions() {
    $regions = [];
    foreach ($this->variables as $variable_name => $variable) {
      if ($variable['plugin_id'] == 'layout_region') {
        $regions[$variable_name] = $variable['configuration'];
        $regions[$variable_name]['label'] = $regions[$variable_name]['label'] ?: $variable_name;
      }
    }
    uasort($regions, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    return $regions;
  }

  /**
   * Returns the ID of default filter format.
   */
  protected static function getDefaultFormat() {
    // Full HTML is the most suitable format for snippets.
    $formats = filter_formats(\Drupal::currentUser());
    return isset($formats['full_html']) ? 'full_html' : filter_default_format();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollection() {
    return new SnippetVariableCollection($this);
  }

}
