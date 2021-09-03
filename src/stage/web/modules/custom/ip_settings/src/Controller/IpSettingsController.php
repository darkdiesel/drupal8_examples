<?php

namespace Drupal\ip_settings\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ip_settings\Form\IpSettingsForm;


/**
 * Class IpSettingsController
 *
 * @package Drupal\ip_settings\Controller
 */
class IpSettingsController extends ControllerBase {

  static function getAPISettings(){
    return \Drupal::config(IpSettingsForm::API_SETTINGS);
  }

  static function getReactSettings(){
    return \Drupal::config(IpSettingsForm::REACT_SETTINGS);
  }
}
