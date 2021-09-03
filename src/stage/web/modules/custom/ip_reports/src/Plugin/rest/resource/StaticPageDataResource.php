<?php

namespace Drupal\ip_reports\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get static pages by id.
 *
 * @RestResource(
 *   id = "get_static_page_node_data",
 *   label = @Translation("Get static page by id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/static-page/{pid}"
 *   }
 * )
 */
class StaticPageDataResource extends ResourceBase {

  private $without_cache = [
    '#cache' => [
      'max-age' => 0,
    ],
  ];

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('ip_webform_api'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get($pid) {
    $static_page = current(\Drupal::entityTypeManager()->getStorage('node')
                                  ->loadByProperties([
                                    'type' => 'static_page',
                                    'nid'  => $pid,
                                    'status' => 1
                                  ])
    );

    if ($static_page instanceof Node) {
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');

      $body_full = $view_builder->view($static_page, 'full');
      $body_full = \Drupal::service('renderer')->render($body_full);

//      $body_teaser = $view_builder->view($static_page, 'teaser');
//      $body_teaser = \Drupal::service('renderer')->render($body_teaser);

      $response['static-page'] = [
        'id'    => $static_page->id(),
        'title' => $static_page->getTitle(),
        'body_full' => $body_full,
        //'body_teaser' => $body_teaser
        'format' => $static_page->body->format,
      ];

      return new Response(json_encode($response), 200);

//      return (new ResourceResponse($response, 200))
//        ->addCacheableDependency($this->without_cache);
    }
    else {
      $response['errors'][] = t('Static Page with id @pid is not found.', ['@pid' => $pid]);
      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }
}

