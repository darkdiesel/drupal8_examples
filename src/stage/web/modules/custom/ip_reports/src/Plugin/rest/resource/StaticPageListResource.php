<?php

namespace Drupal\ip_reports\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get static pages list.
 *
 * @RestResource(
 *   id = "get_static_page_node_list",
 *   label = @Translation("Get avalable static page list"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/static-page/list"
 *   }
 * )
 */
class StaticPageListResource extends ResourceBase {

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
  public function get() {
    $values = [
      'status' => 1,
      'type' => 'static_page'
    ];

    $response = ['static-pages' => []];

    $static_pages_nodes = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->loadByProperties($values);

    foreach ($static_pages_nodes as $static_page) {
      $response['static-pages'][] = [
        'id' => $static_page->id(),
        'title' => $static_page->getTitle(),
      ];
    }

    return new ResourceResponse($response);
  }
}

