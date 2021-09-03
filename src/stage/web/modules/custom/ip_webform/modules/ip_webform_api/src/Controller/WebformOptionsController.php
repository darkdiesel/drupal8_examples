<?php

namespace Drupal\ip_webform_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\Webform;

/**
 *
 * Defines WebformOptionsController class.
 */
class WebformOptionsController extends ControllerBase {

  /**
   * Return options for webform that will be used for API
   *
   * @param $webform
   *
   * @return array
   */
  public static function getWebformOptions($webform){
    if ($webform instanceof Webform) {
      $options = [];

      // cycle name
      $val = $webform->getThirdPartySetting('ip_webform', 'cycle_name');

      $options['cycle_name'] = $val;

      // get explanation text option
      $val = $webform->getThirdPartySetting('ip_webform', 'explanation_text');

      $options['explanation_text'] = isset($val['value']) ? $val['value'] : null;

      // get show estimate reliable option
      $val = $webform->getThirdPartySetting('ip_webform', 'input_data_show_estimate_reliable');

      if ($val && strtolower($val) == 'yes') {
        $val = TRUE;
      }
      else {
        $val = FALSE;
      }

      $options['show_estimate_reliable'] = $val;

      // get show remarks option
      $val = $webform->getThirdPartySetting('ip_webform', 'input_data_show_remarks');

      if ($val && strtolower($val) == 'yes') {
        $val = TRUE;
      }
      else {
        $val = FALSE;
      }

      $options['show_remarks'] = $val;

      // get show description option
      $val = $webform->getThirdPartySetting('ip_webform', 'input_data_show_description_popup');

      if ($val && strtolower($val) == 'yes') {
        $val = TRUE;
      }
      else {
        $val = FALSE;
      }

      $options['show_description_popup'] = $val;

      return $options;
    } else {
      return [];
    }
  }
}
