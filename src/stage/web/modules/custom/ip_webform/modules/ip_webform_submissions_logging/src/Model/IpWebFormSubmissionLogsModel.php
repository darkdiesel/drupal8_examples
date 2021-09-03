<?php

namespace Drupal\ip_webform_submissions_logging\Model;

use Drupal\Component\Utility\Xss;

class IpWebFormSubmissionLogsModel {

  public static $logs_table = 'ip_webform_submissions_logs';

  private static $logs_per_page = 15;

  /**
   * @param $log
   * @param $wid
   * @param $sid
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function add($log, $wid, $sid) {
    try {
      return \Drupal::database()->insert(self::$logs_table)
                    ->fields([
                      'uid'        => \Drupal::currentUser()->id(),
                      'log'        => $log,
                      'webform'    => $wid,
                      'submission' => $sid,
                      'created_at' => \Drupal::time()->getRequestTime(),
                    ])
                    ->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_submissions_logging')
             ->error(t('Some error occurred while saving log to webform submission logs table.'));
      \Drupal::logger('ip_webform_submissions_logging')
             ->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * @param $wid
   *
   * @return bool
   */
  public static function getAllByWebform($wid) {
    try {
      $connection = \Drupal::database();

      $query = $connection->select(self::$logs_table, 'l');

      $query->fields('l', [
        'lid',
        'uid',
        'webform',
        'submission',
        'log',
        'created_at',
      ]);

      $query->condition('l.webform', Xss::filter($wid), '=');

      $query->orderBy('l.created_at', 'DESC');

      $pager = $query->extend('\Drupal\Core\Database\Query\PagerSelectExtender')
                     ->limit(self::$logs_per_page);

      return $pager->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_submissions_logging')
             ->error(t('Some error occurred while getting webform submission logs.'));

      \Drupal::logger('ip_webform_submissions_logging')
             ->error($e->getMessage());

      return FALSE;
    }
  }

  /**
   * @param $wid
   * @param $sid
   *
   * @return bool
   */
  public static function getAllByWebformAndSubmission($wid, $sid) {
    try {
      $connection = \Drupal::database();

      $query = $connection->select(self::$logs_table, 'l');

      $query->fields('l', [
        'lid',
        'uid',
        'webform',
        'submission',
        'log',
        'created_at',
      ]);

      $query->condition('l.webform', Xss::filter($wid), '=');
      $query->condition('l.submission', Xss::filter($sid), '=');

      $pager = $query->extend('\Drupal\Core\Database\Query\PagerSelectExtender')
                     ->limit(self::$logs_per_page);

      return $pager->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_submissions_logging')
             ->error(t('Some error occurred while getting webform submission logs.'));

      \Drupal::logger('ip_webform_submissions_logging')
             ->error($e->getMessage());

      return FALSE;
    }
  }
}
