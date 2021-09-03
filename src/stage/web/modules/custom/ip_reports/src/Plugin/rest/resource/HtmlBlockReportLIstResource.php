<?php

namespace Drupal\ip_reports\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get html_block_report list.
 *
 * @RestResource(
 *   id = "get_html_block_report_node_list",
 *   label = @Translation("Get HTML Blocks for Report list"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/html_block_report/list"
 *   }
 * )
 */
class HtmlBlockReportLIstResource extends ResourceBase {

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

  private $node_html_block_report_type = 'html_block_report';

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
      'type' => $this->node_html_block_report_type,
    ];

    $response = [
      'status' => TRUE,
      'html_blocks' => []
    ];

    $charts_nodes = \Drupal::entityTypeManager()
                           ->getStorage('node')
                           ->loadByProperties($values);

    foreach ($charts_nodes as $chart) {
      $response['html_blocks'][] = [
        'id'    => $chart->id(),
        'title' => $chart->getTitle(),
      ];
    }

    return (new ResourceResponse($response, 200))
      ->addCacheableDependency($this->without_cache);
  }

}

