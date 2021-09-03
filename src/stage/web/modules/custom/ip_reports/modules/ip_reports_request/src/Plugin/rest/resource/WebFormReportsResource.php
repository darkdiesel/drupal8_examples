<?php

namespace Drupal\ip_reports_request\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ip_reports_request\Controller\IpReportRequestCalculationServiceController;
use Drupal\ip_reports_request\Controller\IpReportRequestController;
use Drupal\ip_users\Controller\IpUserDataController;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ip_reports_request\Model\IpReportsRequestsModel;

/**
 * Provides a resource to get webform reports by webform
 *
 * @RestResource(
 *   id = "get_webform_reports",
 *   label = @Translation("Get webform reports by webform id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/{wid}/reports"
 *   }
 * )
 */
class WebFormReportsResource extends ResourceBase {

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
   * @param  $wid
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($wid) {
    $webform = current(\Drupal::entityTypeManager()
                              ->getStorage('webform')
                              ->loadByProperties(['id' => $wid]));

    if ($webform instanceof Webform) {
      $response = ['status' => TRUE];

      if ($webform->isClosed() === TRUE) {
        $response['status'] = FALSE;
        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t('Webform @wid is closed.', ['@wid' => $wid]);

        return (new ResourceResponse($response, 403))
          ->addCacheableDependency($this->without_cache);
      }

      $organisation = IpUserDataController::getOrganisation($this->currentUser);

      // get reports list filtered by webform
      $values = [
        'type'                 => 'report',
        'field_report_webform' => $webform->id(),
        'field_report_settings' => 'show',
      ];

      $response = [
        'organisation' => $organisation,
        'reports'       => [],
      ];

      $report_nodes = \Drupal::entityTypeManager()
                             ->getStorage('node')
                             ->loadByProperties($values);

      $reportRequestCalculationService = new IpReportRequestCalculationServiceController();
      $base_url                        = $reportRequestCalculationService->getBaseUrl();

      foreach ($report_nodes as $report_node) {

        $can_download_pdf  = $report_node->get('field_download_pdf')->value == 'allow';
        $can_download_docx = $report_node->get('field_download_docx')->value == 'allow';

        $show_print_btn = (bool) $report_node->get('field_print_button')->value;

        $report_request = IpReportsRequestsModel::getByWebformOrganisationID($report_node->id(), $organisation, $webform->id());

        if ($report_request) {

          $response_report = [
            'id'           => $report_request->report_id,
            'structure_id' => $report_node->id(),
            'title'        => $report_node->getTitle(),
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
    else {
      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['status'] = FALSE;

      $response['errors'][] = t('Webform with id @wid is not found.', ['@wid' => $wid]);

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }

}

