<?php

namespace Drupal\ip_webform_api\Controller;

use Complex\Exception;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ip_settings\Controller\IpSettingsController;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Defines CalculationServiceController class.
 */
class CalculationServiceController extends ControllerBase {

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
   * Send request for creating request
   *
   * @param $webform_id
   * @param $submission_id
   *
   * @return bool|mixed
   */
  public function createWebform($webform_id){
    $base_url = $this->getBaseUrl();

    if ($base_url === FALSE) {
      return FALSE;
    }

    try {
      $site_id = $this->getSiteID();

      $result = \Drupal::httpClient()->post(sprintf('%s/forms/%s/create?site_id=%s', $base_url, $webform_id, $site_id), [
//        'form_params' => [
//          'site_id' => $site_id,
//        ],
        'headers' => [
          'Content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With' => 'XMLHttpRequest'
        ],
      ]);

      $response = $result->getBody();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_api')->error(t('Something went wrong during sending webform to calculation service. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }

    if (isset($response) && $response) {
      $serializer = $this->getSerializer();

      return $serializer->decode($response, $this->serializer_format);
    } else {
      return FALSE;
    }
  }

  /**
   * Send request for creating request
   *
   * @param $webform_id
   * @param $submission_id
   *
   * @return bool|mixed
   */
  public function createSubmission($webform_id, $submission_id, $force = FALSE){
    $base_url = $this->getBaseUrl();

    if ($base_url === FALSE) {
      return FALSE;
    }

    try {
      $site_id = $this->getSiteID();

      $result = \Drupal::httpClient()->post(sprintf('%s/submissions/%s/%s/create?force=%s', $base_url, $webform_id, $submission_id, $force ? 1 : 0), [
        'form_params' => [
          'site_id' => $site_id,
        ],
        'headers' => [
          'Content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With' => 'XMLHttpRequest'
        ],
      ]);

      $response = $result->getBody();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_api')->error(t('Something went wrong during sending submission to calculation service. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }

    if (isset($response) && $response) {
      $serializer = $this->getSerializer();

      return $serializer->decode($response, $this->serializer_format);
    } else {
      return FALSE;
    }
  }

  public function getSubmissionDetails($webform_id, $submission_id, $force = FALSE){
    $base_url = $this->getBaseUrl();

    if ($base_url === FALSE) {
      return FALSE;
    }

    try {
      $site_id = $this->getSiteID();

      $result = \Drupal::httpClient()->get(sprintf('%s/submissions/%s/%s/details?site_id=%s&force=%s', $base_url, $webform_id, $submission_id, $site_id, $force ? 1 : 0), [
        'headers'       => [
          'Content-type' => 'application/json',
          'X-Requested-With' => 'XMLHttpRequest'
        ],
      ]);

      $response = $result->getBody();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_api')->error(t('Something went wrong during getting submission processing status from calculation service. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }

    if (isset($response) && $response) {
      $serializer = $this->getSerializer();

      return $serializer->decode($response, $this->serializer_format);
    } else {
      return FALSE;
    }
  }

  public function deleteSubmission($webform_id, $submission_id, $force = FALSE){
    $base_url = $this->getBaseUrl();

    if ($base_url === FALSE) {
      return FALSE;
    }

    try {
      $site_id = $this->getSiteID();

      $result = \Drupal::httpClient()->get(sprintf('%s/submissions/%s/%s/delete?site_id=%s&force=%s', $base_url, $webform_id, $submission_id, $site_id, $force ? 1 : 0), [
        'headers'       => [
          'Content-type' => 'application/json',
          'X-Requested-With' => 'XMLHttpRequest'
        ],
      ]);

      $response = $result->getBody();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_api')->error(t('Something went wrong during deleting submission from calculation service. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }

    if (isset($response) && $response) {
      $serializer = $this->getSerializer();

      return $serializer->decode($response, $this->serializer_format);
    } else {
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
      $this->config = \Drupal::config($this->config_name);
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
      \Drupal::logger('ip_webform_api')
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
      \Drupal::logger('ip_webform_api')
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
