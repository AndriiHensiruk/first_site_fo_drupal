<?php

namespace Drupal\snippet_manager\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Url;
use Drupal\snippet_manager\SnippetVariablePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Snippet template form.
 *
 * @property \Drupal\snippet_manager\SnippetInterface $entity
 */
class TemplateForm extends EntityForm {

  /**
   * The variable manager.
   *
   * @var \Drupal\snippet_manager\SnippetVariablePluginManager
   */
  protected $variableManager;

  /**
   * The Twig service.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a snippet form object.
   *
   * @param \Drupal\snippet_manager\SnippetVariablePluginManager $variable_manager
   *   The variable manager.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   The Twig service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(SnippetVariablePluginManager $variable_manager, TwigEnvironment $twig, RendererInterface $renderer) {
    $this->variableManager = $variable_manager;
    $this->twig = $twig;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.snippet_variable'),
      $container->get('twig'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $template = $this->entity->get('template');

    $form['template'] = [
      '#title' => $this->t('Template'),
      '#type' => 'text_format',
      '#default_value' => $template['value'],
      '#rows' => 10,
      '#format' => $template['format'],
      '#editor' => FALSE,
      '#required' => TRUE,
      '#codemirror' => [
        'mode' => 'html_twig',
        'modeSelect' => [
          'html_twig' => $this->t('HTML/Twig'),
          'text/x-twig' => $this->t('Twig'),
          'text/html' => $this->t('HTML'),
          'text/javascript' => $this->t('JavaScript'),
          'text/css' => $this->t('CSS'),
        ],
        'lineNumbers' => TRUE,
      ],
    ];

    // -- Variables.
    $header = [
      $this->t('Name'),
      $this->t('Type'),
      $this->t('Plugin'),
      $this->t('Operations'),
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => [],
      '#empty' => $this->t('Variables are not configured yet.'),
      '#caption' => $this->t('Variables'),
      '#attributes' => ['class' => ['sm-variables']],
    ];

    foreach ($this->entity->getPluginCollection() as $variable_name => $plugin) {

      $route_parameters = [
        'snippet' => $this->entity->id(),
        'variable' => $variable_name,
      ];

      $operation_links = [];
      if ($plugin) {
        $operation_links['edit'] = [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('snippet_manager.variable_edit_form', $route_parameters),
        ];
        $operation_links += $plugin->getOperations();
      }
      // Allow deletion of broken variables.
      $operation_links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('snippet_manager.variable_delete_form', $route_parameters),
      ];

      $operation_data = [
        '#type' => 'operations',
        '#links' => $operation_links,
      ];

      $variable_url = Url::fromUserInput(
        '#',
        [
          'fragment' => 'snippet-edit-form',
          'attributes' => [
            'title' => $this->t('Insert to the textarea'),
            'data-drupal-selector' => "snippet-variable",
          ],
        ]
      );

      $row = &$form['table']['#rows'][$variable_name];
      $row[] = Link::fromTextAndUrl($variable_name, $variable_url);
      if ($plugin) {
        $row[] = $plugin->getType();
        $row[] = $plugin->getPluginId();
      }
      else {
        $row[] = '';
        $plugin_id = $this->entity->getVariable($variable_name)['plugin_id'];
        $row[] = $plugin_id . ' - ' . $this->t('missing');
        $this->messenger()->addWarning($this->t('The %plugin plugin does not exist.', ['%plugin' => $plugin_id]));
      }
      $row[] = ['data' => $operation_data];

    }

    $form['#attached']['library'][] = 'snippet_manager/editor';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $template = $form_state->getValue('template');
    try {
      $this->twig->renderInline(check_markup($template['value'], $template['format']));
    }
    catch (\Twig_Error $exception) {
      // For performance reasons the snippet template was rendered above without
      // variables. However sometimes it may lead to runtime Twig errors. To
      // confirm such errors we render the template again with fully initialized
      // variable plugins.
      try {
        $this->renderSnippet();
      }
      catch (\Twig_Error $exception) {
        $form_state->setError($form['template']['value'], $this->t('Twig error: %message', ['%message' => $exception->getRawMessage()]));
      }
    }
  }

  /**
   * Renders a snippet.
   */
  protected function renderSnippet() {
    $build = $this->entityTypeManager
      ->getViewBuilder('snippet')
      ->view($this->entity);
    $this->renderer->renderPlain($build);
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = parent::actionsElement($form, $form_state);
    $element['delete']['#access'] = FALSE;

    if (!$this->entity->isNew()) {
      $element['add_variable'] = [
        '#type' => 'link',
        '#title' => $this->t('Add variable'),
        '#url' => Url::fromRoute('snippet_manager.variable_add_form', ['snippet' => $this->entity->id()]),
        '#attributes' => ['class' => 'button'],
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
