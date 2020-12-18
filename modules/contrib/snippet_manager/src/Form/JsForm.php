<?php

namespace Drupal\snippet_manager\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\snippet_manager\SnippetLibraryBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Snippet JS form.
 *
 * @property \Drupal\snippet_manager\SnippetInterface $entity
 */
class JsForm extends EntityForm {

  /**
   * The key/value store to use for state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The library builder.
   *
   * @var \Drupal\snippet_manager\SnippetLibraryBuilder
   */
  protected $libraryBuilder;

  /**
   * Constructs a snippet form object.
   *
   * @param \Drupal\snippet_manager\SnippetLibraryBuilder $library_builder
   *   The snippet library builder.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   */
  public function __construct(SnippetLibraryBuilder $library_builder, StateInterface $state) {
    $this->state = $state;
    $this->libraryBuilder = $library_builder;
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

    $js = $this->entity->get('js');
    // BC layer.
    $js['preprocess'] = !empty($js['preprocess']);

    $form['js']['#tree'] = TRUE;

    $form['js']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $js['status'],
    ];

    $form['js']['preprocess'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preprocess'),
      '#default_value' => $js['preprocess'],
    ];

    $form['js']['value'] = [
      '#title' => $this->t('JavaScript'),
      '#type' => 'codemirror',
      '#default_value' => $js['value'],
      '#codemirror' => [
        'mode' => 'text/javascript',
        'lineNumbers' => TRUE,
        'buttons' => [
          'undo',
          'redo',
          'enlarge',
          'shrink',
        ],
      ],
    ];

    $form['js']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $js['status'],
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

    $file_path = $this->libraryBuilder->getFilePath('js', $this->entity);
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
