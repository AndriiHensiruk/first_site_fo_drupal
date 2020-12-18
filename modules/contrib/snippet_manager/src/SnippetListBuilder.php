<?php

namespace Drupal\snippet_manager;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a listing of snippets.
 */
class SnippetListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['id'] = [
      'data' => $this->t('ID'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['status'] = $this->t('Status');
    $header['page'] = [
      'data' => $this->t('Page'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['block'] = [
      'data' => $this->t('Block'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['display_variant'] = [
      'data' => $this->t('Display variant'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['layout'] = [
      'data' => $this->t('Layout'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header += parent::buildHeader();
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\snippet_manager\SnippetInterface $entity */
    $row['name'] = $entity->toLink();
    $row['id'] = $entity->id();

    if ($entity->status()) {
      $row['status']['data'] = $this->t('Enabled');
      $row['status']['data-status'] = 'enabled';
    }
    else {
      $row['status']['data'] = $this->t('Disabled');
      $row['status']['class'] = ['sm-inactive'];
      $row['status']['data-status'] = 'disabled';
    }

    $page = $entity->get('page');
    if ($page['status']) {
      if (strpos($page['path'], '%') === FALSE) {
        $url = Url::fromUri('internal:/' . $page['path']);
        $row['page']['data'] = Link::fromTextAndUrl($page['path'], $url);
      }
      else {
        $row['page']['data'] = $page['path'];
      }
    }
    else {
      $row['page']['data'] = '';
    }
    $row['page']['data-page'] = (int) $page['status'];

    $block = $entity->get('block');
    $row['block']['data'] = $block['status'] ?
      $block['name'] ?: $entity->label() : '';
    $row['block']['data-block'] = (int) $block['status'];

    $display_variant = $entity->get('display_variant');
    $row['display_variant']['data'] = $display_variant['status'] ?
      $display_variant['admin_label'] ?: $entity->label() : '';
    $row['display_variant']['data-display-variant'] = (int) $display_variant['status'];

    $layout = $entity->get('layout');
    $row['layout']['data'] = $layout['status'] ?
      $layout['label'] ?: $entity->label() : '';
    $row['layout']['data-layout'] = (int) $layout['status'];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    $operations = parent::getDefaultOperations($entity);
    $operations['duplicate'] = [
      'title' => $this->t('Duplicate'),
      'url' => $entity->toUrl('duplicate-form'),
      'weight' => 100,
    ];

    if (isset($operations['enable'])) {
      $operations['enable']['weight'] = 150;
    }

    if (isset($operations['disable'])) {
      $operations['disable']['weight'] = 150;
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['filters'] = [
      '#type' => 'container',
      '#weight' => -10,
      '#attributes' => [
        'class' => ['sm-listing-filters'],
      ],
    ];

    $build['filters']['search'] = [
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#size' => 30,
      '#placeholder' => $this->t('Snippet name or ID'),
      '#attributes' => [
        'data-drupal-selector' => ['sm-snippet-search'],
        'autocomplete' => 'off',
      ],
    ];

    $build['filters']['usage'] = [
      '#type' => 'select',
      '#title' => $this->t('Usage'),
      '#options' => [
        '' => $this->t('- Any -'),
        'page' => $this->t('Page'),
        'block' => $this->t('Block'),
        'display_variant' => $this->t('Display variant'),
        'layout' => $this->t('Layout'),
        'none' => $this->t('None'),
      ],
      '#attributes' => [
        'data-drupal-selector' => ['sm-snippet-usage'],
      ],
    ];

    $build['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        '' => $this->t('- Any -'),
        'enabled' => $this->t('Enabled'),
        'disabled' => $this->t('Disabled'),
      ],
      '#attributes' => [
        'data-drupal-selector' => ['sm-snippet-status'],
      ],
    ];

    $build['filters']['reset'] = [
      '#type' => 'button',
      '#title' => $this->t('Usage'),
      '#value' => $this->t('Reset'),
      '#attributes' => [
        'data-drupal-selector' => ['sm-snippet-reset'],
      ],
    ];

    $build['table']['#attributes']['class'][] = 'sm-snippets-overview';
    $build['table']['#attributes']['data-drupal-selector'] = 'sm-snippet-list';
    $build['#attached']['library'][] = 'snippet_manager/listing';
    return $build;
  }

  /**
   * Generates a string representation for the given byte count.
   *
   * @see format_size()
   */
  public function formatSize($size) {
    return format_size($size);
  }

  /**
   * Returns label for a given format.
   */
  public function getFormatLabel($format) {
    $formats = filter_formats();
    return isset($formats[$format]) ? $formats[$format]->label() : NULL;
  }

}
