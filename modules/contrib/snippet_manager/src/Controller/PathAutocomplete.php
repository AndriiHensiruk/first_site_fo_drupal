<?php

namespace Drupal\snippet_manager\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns autocomplete responses for snippet manager paths.
 */
class PathAutocomplete implements ContainerInjectionInterface {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(RouteProviderInterface $route_provider) {
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.route_provider')
    );
  }

  /**
   * Retrieves suggestions for path completion.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing autocomplete suggestions.
   */
  public function autocomplete(Request $request) {
    $matches = [];
    $typed_path = $request->query->get('q');

    if (is_string($typed_path)) {
      foreach ($this->routeProvider->getAllRoutes() as $route_name => $route) {
        $path = $route->getPath();
        if (stripos($path, $typed_path) !== FALSE && in_array('GET', $route->getMethods())) {
          // Exclude special routes like <current>.
          if ($route_name[0] != '<') {
            // Temporary key results by path to avoid duplicates.
            $matches[$path] = [
              'value' => $path,
              'label' => $path,
            ];
          }
        }
      }

      $matches = array_values($matches);
      usort($matches, function ($a, $b) {
        return strcmp($a['label'], $b['label']);
      });
      $matches = array_slice($matches, 0, 15);
    }

    return new JsonResponse($matches);
  }

}
