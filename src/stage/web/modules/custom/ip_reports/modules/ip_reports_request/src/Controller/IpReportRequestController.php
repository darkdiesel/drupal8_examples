<?php

namespace Drupal\ip_reports_request\Controller;

use Complex\Exception;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\ip_reports_request\Model\IpReportsRequestsModel;
use Drupal\ip_reports_request\Controller\IpReportRequestCalculationServiceController;

/**
 * Defines IpReportRequestController class.
 */
class IpReportRequestController extends ControllerBase {

  const REPORT_PDF = 'pdf_url';

  const REPORT_DOCX = 'docx_url';

  const REPORT_HTML = 'html_url';

  const REPORT_PRINT = 'print_url';

  /**
   * @param $report_structure_id
   * @param $remote_report_id
   * @param $site_id
   *
   * @return string
   */
  public static function buildSerializedResults($report_structure_id, $remote_report_id, $site_id) {
    return serialize(self::buildResults($report_structure_id, $remote_report_id, $site_id));
  }

  /**
   * Build array of report request results
   *
   * @param $report_structure_id
   * @param $remote_report_id
   * @param $site_id
   *
   * @return array
   */
  public static function buildResults($report_structure_id, $remote_report_id, $site_id) {
    return [
      self::REPORT_PDF  => sprintf('/reports/%s/%s/pdf?site_id=%s', $report_structure_id, $remote_report_id, $site_id),
      self::REPORT_HTML => sprintf('/reports/%s/%s/html?site_id=%s', $report_structure_id, $remote_report_id, $site_id),
      self::REPORT_DOCX => sprintf('/reports/%s/%s/docx?site_id=%s', $report_structure_id, $remote_report_id, $site_id),
    ];
  }

  public static function getResultsLabel($label) {
    $labels = self::getResultsLabels();

    if (isset($labels[$label])) {
      return $labels[$label];
    }
    else {
      return '';
    }
  }

  public static function getResultsLabels() {
    return [
      self::REPORT_PDF   => t('Download PDF'),
      self::REPORT_DOCX  => t('Download DOCX'),
      self::REPORT_HTML  => t('Download HTML'),
      self::REPORT_PRINT => t('Print Report'),
    ];
  }


  public static function setAnonymousReprots($wid, $sid, $data) {
    $tempstore = \Drupal::service('user.private_tempstore')
                        ->get('ip_reports_request');

    return $tempstore->set(sprintf('webfrom_%s_%s', $wid, $sid), $data);
  }

  public static function getAnonymousReprots($wid, $sid) {
    $tempstore = \Drupal::service('user.private_tempstore')
                        ->get('ip_reports_request');

    return $tempstore->get(sprintf('webfrom_%s_%s', $wid, $sid));
  }

}
