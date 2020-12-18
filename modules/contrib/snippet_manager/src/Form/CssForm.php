<?php

namespace Drupal\snippet_manager\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\snippet_manager\SnippetLibraryBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Snippet CSS form.
 *
 * @property \Drupal\snippet_manager\SnippetInterface $entity
 */
class CssForm extends EntityForm {

  /**
   * The library builder.
   *
   * @var \Drupal\snippet_manager\SnippetLibraryBuilder
   */
  protected $libraryBuilder;

  /**
   * The key/value Store to use for state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a snippet form object.
   *
   * @param \Drupal\snippet_manager\SnippetLibraryBuilder $library_builder
   *   The snippet library builder.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   */
  public function __construct(SnippetLibraryBuilder $library_builder, StateInterface $state) {
    $this->libraryBuilder = $library_builder;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('snippet_manager.snippet_library_builder'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $css = $this->entity->get('css');
    // BC layer.
    $css['preprocess'] = !empty($css['preprocess']);

    $form['css']['#tree'] = TRUE;

    $form['css']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $css['status'],
    ];

    $form['css']['preprocess'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preprocess'),
      '#default_value' => $css['preprocess'],
    ];

    $form['css']['group'] = [
      '#type' => 'select',
      '#title' => $this->t('Group'),
      '#default_value' => $css['group'],
      '#options' => [
        'base' => $this->t('Base'),
        'layout' => $this->t('Layout'),
        'component' => $this->t('Component'),
        'state' => $this->t('State'),
        'theme' => $this->t('Theme'),
      ],
    ];

    $form['css']['value'] = [
      '#title' => $this->t('CSS'),
      '#type' => 'codemirror',
      '#default_value' => $css['value'],
      '#codemirror' => [
        'mode' => 'text/css',
        'lineNumbers' => TRUE,
        'buttons' => [
          'undo',
          'redo',
          'enlarge',
          'shrink',
        ],
      ],
    ];

    $form['#attached']['library'][] = 'snippet_manager/editor';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = parent::actionsElement($form, $form_state);
    $element['delete']['#access'] = FALSE;

    $file_path = $this->libraryBuilder->getFilePath('css', $this->entity);
    if (file_exists(DRUPAL_ROOT . '/' . $file_path)) {
      $options['query'][$this->state->get('system.css_js_query_string') ?: '0'] = NULL;
      $element['open_file'] = [
        '#type' => 'link',
        '#title' => $this->t('Open file'),
        '#url' => Url::fromUri('base://' . $file_path, $options),
        '#attributes' => ['class' => 'button', 'target' => '_blank'],
        '#weight' => 5,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $message = $this->t('Snippet %label has been updated.', ['%label' => $this->entity->label()]);
    $this->messenger()->addStatus($message);
  }

}
