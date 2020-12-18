<?php

namespace Drupal\snippet_manager\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;
use Drupal\snippet_manager\Entity\Snippet as SnippetEntity;

/**
 * Defines the "Snippet" plugin.
 *
 * @CKEditorPlugin(
 *   id = "snippet_manager_snippet",
 *   label = @Translation("Snippet"),
 *   module = "snippet_manager"
 * )
 */
class Snippet extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'snippet_manager') . '/js/plugins/snippet/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $config['snippets'] = self::getSnippetOptions();

    $settings = $editor->getSettings();
    if (!empty($settings['plugins']['snippet_manager_snippet']['snippets'])) {
      foreach ($config['snippets'] as $key => $label) {
        if (!in_array($key, $settings['plugins']['snippet_manager_snippet']['snippets'])) {
          unset($config['snippets'][$key]);
        }
      }
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $module_path = drupal_get_path('module', 'snippet_manager');
    return [
      'snippet' => [
        'label' => $this->t('Snippet'),
        'image' => $module_path . '/js/plugins/snippet/icons/snippet.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\editor\Form\EditorImageDialog
   * @see editor_image_upload_settings_form()
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {

    // Defaults.
    $config = ['snippets' => []];
    $settings = $editor->getSettings();
    if (isset($settings['plugins']['snippet_manager_snippet'])) {
      $config = $settings['plugins']['snippet_manager_snippet'];
    }

    $form['snippets'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Snippets'),
      '#options' => self::getSnippetOptions(),
      '#default_value' => $config['snippets'],
      '#description' => $this->t('Select snippets that can be listed in the dropdown menu. If nothing selected all snippets will be available.'),
      '#element_validate' => [
        [$this, 'validateSnippets'],
      ],
    ];

    return $form;
  }

  /**
   * Gets snippet options.
   *
   * @return array
   *   An associative array of snippet labels keyed by snippet IDs.
   */
  protected static function getSnippetOptions() {
    $options = [];
    /** @var \Drupal\snippet_manager\SnippetInterface $snippet */
    foreach (SnippetEntity::loadMultiple() as $snippet) {
      $options[$snippet->id()] = $snippet->label();
    }
    return $options;
  }

  /**
   * Element validate handler for the "snippets" element in settingsForm().
   */
  public function validateSnippets(array &$element, FormStateInterface $form_state) {
    // Prepare allowed snippets for configuration export.
    $editor_values = $form_state->getValue('editor');
    $snippets = &$editor_values['settings']['plugins']['snippet_manager_snippet']['snippets'];
    $snippets = array_keys(array_filter($snippets));
    $form_state->setValue('editor', $editor_values);
  }

}
