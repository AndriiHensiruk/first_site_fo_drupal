<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\snippet_manager\SnippetAwareInterface;
use Drupal\snippet_manager\SnippetAwareTrait;
use Drupal\snippet_manager\SnippetVariableBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides formatted text variable type.
 *
 * @SnippetVariable(
 *   id = "text",
 *   title = @Translation("Formatted text"),
 *   category = @Translation("Other"),
 * )
 */
class Text extends SnippetVariableBase implements SnippetAwareInterface, ContainerFactoryPluginInterface {

  use SnippetAwareTrait;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * Constructs the plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityRepositoryInterface $entity_repository, FileUsageInterface $file_usage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityRepository = $entity_repository;
    $this->fileUsage = $file_usage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.repository'),
      $container->get('file.usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['content'] = [
      '#title' => $this->t('Content'),
      '#type' => 'text_format',
      '#default_value' => $this->configuration['content']['value'],
      '#format' => $this->configuration['content']['format'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Records usage of files referenced by formatted text field.
   *
   * It is important to note that this only works for the plugin form
   * submissions. If the snippet is created programmatically or through
   * configuration import the files won't be tracked.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $content = $form_state->getValue('content');

    $original_uuids = self::parseFileUuids($this->configuration['content']['value']);
    $current_uuids = self::parseFileUuids($content['value']);

    $snippet_id = $this->getSnippet()->id();

    $new_uuids = array_diff($current_uuids, $original_uuids);
    foreach ($new_uuids as $uuid) {
      /** @var \Drupal\file\FileInterface $file */
      if ($file = $this->entityRepository->loadEntityByUuid('file', $uuid)) {
        $this->fileUsage->add($file, 'snippet_manager', 'snippet', $snippet_id);
      }
    }

    $outdated_uuids = array_diff($original_uuids, $current_uuids);
    foreach ($outdated_uuids as $uuid) {
      /** @var \Drupal\file\FileInterface $file */
      if ($file = $this->entityRepository->loadEntityByUuid('file', $uuid)) {
        $this->fileUsage->delete($file, 'snippet_manager', 'snippet', $snippet_id);
      }
    }

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration['content'] = [
      'value' => '',
      'format' => filter_default_format(),
    ];
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#type' => 'processed_text',
      '#text' => $this->configuration['content']['value'],
      '#format' => $this->configuration['content']['format'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $config_name = \Drupal::entityTypeManager()
      ->getStorage('filter_format')
      ->load($this->configuration['content']['format'])
      ->getConfigDependencyName();
    return ['config' => [$config_name]];
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete() {
    $uuids = self::parseFileUuids($this->configuration['content']['value']);
    $snippet_id = $this->getSnippet()->id();
    foreach ($uuids as $uuid) {
      /** @var \Drupal\file\FileInterface $file */
      if ($file = $this->entityRepository->loadEntityByUuid('file', $uuid)) {
        $this->fileUsage->delete($file, 'snippet_manager', 'snippet', $snippet_id);
      }
    }
  }

  /**
   * Parses an HTML snippet for linked files with data-entity-uuid attributes.
   *
   * @param string $text
   *   The partial (X)HTML snippet to load.
   *
   * @return array
   *   An array of all found UUIDs.
   */
  protected static function parseFileUuids($text) {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $uuids = [];
    foreach ($xpath->query('//*[@data-entity-type="file" and @data-entity-uuid]') as $node) {
      /** @var \DOMElement $node */
      $uuids[] = $node->getAttribute('data-entity-uuid');
    }
    return $uuids;
  }

}
