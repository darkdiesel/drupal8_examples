<?php

namespace Drupal\ip_webform_remarks\Model;

class IpWebformRemarksLogModel {

  public static $remarks_table = 'ip_webform_remarks_logs';

  /**
   * @param $remark_id
   * @param $message
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   * @throws \Exception
   */
  public static function add($remark_id, $message) {
    try {

      if (is_array($message)) {
        $message = implode(",", $message);
      }

      return \Drupal::database()->insert(self::$remarks_table)
                    ->fields([
                      'uid'        => \Drupal::currentUser()->id(),
                      'remark_id'  => $remark_id,
                      'message'    => $message,
                      'created_at' => \Drupal::time()->getRequestTime(),
                    ])
                    ->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_remarks')
             ->error(t('Something went wrong during remark logs creating. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_remarks')->error($e->getMessage());
      return FALSE;
    }
  }
}
