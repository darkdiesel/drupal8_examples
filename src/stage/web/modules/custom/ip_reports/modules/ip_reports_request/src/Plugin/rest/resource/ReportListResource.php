<?php

namespace Drupal\ip_reports_request\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ip_reports_request\Controller\IpReportRequestCalculationServiceController;
use Drupal\ip_reports_request\Controller\IpReportRequestController;
use Drupal\ip_users\Controller\IpUserDataController;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ip_reports_request\Model\IpReportsRequestsModel;

/**
 * Provides a resource to get reports by selected organisation in session
 *
 * @RestResource(
 *   id = "get_report_list",
 *   label = @Translation("Get reports by Organisation"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/report/list"
 *   }
 * )
 */
class ReportListResource extends ResourceBase {

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
   * Responds to GET reports.
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get() {
    $response = [
      'status'  => TRUE,
      'reports' => [],
    ];

    $report_nodes = [];

    $organisation = IpUserDataController::getOrganisation($this->currentUser);

    if ($organisation) {
      // get reports requests list by organisation id
      // @TODO: Group by report_node_id
      $reports_request = IpReportsRequestsModel::getAllByOrganisationID($organisation);
    } else {
      // @TODO: will be removed when default organisation will be provided?
      $reports_request = IpReportsRequestsModel::getByUser($this->currentUser->id());
    }

    if ($reports_request && is_array($reports_request)) {
      $reportRequest = new IpReportRequestCalculationServiceController();
      $base_url      = $reportRequest->getBaseUrl();

      foreach ($reports_request as $report_request) {
        if (!isset($report_node[$report_request->report_node_id])) {
          /**
           * @var \Drupal\node\Entity\Node
           */
          $report_nodes[$report_request->report_node_id] = current(\Drupal::entityTypeManager()
                                                                          ->getStorage('node')
                                                                          ->loadByProperties([
                                                                            'type'                  => 'report',
                                                                            'nid'                   => $report_request->report_node_id,
                                                                            'field_report_settings' => 'show',
                                                                          ])
          );
        }

        if (!$report_nodes[$report_request->report_node_id]) {
          continue;
        }

        $can_download_pdf  = $report_nodes[$report_request->report_node_id]->get('field_download_pdf')->value == 'allow';
        $can_download_docx = $report_nodes[$report_request->report_node_id]->get('field_download_docx')->value == 'allow';

        $show_print_btn = (bool) $report_nodes[$report_request->report_node_id]->get('field_print_button')->value;

        $response_report = [
          'id'           => $report_request->report_id,
          'structure_id' => $report_nodes[$report_request->report_node_id]->id(),
          'title'        => $report_nodes[$report_request->report_node_id]->getTitle(),
          'request'      => [
            'status'     => $report_request->status,
            'result'     => [],
            'print_url'  => NULL,
            'created_at' => date('Y-m-d H:i:s', $report_request->created_at),
            'updated_at' => date('Y-m-d H:i:s', $report_request->updated_at),
          ],
          'permissions'  => [
            'can_download_pdf'  => $can_download_pdf,
            'can_download_docx' => $can_download_docx,
            'show_print_btn'    => $show_print_btn,
          ],
        ];

        //@TODO: move base url to common method
        if ($report_request->result) {
          $result = unserialize($report_request->result, ['allowed_classes' => FALSE]);

          if (is_array($result) && count($result)) {
            if (isset($result[IpReportRequestController::REPORT_HTML])) {
              $response_report['request']['result'][IpReportRequestController::REPORT_HTML] = $base_url . $result[IpReportRequestController::REPORT_HTML];
            }

            if (isset($result[IpReportRequestController::REPORT_PDF]) && $can_download_pdf) {
              $response_report['request']['result'][IpReportRequestController::REPORT_PDF] = $base_url . $result[IpReportRequestController::REPORT_PDF];
            }

            if (isset($result[IpReportRequestController::REPORT_DOCX]) && $can_download_docx) {
              $response_report['request']['result'][IpReportRequestController::REPORT_DOCX] = $base_url . $result[IpReportRequestController::REPORT_DOCX];
            }

            if ($show_print_btn && isset($result[IpReportRequestController::REPORT_PDF])) {
              $response_report['request']['print_url'] = $base_url . $result[IpReportRequestController::REPORT_PDF] . '&download=0';
            }
          }
        }

        $response['reports'][] = $response_report;
      }
    }

    return (new ResourceResponse($response, 200))
      ->addCacheableDependency($this->without_cache);
  }

}
