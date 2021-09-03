<?php

namespace Drupal\ip_reports\Plugin\rest\resource;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get chart data by chart id.
 *
 * @RestResource(
 *   id = "get_easychart_node_data",
 *   label = @Translation("Get chart data by chart id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/easychart/{cid}"
 *   }
 * )
 */
class ChartDataResource extends ResourceBase {

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

  private $node_chart_type = 'easychart';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user
  ) {
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
  public function get($cid) {
    $response = [
      'status' => TRUE,
    ];

    $query = \Drupal::request()->query;

    if ($query->has('revision')) {
      $revision = Xss::filter($query->get('revision'));

      $is_invalid_params = FALSE;

      if (is_numeric($revision)) {
        $chart_node = \Drupal::entityTypeManager()
                             ->getStorage('node')
                             ->loadRevision($revision);

        if (!($chart_node instanceof Node) || !(($chart_node instanceof Node) && $chart_node->getType() == $this->node_chart_type)) {
          $is_invalid_params = TRUE;
        }
      }
      else {
        $is_invalid_params = TRUE;
      }

      if ($is_invalid_params) {
        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t('Invalid params.', ['@cid' => $cid]);

        return (new ResourceResponse($response, 400))
          ->addCacheableDependency($this->without_cache);
      }
    }
    else {
      // if no revision in GET param load last revision by chart id
      $chart_node = current(\Drupal::entityTypeManager()->getStorage('node')
                                   ->loadByProperties([
                                     'type' => $this->node_chart_type,
                                     'nid'  => $cid,
                                   ])
      );

      if (!$chart_node instanceof Node) {
        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t('Chart with id @cid is not found.', ['@cid' => $cid]);

        return (new ResourceResponse($response, 404))
          ->addCacheableDependency($this->without_cache);
      }
    }

    $response = [
      'status' => TRUE,
      'chart'  => [],
    ];

    $chart = current($chart_node->get('easychart')->getValue());

    //      $chart = self::stripslashes_deep($chart);
    //      $response['chart']['csv'] = json_decode(stripslashes($chart['csv']));

    $response['chart'] = $chart;

    return (new ResourceResponse($response, 200))
      ->addCacheableDependency($this->without_cache);

  }

}
