<?php

namespace Drupal\snippet_manager\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Display\VariantManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General snippet form.
 *
 * @property \Drupal\snippet_manager\SnippetInterface $entity
 */
class GeneralForm extends EntityForm {

  /**
   * The variant manager.
   *
   * @var \Drupal\Core\Display\VariantManager
   */
  protected $variantManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $permissionHandler;

  /**
   * Constructs a snippet form object.
   *
   * @param \Drupal\Core\Display\VariantManager $variant_manager
   *   The variant manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler.
   */
  public function __construct(VariantManager $variant_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, PermissionHandlerInterface $permission_handler) {
    $this->variantManager = $variant_manager;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->permissionHandler = $permission_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.display_variant'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('user.permissions')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\snippet_manager\Entity\Snippet::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    // -- Page.
    $form['page'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Page'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#group' => 'additional_settings',
    ];

    $form['page']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable snippet page'),
      '#default_value' => $this->entity->get('page')['status'],
    ];

    $page_states = [
      'visible' => [
        ':input[name="page[status]"]' => ['checked' => TRUE],
      ],
    ];

    $form['page']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Leave empty to use snippet label.'),
      '#default_value' => $this->entity->get('page')['title'],
      '#states' => $page_states,
    ];

    $description_args = [
      '%placeholder_1' => '%',
      '%placeholder_2' => 'content/%',
      '%placeholder_3' => 'content/%node',
    ];
    $form['page']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('This page will be displayed by visiting this path on your site. You may use "%placeholder_1" in your URL to represent placeholders. For example, "%placeholder_2". If needed you can even load entities using named route parameters like "%placeholder_3".', $description_args),
      '#default_value' => $this->entity->get('page')['path'],
      '#states' => $page_states,
    ];

    $theme_options[''] = $this->t('- Default -');
    foreach ($this->themeHandler->listInfo() as $theme) {
      if ($theme->status && empty($theme->info['hidden'])) {
        $theme_options[$theme->getName()] = $theme->info['name'];
      }
    }

    $form['page']['theme'] = [
      '#type' => 'radios',
      '#title' => $this->t('Theme'),
      '#options' => $theme_options,
      '#default_value' => $this->entity->get('page')['theme'] ?: '',
      '#states' => $page_states,
    ];

    $variant_definitions = $this->variantManager->getDefinitions();
    $options = ['' => $this->t('- Default -')];
    foreach ($variant_definitions as $id => $definition) {
      $options[$id] = $definition['admin_label'];
    }
    asort($options);

    $form['page']['display_variant'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display variant'),
      '#options' => $options,
      '#default_value' => $this->entity->get('page')['display_variant'] ?: '',
      '#states' => $page_states,
    ];

    $form['page']['access']['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Access'),
      '#options' => [
        'all' => $this->t('- Do not limit -'),
        'permission' => $this->t('Permission'),
        'role' => $this->t('Role'),
      ],
      '#default_value' => $this->entity->get('page')['access']['type'],
      '#states' => $page_states,
    ];

    $options = ['' => $this->t('- Select permission -')];
    $permissions = $this->permissionHandler->getPermissions();
    foreach ($permissions as $permission => $permission_info) {
      $provider = $permission_info['provider'];
      $display_name = $this->moduleHandler->getName($provider);
      $options[$display_name][$permission] = strip_tags($permission_info['title']);
    }

    $form['page']['access']['permission'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Permission'),
      '#description' => $this->t('Only users with the selected permission flag will be able to access this snippet.'),
      '#default_value' => $this->entity->get('page')['access']['permission'],
      '#states' => [
        'visible' => $page_states['visible'] + [':input[name="page[access][type]"]' => ['value' => 'permission']],
      ],
    ];

    $form['page']['access']['role'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Role'),
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', user_role_names()),
      '#description' => $this->t('Only the checked roles will be able to access this page.'),
      '#default_value' => $this->entity->get('page')['access']['role'],
      '#states' => [
        'visible' => $page_states['visible'] + [':input[name="page[access][type]"]' => ['value' => 'role']],
      ],
    ];

    // -- Block.
    if ($this->moduleHandler->moduleExists('block')) {
      $form['block'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Block'),
        '#open' => FALSE,
        '#tree' => TRUE,
        '#group' => 'additional_settings',
      ];

      $form['block']['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable snippet block'),
        '#default_value' => $this->entity->get('block')['status'],
      ];

      $block_states = [
        'visible' => [
          ':input[name="block[status]"]' => ['checked' => TRUE],
        ],
      ];

      $form['block']['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Admin description'),
        '#description' => $this->t('Leave empty to use snippet label.'),
        '#default_value' => $this->entity->get('block')['name'],
        '#states' => $block_states,
      ];
    }

    $form['display_variant'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display variant'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#group' => 'additional_settings',
    ];

    $form['display_variant']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable display variant'),
      '#default_value' => $this->entity->get('display_variant')['status'],
    ];

    $display_variant_states = [
      'visible' => [
        ':input[name="display_variant[status]"]' => ['checked' => TRUE],
      ],
    ];

    $form['display_variant']['admin_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Admin label'),
      '#description' => $this->t('Leave empty to use snippet label.'),
      '#default_value' => $this->entity->get('display_variant')['admin_label'],
      '#states' => $display_variant_states,
    ];

    // -- Layout.
    if ($this->moduleHandler->moduleExists('layout_discovery')) {
      $form['layout'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Layout'),
        '#open' => FALSE,
        '#tree' => TRUE,
        '#group' => 'additional_settings',
      ];

      $form['layout']['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable snippet layout'),
        '#default_value' => $this->entity->get('layout')['status'],
      ];

      $layout_states = [
        'visible' => [
          ':input[name="layout[status]"]' => ['checked' => TRUE],
        ],
      ];

      $form['layout']['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#description' => $this->t('Leave empty to use snippet label.'),
        '#default_value' => $this->entity->get('layout')['label'],
        '#states' => $layout_states,
      ];

      $region_options = [];
      foreach ($this->entity->getLayoutRegions() as $region_name => $region) {
        $region_options[$region_name] = $region['label'];
      }

      $form['layout']['default_region'] = [
        '#type' => 'radios',
        '#title' => $this->t('Default region'),
        '#options' => $region_options,
        '#default_value' => $this->entity->get('layout')['default_region'] ?: NULL,
        '#access' => count($region_options) > 0,
        '#states' => $layout_states,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $page = $form_state->getValue('page');
    $access = $page['access'];

    // Make the role export more readable.
    $role = array_values(array_filter($access['role']));
    $form_state->setValue(['page', 'access', 'role'], $role);

    if ($page['status']) {
      $errors = $this->validatePath($page['path']);
      foreach ($errors as $error) {
        $form_state->setError($form['page']['path'], $error);
      }

      if ($access['type'] == 'permission' && !$access['permission']) {
        $form_state->setError($form['page']['access']['permission'], $this->t('You must select a permission if access type is "Permission".'));
      }

      if ($access['type'] == 'role' && count($role) == 0) {
        $form_state->setError($form['page']['access']['role'], $this->t('You must select at least one role if access type is "Role".'));
      }
    }

    // Remove trailing '/' and whitespaces from the path.
    $page['path'] = trim($page['path'], ' /');
    $form_state->setValue(['page', 'path'], $page['path']);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = $this->entity->save();

    $message_arguments = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Snippet %label has been created.', $message_arguments)
      : $this->t('Snippet %label has been updated.', $message_arguments);
    $this->messenger()->addStatus($message);

    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
  }

  /**
   * Validates the path of the page.
   *
   * @param string $path
   *   The path to validate.
   *
   * @return array
   *   A list of error messages.
   */
  protected function validatePath($path) {
    $errors = [];
    if (strpos($path, '%') === 0) {
      $errors[] = $this->t('"%" may not be used for the first segment of a path.');
    }

    $parsed_url = UrlHelper::parse($path);
    if (empty($parsed_url['path'])) {
      $errors[] = $this->t('Path is empty.');
    }

    if (!empty($parsed_url['query'])) {
      $errors[] = $this->t('No query allowed.');
    }

    if (!parse_url('internal:/' . $path)) {
      $errors[] = $this->t('Invalid path. Valid characters are alphanumerics as well as "-", ".", "_" and "~".');
    }

    $path_sections = explode('/', $path);
    // Symfony routing does not allow to use numeric placeholders.
    // @see \Symfony\Component\Routing\RouteCompiler
    $numeric_placeholders = array_filter($path_sections, function ($section) {
      return (preg_match('/^%(.*)/', $section, $matches)
        && is_numeric($matches[1]));
    });
    if (!empty($numeric_placeholders)) {
      $errors[] = $this->t('Numeric placeholders may not be used. Please use plain placeholders (%).');
    }
    return $errors;
  }

}
