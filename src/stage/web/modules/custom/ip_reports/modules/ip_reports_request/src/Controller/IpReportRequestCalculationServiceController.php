<?php

namespace Drupal\ip_reports_request\Controller;

use Complex\Exception;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\ip_settings\Controller\IpSettingsController;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Defines IpWebformApiLaravelIntegrationController class.
 */
class IpReportRequestCalculationServiceController extends ControllerBase {

  /* @var string $config_name Configuration settings name */
  private $config_name = 'laravel.settings';
  /* @var $config  Calculation Service Configuration */
  private $config = FALSE;
  /* @var $webform_settings WebForm Settings */
  private $webform_settings = FALSE;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;
  protected $serializer_format = 'json';

  /**
   * Send request for creating report
   *
   * @param $report_structure_node_id int
   * @param $conditions array
   * @param $force bool
   *
   * @return bool|mixed
   */
  public function createReport($report_structure_node_id, $conditions = [], $force = FALSE) {
    $base_url = $this->getBaseUrl();

    if ($base_url === FALSE) {
      return FALSE;
    }

    try {
      $site_id = $this->getSiteID();

      $result = \Drupal::httpClient()
                       ->post(sprintf('%s/reports/%s/create?force=%s', $base_url, $report_structure_node_id, $force ? 1 : 0), [
                         'json' => [
                           'conditions' => $conditions,
                           'site_id' => $site_id
                         ],
                         'headers' => [
                           'Accept' => "application/$this->serializer_format",
                         ]
                       ]);

      $response = $result->getBody();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during sending request for creating report. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }

    if (isset($response) && $response) {
      $serializer = $this->getSerializer();

      return $serializer->decode($response, $this->serializer_format);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Send request for regenerating existing report
   *
   * @param $report_structure_node_id int
   * @param $remote_report_id int
   * @param $force bool
   *
   * @return bool|mixed
   */
  public function regenerateReport($report_structure_node_id, $remote_report_id, $force = FALSE) {
    $base_url = $this->getBaseUrl();

    if ($base_url === FALSE) {
      return FALSE;
    }

    try {
      $site_id = $this->getSiteID();

      $result = \Drupal::httpClient()
                       ->post(sprintf('%s/reports/%s/%s/generate?site_id=%s&force=%s', $base_url, $report_structure_node_id, $remote_report_id, $site_id, $force ? 1 : 0), [
                         'headers'     => [
                           'Content-type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
                           'X-Requested-With' => 'XMLHttpRequest',
                         ],
                       ]);

      $response = $result->getBody();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during sending request for report regeneration. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }

    if (isset($response) && $response) {
      $serializer = $this->getSerializer();

      return $serializer->decode($response, $this->serializer_format);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Send request for deleting report
   *
   * @param $report_structure_node_id int
   * @param $remote_report_id int
   * @param $force bool
   *
   * @return bool|mixed
   */
  public function deleteReport($report_structure_node_id, $remote_report_id, $force = FALSE) {
    $base_url = $this->getBaseUrl();

    if ($base_url === FALSE) {
      return FALSE;
    }

    try {
      $site_id = $this->getSiteID();

      $result = \Drupal::httpClient()
                       ->post(sprintf('%s/reports/%s/%s/delete?site_id=%s&force=%s', $base_url, $report_structure_node_id, $remote_report_id, $site_id, $force ? 1 : 0), [
                         'headers'     => [
                           'Content-type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
                           'X-Requested-With' => 'XMLHttpRequest',
                         ],
                       ]);

      $response = $result->getBody();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during sending request for report deleting. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }

    if (isset($response) && $response) {
      $serializer = $this->getSerializer();

      return $serializer->decode($response, $this->serializer_format);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get port detail
   *
   * @param $report_structure_node_id int
   * @param $remote_report_id int
   *
   * @return bool|mixed
   */
  public function getReportDetails($report_structure_node_id, $remote_report_id) {
    $base_url = $this->getBaseUrl();

    if ($base_url === FALSE) {
      return FALSE;
    }

    try {
      $site_id = $this->getSiteID();

      $result = \Drupal::httpClient()
                       ->get(sprintf('%s/reports/%s/%s/details?site_id=%s', $base_url, $report_structure_node_id, $remote_report_id, $site_id), [
                         'headers'     => [
                           'Content-type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
                           'X-Requested-With' => 'XMLHttpRequest',
                         ],
                       ]);

      $response = $result->getBody();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during sending request for report details. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }

    if (isset($response) && $response) {
      $serializer = $this->getSerializer();

      return $serializer->decode($response, $this->serializer_format);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Return configuration for laravel integration
   *
   * @return bool|\Drupal\Core\Config\ImmutableConfig
   */
  public function getConfig(){
    if (!$this->config) {
      $this->config = \Drupal::config('laravel.settings');
    }

    return $this->config;
  }

  /**
   * Return url for calculation service API
   *
   * @return array|bool|mixed|null
   */
  public function getBaseUrl() {
    $config = $this->getConfig();

    $base_url = $config->get('base_url');

    if (!$base_url) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Please setup base_url config for laravel integration!'));

      return FALSE;
    }
    else {
      return $base_url;
    }
  }

  /**
   * Return ip webform settings.
   *
   * @return bool|\Drupal\Core\Config\ImmutableConfig
   */
  public function getWebformSettings(){
    if (!$this->webform_settings) {
      $this->webform_settings = IpSettingsController::getAPISettings();
    }

    return $this->webform_settings;
  }

  /**
   * Return site id for request to calculation service
   *
   * @return array|bool|mixed|null
   */
  public function getSiteID(){
    $webform_settings = $this->getWebformSettings();

    $site_id = $webform_settings->get('site_id');

    if (!$site_id) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Please setup site_id config for laravel integration!'));

      return '';
    }
    else {
      return $site_id;
    }
  }

  /**
   * get Json Serializer
   *
   * @return \Symfony\Component\Serializer\Serializer
   */
  public function getSerializer() {
    if (!$this->serializer) {
      $json_encoder = new JsonEncoder();

      $this->serializer = new Serializer([], [$json_encoder]);
    }

    return $this->serializer;
  }

}
