<?php

namespace Drupal\ip_webform\Model;

class IpWebFormLogsModel {
  public static $logs_table = 'ip_webform_logs';

  /**
   * @param $log
   * @param $webform_id
   *
   * @throws \Exception
   */
  public static function add($log, $webform_id){
    \Drupal::database()->insert(self::$logs_table)
      ->fields([
        'uid' => \Drupal::currentUser()->id(),
        'log' => $log,
        'webform' => $webform_id,
        'timestamp' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }
}
