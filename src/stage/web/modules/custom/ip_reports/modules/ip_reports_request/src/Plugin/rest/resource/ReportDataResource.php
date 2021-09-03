<?php

namespace Drupal\ip_reports_request\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ip_reports_request\Controller\IpReportRequestCalculationServiceController;
use Drupal\ip_users\Controller\IpUserDataController;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ip_reports_request\Model\IpReportsRequestsModel;
use Drupal\ip_reports_request\Controller\IpReportRequestController;

/**
 * Provides a resource to get report data
 *
 * @RestResource(
 *   id = "get_report_data",
 *   label = @Translation("Get report data"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/report/{rid}"
 *   }
 * )
 */
class ReportDataResource extends ResourceBase {

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
   * Responds to GET requests.
   *
   * @param  $rid
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($rid) {
    $response = [
      'status' => TRUE,
      'report' => [],
    ];

    //    $organisation = IpUserDataController::getOrganisation($this->currentUser);
    //
    //    // if organisation not saved in session send error
    //    if (!$organisation) {
    //      if (!isset($response['errors'])) {
    //        $response['errors'] = [];
    //      }
    //
    //      $response['status'] = FALSE;
    //
    //      $response['errors'][] = t('Organisation not selected.');
    //
    //      return (new ResourceResponse($response, 400))
    //        ->addCacheableDependency($this->without_cache);
    //    }

    // get reports requests list by organisation id
    // @TODO: Group by report_node_id
    $report_request = IpReportsRequestsModel::get($rid);

    if ($report_request) {
      $response = ['status' => TRUE];

      $calculationService = new IpReportRequestCalculationServiceController();
      $base_url      = $calculationService->getBaseUrl();

      $report_node = current(\Drupal::entityTypeManager()
                                    ->getStorage('node')
                                    ->loadByProperties([
                                      'type' => 'report',
                                      'nid'  => $report_request->report_node_id,
                                    ]));

      $can_download_pdf  = $report_node->get('field_download_pdf')->value == 'allow';
      $can_download_docx = $report_node->get('field_download_docx')->value == 'allow';

      $show_print_btn = (bool)$report_node->get('field_print_button')->value;

      $response_report = [
        'id'           => $report_request->report_id,
        'structure_id' => $report_node->id(),
        'title'        => $report_node->getTitle(),
        'request'      => [
          'status'     => $report_request->status,
          'result'     => [],
          'print_url'     => NULL,
          'created_at' => date('Y-m-d H:i:s', $report_request->created_at),
          'updated_at' => date('Y-m-d H:i:s', $report_request->updated_at),
        ],
        'permissions'  => [
          'can_download_pdf'  => $can_download_pdf,
          'can_download_docx' => $can_download_docx,
          'show_print_btn' => $show_print_btn,
        ],
      ];

      if ($report_request->result) {
        $result = unserialize($report_request->result, ['allowed_classes' => FALSE]);

        //@TODO: move base url to common method
        if (is_array($result) && count($result)) {
          if (isset($result[IpReportRequestController::REPORT_HTML])) {
            $response_report['request']['result'][IpReportRequestController::REPORT_HTML] = $base_url.$result[IpReportRequestController::REPORT_HTML];
          }

          if (isset($result[IpReportRequestController::REPORT_PDF]) && $can_download_pdf) {
            $response_report['request']['result'][IpReportRequestController::REPORT_PDF] = $base_url.$result[IpReportRequestController::REPORT_PDF];
          }

          if (isset($result[IpReportRequestController::REPORT_DOCX]) && $can_download_docx) {
            $response_report['request']['result'][IpReportRequestController::REPORT_DOCX] = $base_url.$result[IpReportRequestController::REPORT_DOCX];
          }

          if ($show_print_btn && isset($result[IpReportRequestController::REPORT_PDF])) {
            $response_report['request']['print_url'] = $base_url.$result[IpReportRequestController::REPORT_PDF] . '&download=0';
          }
        }
      }

      $response['report'] = $response_report;

      return (new ResourceResponse($response, 200))
        ->addCacheableDependency($this->without_cache);
    }
    else {
      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['status'] = FALSE;

      $response['errors'][] = t('Report with id @rid is not found.', ['@rid' => $rid]);

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }

}
