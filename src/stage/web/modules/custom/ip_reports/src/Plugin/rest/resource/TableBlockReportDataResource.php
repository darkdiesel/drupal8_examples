<?php

namespace Drupal\ip_reports\Plugin\rest\resource;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a resource to get html_block_report data by id.
 *
 * @RestResource(
 *   id = "get_table_block_report_node_data",
 *   label = @Translation("Get Table Blocks for Report by id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/table_block_report/{brid}"
 *   }
 * )
 */
class TableBlockReportDataResource extends ResourceBase {

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

  private $node_table_block_report_type = 'table_block_report';

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
  public function get($brid) {
    $response = [
      'status' => TRUE,
    ];

    $query = \Drupal::request()->query;

    if ($query->has('revision')) {
      $revision = Xss::filter($query->get('revision'));

      $is_invalid_params = FALSE;

      if (is_numeric($revision)) {
        $table_block_report_node = \Drupal::entityTypeManager()
                             ->getStorage('node')
                             ->loadRevision($revision);

        if (!($table_block_report_node instanceof Node) || !(($table_block_report_node instanceof Node) && $table_block_report_node->getType() == $this->node_table_block_report_type)) {
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

        $response['status'] = FALSE;
        $response['errors'][] = t('Invalid params.');

        return new ResourceResponse($response);
      }
    }
    else {
      // if no revision in GET param load last revision by block id
      $table_block_report_node = current(\Drupal::entityTypeManager()->getStorage('node')
                                   ->loadByProperties([
                                     'type' => $this->node_table_block_report_type,
                                     'nid'  => $brid,
                                   ])
      );

      if (!$table_block_report_node instanceof Node) {
        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['status'] = FALSE;
        $response['errors'][] = t('Table Block Report with id @brid is not found.', ['@brid' => $brid]);

        return (new ResourceResponse($response, 404))
          ->addCacheableDependency($this->without_cache);
      }
    }

    $response['table_block'] = [];

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');

    $body_full = $view_builder->view($table_block_report_node, 'full');
    $body_full = \Drupal::service('renderer')->render($body_full);

    $response['table_block']['id'] = $brid;
    $response['table_block']['body'] = $body_full;

    return new Response(json_encode($response), 200);

    //return new ResourceResponse($response);
  }

}
