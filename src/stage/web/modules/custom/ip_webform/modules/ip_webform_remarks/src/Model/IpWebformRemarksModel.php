<?php

namespace Drupal\ip_webform_remarks\Model;

class IpWebformRemarksModel {

  public static $remarks_table = 'ip_webform_remarks';

  /**
   * Create nw remark
   *
   * @param $organisation_id
   * @param $indicator
   * @param $text
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   * @throws \Exception
   */
  public static function add($organisation_id, $indicator, $text) {
    try {
    $remark =  \Drupal::database()->insert(self::$remarks_table)
                  ->fields([
                    'uid' => \Drupal::currentUser()->id(),
                    'organisation_id' => $organisation_id,
                    'indicator'       => $indicator,
                    'text'       => $text,
                    'created_at' => \Drupal::time()->getRequestTime(),
                    'updated_at' => \Drupal::time()->getRequestTime(),
                  ])
                  ->execute();

      if ($remark !== FALSE) {
        $remark_log = IpWebformRemarksLogModel::add($remark, $text);
      }

      return $remark;
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_remarks')
             ->error(t('Something went wrong during remark creating. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_remarks')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   *
   * Update task
   *
   * @param $organisation_id
   * @param $indicator
   * @param array $fields
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function update($organisation_id, $indicator, $fields = []) {

    if (!is_array($fields)) {
      $fields = [];
    }

    $fields = array_merge($fields,
      [
        'uid' => \Drupal::currentUser()->id(),
        'updated_at' => \Drupal::time()->getRequestTime(),
      ]
    );

    try {
      $remark =  \Drupal::database()->update(self::$remarks_table)
                    ->fields($fields)
                    ->condition('remarks.organisation_id', $organisation_id, '=')
                    ->condition('remarks.indicator', $indicator, '=')
                    ->execute();

      if ($remark !== FALSE) {
        $remark_log = IpWebformRemarksLogModel::add($remark, print_r($fields));
      }

      return $remark;
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_remarks')
             ->error(t('Something went wrong during submission updating. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   *
   * Update task
   *
   * @param $remark_id
   * @param array $fields
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function updateRemark($remark_id, $fields = []) {

    if (!is_array($fields)) {
      $fields = [];
    }

    $log_fields = $fields;

    $fields = array_merge($fields,
      [
        'uid' => \Drupal::currentUser()->id(),
        'updated_at' => \Drupal::time()->getRequestTime(),
      ]
    );

    try {
      $remark =  \Drupal::database()->update(self::$remarks_table)
                    ->fields($fields)
                    ->condition('remark_id', $remark_id, '=')
                    ->execute();

      if ($remark !== FALSE) {
        $remark_log = IpWebformRemarksLogModel::add($remark, $log_fields);
      }

      return $remark;
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_remarks')
             ->error(t('Something went wrong during submission updating. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get remark by $organisation_id and indicator
   *
   * @param $organisation_id
   * @param $indicator
   *
   * @return mixed
   */
  public static function get($organisation_id, $indicator) {
    $query = \Drupal::database()->select(self::$remarks_table, 'remarks');
    $query->fields('remarks', ['remark_id', 'uid', 'organisation_id', 'indicator' ,'text', 'created_at', 'updated_at']);
    $query->condition('remarks.organisation_id', $organisation_id);
    $query->condition('remarks.indicator', $indicator);
    $query->range(0, 1);

    return $query->execute()->fetchObject();
  }

  /**
   * Set finished status for task
   *
   * @param $remark_id int
   * @param $text string
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function setText($remark_id, $text) {
    return self::updateRemark($remark_id, ['text' => $text]);
  }

}
