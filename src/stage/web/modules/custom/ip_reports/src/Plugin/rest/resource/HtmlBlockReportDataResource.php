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
 *   id = "get_html_block_report_node_data",
 *   label = @Translation("Get HTML Blocks for Report by id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/html_block_report/{brid}"
 *   }
 * )
 */
class HtmlBlockReportDataResource extends ResourceBase {

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
        $html_block_report_node = \Drupal::entityTypeManager()
                             ->getStorage('node')
                             ->loadRevision($revision);

        if (!($html_block_report_node instanceof Node) || !(($html_block_report_node instanceof Node) && $html_block_report_node->getType() == $this->node_html_block_report_type)) {
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

        return (new ResourceResponse($response, 400))
          ->addCacheableDependency($this->without_cache);
      }
    }
    else {
      // if no revision in GET param load last revision by block id
      $html_block_report_node = current(\Drupal::entityTypeManager()->getStorage('node')
                                   ->loadByProperties([
                                     'type' => $this->node_html_block_report_type,
                                     'nid'  => $brid,
                                   ])
      );

      if (!$html_block_report_node instanceof Node) {
        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['status'] = FALSE;
        $response['errors'][] = t('Html Block Report with id @brid is not found.', ['@brid' => $brid]);

        return new ResourceResponse($response);
      }
    }


    $response['html_block'] = [];

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');

    $body_full = $view_builder->view($html_block_report_node, 'full');
    $body_full = \Drupal::service('renderer')->render($body_full);

    $response['html_block']['id'] = $brid;
    $response['html_block']['body'] = $body_full;

    return new Response(json_encode($response), 200);

    //return new ResourceResponse($response);
  }
}
