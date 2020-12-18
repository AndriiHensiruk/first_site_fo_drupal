<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\snippet_manager\SnippetVariableBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block variable type.
 *
 * @SnippetVariable(
 *   id = "block",
 *   category = @Translation("Block"),
 *   deriver = "\Drupal\snippet_manager\Plugin\SnippetVariable\BlockDeriver",
 * )
 */
class Block extends SnippetVariableBase implements ContainerFactoryPluginInterface {

  use PluginDependencyTrait;
  use ContextAwarePluginAssignmentTrait;

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The plugin form manager.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Creates plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   Block plugin manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_manager
   *   The plugin form manager.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The lazy context repository service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, BlockManagerInterface $block_manager, AccountInterface $account, PluginFormFactoryInterface $plugin_form_manager, ContextRepositoryInterface $context_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->blockManager = $block_manager;
    $this->account = $account;
    $this->pluginFormFactory = $plugin_form_manager;
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('plugin.manager.block'),
      $container->get('current_user'),
      $container->get('plugin_form.factory'),
      $container->get('context.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    // System branding block relies on this value to load theme settings.
    // @see \Drupal\system\Plugin\Block\SystemBrandingBlock::blockForm().
    $theme = $this->configFactory->get('system.theme')->get('default');
    $form_state->set('block_theme', $theme);

    /** @var \Drupal\Core\Plugin\ContextAwarePluginInterface $block_plugin */
    $block_plugin = $this->getBlockPlugin();

    $plugin_form = $block_plugin instanceof PluginWithFormsInterface ?
      $this->pluginFormFactory->createInstance($block_plugin, 'configure') : $block_plugin;

    $form = $plugin_form->buildConfigurationForm($form, $form_state);

    // Add context mapping UI form element.
    $contexts = $this->contextRepository->getAvailableContexts();
    $form['context_mapping'] = $this->addContextAssignmentElement($block_plugin, $contexts);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->getBlockPlugin()->blockValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

    $block_plugin = $this->getBlockPlugin();
    $block_plugin->blockSubmit($form, $form_state);
    $this->configuration = $block_plugin->getConfiguration();

    unset($this->configuration['id'], $this->configuration['provider']);

    if ($context_mapping = $form_state->getValue('context_mapping')) {
      $this->configuration['context_mapping'] = $context_mapping;
    }

    $this->configuration['label'] = $form_state->getValue('label');
    $this->configuration['label_display'] = $form_state->getValue('label_display');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'settings' => [
        'label' => '',
        'label_display' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [];

    $block_plugin = $this->getBlockPlugin();

    // Inject runtime contexts.
    if ($block_plugin instanceof ContextAwarePluginInterface) {
      $contexts = $this->contextRepository->getRuntimeContexts($block_plugin->getContextMapping());
      $this->contextHandler()->applyContextMapping($block_plugin, $contexts);
    }

    $access = $block_plugin->access($this->account, TRUE);
    if ($access->isAllowed()) {
      $build['content'] = $block_plugin->build();
      if (!Element::isEmpty($build['content'])) {
        $build += [
          '#theme' => 'block',
          '#attributes' => [],
          '#contextual_links' => [],
          '#configuration' => $block_plugin->getConfiguration(),
          '#plugin_id' => $block_plugin->getPluginId(),
          '#base_plugin_id' => $block_plugin->getBaseId(),
          '#derivative_plugin_id' => $block_plugin->getDerivativeId(),
        ];
      }
    }

    CacheableMetadata::createFromObject($access)->applyTo($build);
    return $build;
  }

  /**
   * Creates block plugin instance.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   Block plugin instance.
   */
  protected function getBlockPlugin() {
    return $this->blockManager
      ->createInstance($this->getDerivativeId(), $this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = $this->getPluginDependencies($this->getBlockPlugin());
    $dependencies['module'][] = 'block';
    return $dependencies;
  }

}
