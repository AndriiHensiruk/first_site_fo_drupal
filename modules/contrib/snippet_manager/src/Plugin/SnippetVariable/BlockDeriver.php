<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves plugin definitions for all block types.
 */
class BlockDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Constructs BlockDeriver object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The lazy context repository service.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   Block plugin manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ContextRepositoryInterface $context_repository, BlockManagerInterface $block_manager) {
    $this->moduleHandler = $module_handler;
    $this->contextRepository = $context_repository;
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('module_handler'),
      $container->get('context.repository'),
      $container->get('plugin.manager.block')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    if (!$this->moduleHandler->moduleExists('block')) {
      return parent::getDerivativeDefinitions($base_plugin_definition);
    }

    $special_blocks = [
      // These two blocks can only be configured in display variant plugin.
      // @see \Drupal\block\Plugin\DisplayVariant\BlockPageVariant
      'page_title_block',
      'system_main_block',
      // Fallback plugin makes no sense here.
      'broken',
    ];

    $contexts = $this->contextRepository->getAvailableContexts();
    $definitions = $this->blockManager->getDefinitionsForContexts($contexts);
    foreach ($definitions as $block_id => $definition) {

      // Some definitions are not intended to be placed by the UI.
      $ui_hidden = !empty($definition['_block_ui_hidden']);

      // Do not allow nested snippet blocks to avoid recursion.
      $from_snippet = $definition['provider'] == 'snippet_manager';

      // Special block plugins.
      $special = in_array($block_id, $special_blocks);

      if (!$ui_hidden && !$from_snippet && !$special) {
        $this->derivatives[$block_id] = $base_plugin_definition;
        // Capitalize category to transform 'core' into 'Core'.
        $this->derivatives[$block_id]['title'] = ucfirst($definition['category']) . ' - ' . $definition['admin_label'];
      }

    }
    return $this->derivatives;
  }

}
