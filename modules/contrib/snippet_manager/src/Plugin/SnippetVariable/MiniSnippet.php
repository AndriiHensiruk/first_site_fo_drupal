<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\Core\Form\FormStateInterface;
use Drupal\snippet_manager\SnippetVariableBase;

/**
 * Provides formatted text variable type.
 *
 * @SnippetVariable(
 *   id = "mini_snippet",
 *   title = @Translation("Mini snippet"),
 *   category = @Translation("Other"),
 * )
 */
class MiniSnippet extends SnippetVariableBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['template'] = [
      '#title' => $this->t('Template'),
      '#type' => 'codemirror',
      '#default_value' => $this->configuration['template'],
      '#required' => TRUE,
      '#codemirror' => [
        'mode' => 'html_twig',
        'buttons' => [
          'bold',
          'italic',
          'underline',
          'strike-through',
          'list-numbered',
          'list-bullet',
          'undo',
          'redo',
          'clear-formatting',
          'enlarge',
          'shrink',
        ],
        'modeSelect' => [
          'html_twig' => $this->t('HTML/Twig'),
          'text/x-twig' => $this->t('Twig'),
          'text/html' => $this->t('HTML'),
          'text/javascript' => $this->t('JavaScript'),
          'text/css' => $this->t('CSS'),
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['template' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#type' => 'inline_template',
      '#template' => $this->configuration['template'],
      '#context' => [],
    ];
  }

}
