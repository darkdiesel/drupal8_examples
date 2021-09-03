<?php

namespace Drupal\ip_reports_request\Model;

use Drupal\Core\Database\Database;

class IpReportsRequestsModel {

  public static $reports_requests_table = 'ip_reports_requests';

  const STATUS_ALL = 'all';

  const STATUS_PENDING = 'pending';

  const STATUS_PROCESSING = 'processing';

  const STATUS_FINISHED = 'completed';

  const STATUS_ERROR = 'failed';

  // count of displayed reports on list page
  const REPORTS_COUNT = 15;

  /**
   * @param int $uid
   * @param int $report_node_id
   * @param int $remote_report_id
   * @param string $webform_id
   * @param int $submission_id
   * @param array $conditions
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   * @throws \Exception
   */
  public static function add($uid, $report_node_id, $remote_report_id, $webform_id, $submission_id, $conditions = []) {
    try {

      if (isset($conditions['organisation_id']) && $conditions['organisation_id']) {
        $organisation_id = $conditions['organisation_id'];
      }
      else {
        $organisation_id = NULL;
      }

      return \Drupal::database()->insert(self::$reports_requests_table)
                    ->fields([
                      'uid'              => $uid,
                      'organisation_id'  => $organisation_id,
                      'report_node_id'   => $report_node_id,
                      'remote_report_id' => $remote_report_id,
                      'webform_id'       => $webform_id,
                      'submission_id'    => $submission_id,
                      'conditions'       => serialize($conditions),
                      'result'           => serialize([]),
                      'created_at'       => \Drupal::time()->getRequestTime(),
                    ])
                    ->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during saving report request to DB. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get report by report_id
   *
   * @param int $report_id
   *
   * @return mixed
   */
  public static function get($report_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'webform_id',
        'submission_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.report_id', $report_id);
      $query->range(0, 1);

      return $query->execute()->fetchObject();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting report. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get report by report_id and user
   *
   * @param int $user_id
   *
   * @return mixed
   */
  public static function getByUser($user_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.uid', $user_id);
      $query->orderBy('reports.created_at', 'DESC');
      $query->range(0, 1);

      return $query->execute()->fetchObject();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting report. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get report by report_id and user
   *
   * @param int $report_node_id
   * @param string $webform_id
   * @param int $submission_id
   *
   * @return mixed
   */
  public static function getByNodeReportWebformSubmissionID($report_node_id, $webform_id, $submission_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.report_node_id', $report_node_id);
      $query->condition('reports.webform_id', $webform_id);
      $query->condition('reports.submission_id', $submission_id);
      $query->orderBy('reports.created_at', 'DESC');
      $query->range(0, 1);

      return $query->execute()->fetchObject();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting report. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get report by report_id and user
   *
   * @param string $webform_id
   * @param int $submission_id
   *
   * @return mixed
   */
  public static function getAlByWebformSubmissionID($webform_id, $submission_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'webform_id',
        'submission_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.webform_id', $webform_id);
      $query->condition('reports.submission_id', $submission_id);
      $query->orderBy('reports.created_at', 'DESC');
      $query->range(0, 1);

      return $query->execute()->fetchAll();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting reports by webform and submission id. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get report by report_node_id and user
   *
   * @param int $report_node_id
   * @param int $organisation_id
   *
   * @return mixed
   */
  public static function getByOrganisationID($report_node_id, $organisation_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.report_node_id', $report_node_id);
      $query->condition('reports.organisation_id', $organisation_id);
      $query->orderBy('reports.created_at', 'DESC');
      $query->range(0, 1);

      return $query->execute()->fetchObject();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting report. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get report by report_node_id, organisation id and webform id
   *
   * @param int $report_node_id
   * @param int $organisation_id
   * @param string $webform_id
   *
   * @return mixed
   */
  public static function getByWebformOrganisationID($report_node_id, $organisation_id, $webform_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'webform_id',
        'submission_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.report_node_id', $report_node_id);
      $query->condition('reports.webform_id', $webform_id);
      $query->condition('reports.organisation_id', $organisation_id);
      $query->orderBy('reports.created_at', 'DESC');
      $query->range(0, 1);

      return $query->execute()->fetchObject();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting report. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get report by report_id and user
   *
   * @param int $organisation_id
   *
   * @return mixed
   */
  public static function getAllByOrganisationID($organisation_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.organisation_id', $organisation_id);
      $query->orderBy('reports.created_at', 'DESC');
      //      $query->groupBy('reports.report_node_id');

      //      $query->range(0, 1);

      return $query->execute()->fetchAll();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting reports. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }


  public static function getAllByOrganisationListID($organisations) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.organisation_id', $organisations, 'IN');
      $query->orderBy('reports.created_at', 'DESC');
      //      $query->groupBy('reports.report_node_id');

      //      $query->range(0, 1);

      return $query->execute()->fetchAll();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting reports. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }


  /**
   * get all reports attached to $node_report_id
   *
   * @param int $node_report_id
   *
   * @return mixed
   */
  public static function getAllByNodeReportID($node_report_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'organisation_id',
        'remote_report_id',
        'report_node_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);
      $query->condition('reports.report_node_id', $node_report_id);
      $query->orderBy('reports.created_at', 'DESC');

      return $query->execute()->fetchAll();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting reports. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * @param int $count
   *
   * @return bool|array
   */
  public static function getReportsLimit($count = self::REPORTS_COUNT) {
    try {
      $connection = Database::getConnection();

      $query = $connection->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'remote_report_id',
        'report_node_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);

      $query->orderBy('reports.updated_at', 'ASC');
      $query->orderBy('reports.created_at', 'ASC');

      $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                     ->limit($count);

      $result = $pager->execute();

      return $result;
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting reports list. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * Get last reports with not completed status
   *
   * @param $count
   *
   * @return mixed
   */
  public static function getLastInProgressLimit($count = self::REPORTS_COUNT) {
    try {
      $connection = Database::getConnection();

      $query = $connection->select(self::$reports_requests_table, 'reports');
      $query->fields('reports', [
        'report_id',
        'remote_report_id',
        'report_node_id',
        'conditions',
        'status',
        'result',
        'created_at',
        'updated_at',
      ]);

      $query->condition('reports.status', [
        self::STATUS_FINISHED,
        self::STATUS_ERROR,
      ], 'NOT IN');
      $query->orderBy('reports.updated_at', 'ASC');
      $query->orderBy('reports.created_at', 'ASC');

      $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                     ->limit($count);

      return $pager->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during getting reports list for cron. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   *
   * Update report
   *
   * @param $report_id
   * @param array $fields
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function update($report_id, $fields = []) {

    if (!is_array($fields)) {
      $fields = [];
    }

    $fields = array_merge($fields,
      [
        'updated_at' => \Drupal::time()->getRequestTime(),
      ]
    );

    try {
      return \Drupal::database()->update(self::$reports_requests_table)
                    ->fields($fields)
                    ->condition('report_id', $report_id, '=')
                    ->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during report updating. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * Set error status for report
   *
   * @param $report_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function setStatusError($report_id) {
    return self::update($report_id, ['status' => self::STATUS_ERROR]);
  }

  /**
   * Set finished status for report
   *
   * @param $report_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function setStatusFinish($report_id) {
    return self::update($report_id, ['status' => self::STATUS_FINISHED]);
  }

  /**
   * Set processing status for report
   *
   * @param $report_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function setStatusProcessing($report_id) {
    return self::update($report_id, ['status' => self::STATUS_PROCESSING]);
  }

  /**
   * Set pending status for report
   *
   * @param $report_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function setStatusPending($report_id) {
    return self::update($report_id, ['status' => self::STATUS_PENDING]);
  }

  /**
   *
   * Delete report request from DB
   *
   * @param $report_id
   *
   * @return bool|int
   */
  public static function delete($report_id) {
    try {
      $connection = Database::getConnection();

      return $connection->delete(self::$reports_requests_table)
                        ->condition('report_id', $report_id)
                        ->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during report deleting. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }
}
