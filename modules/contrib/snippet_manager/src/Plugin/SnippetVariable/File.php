<?php

namespace Drupal\snippet_manager\Plugin\SnippetVariable;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\snippet_manager\SnippetAwareInterface;
use Drupal\snippet_manager\SnippetAwareTrait;
use Drupal\snippet_manager\SnippetVariableBase;

/**
 * Provides file variable type.
 *
 * This plugin is serialized on ajax form submit, so that we cannot save
 * dependency as class properties.
 *
 * @SnippetVariable(
 *   id = "file",
 *   title = @Translation("File"),
 *   category = @Translation("Other"),
 * )
 */
class File extends SnippetVariableBase implements SnippetAwareInterface {

  use SnippetAwareTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    // This form is intended for snippet administrators who by definition are
    // trusted. So that no need to limit the list of allowed file extensions.
    // Unfortunatly at the time being it is not possible to allow uploading
    // files with any file extension so we just expand the list to allow most
    // needed file types.
    // @see https://www.drupal.org/project/drupal/issues/997900
    $allowed_extensions = 'jpg jpeg gif png apng webp mov avi mp3 mp4 svg svgz woff woff2 txt doc docx xls xlsx pdf ppt pps odt ods odp rar zip gz tgz';

    $form['file'] = [
      '#title' => 'File',
      '#type' => 'managed_file',
      '#upload_location' => 'public://snippet',
      '#required' => TRUE,
      '#upload_validators'  => [
        'file_validate_extensions' => [$allowed_extensions],
      ],
      '#description' => $this->t('Allowed types: @extensions.', ['@extensions' => $allowed_extensions]),
    ];

    if ($this->configuration['file']) {
      if ($file = $this->getFile()) {
        $form['file']['#default_value'] = [$file->id()];
      }
    }

    $form['format'] = [
      '#title' => $this->t('Format'),
      '#type' => 'radios',
      '#options' => [
        'generic' => $this->t('Generic'),
        'url' => $this->t('URL'),
      ],
      '#default_value' => $this->configuration['format'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $origin_file = $this->getFile();

    /** @var \Drupal\file\FileInterface $new_file */
    $new_file = \Drupal::entityTypeManager()->getStorage('file')->load($values['file'][0]);

    // Update usage if the file has been changed.
    if (!$origin_file || $origin_file->uuid() != $new_file->uuid()) {
      $this->addUsage($new_file);
      $origin_file && $this->deleteUsage($origin_file);
    }

    $this->configuration = [
      'file' => $new_file->uuid(),
      'format' => $values['format'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'file' => NULL,
      'format' => 'generic',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [];

    if ($this->configuration['file']) {
      if ($file = $this->getFile()) {
        if ($this->configuration['format'] == 'generic') {
          $build['file'] = [
            '#theme' => 'file_link',
            '#file' => $file,
          ];
        }
        else {
          $build['file'] = [
            '#markup' => file_create_url($file->getFileUri()),
          ];
        }
        $build['file']['#cache']['tags'] = $file->getCacheTags();

      }
    }

    return $build;
  }

  /**
   * Returns file entity.
   *
   * @return \Drupal\file\FileInterface
   *   The file.
   */
  protected function getFile() {
    if ($this->configuration['file']) {
      return \Drupal::service('entity.repository')->loadEntityByUuid('file', $this->configuration['file']);
    }
  }

  /**
   * Records that the snippet is using a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   A file entity.
   */
  protected function addUsage(FileInterface $file) {
    \Drupal::service('file.usage')
      ->add($file, 'snippet_manager', 'snippet', $this->getSnippet()->id());
  }

  /**
   * Removes a record to indicate that a the snippet is no longer using a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   A file entity.
   */
  protected function deleteUsage(FileInterface $file) {
    \Drupal::service('file.usage')
      ->delete($file, 'snippet_manager', 'snippet', $this->getSnippet()->id());
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = [];
    if ($file = $this->getFile()) {
      $dependencies['content'][] = $file->getConfigDependencyName();
    }
    $dependencies['module'][] = 'file';
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete() {
    // The file may not be set if variable edit form was not submitted.
    if ($file = $this->getFile()) {
      $this->deleteUsage($file);
    }
  }

}
