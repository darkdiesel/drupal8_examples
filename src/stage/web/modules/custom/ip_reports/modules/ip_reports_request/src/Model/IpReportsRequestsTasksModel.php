<?php

namespace Drupal\ip_reports_request\Model;

class IpReportsRequestsTasksModel {

  public static $tasks_table = 'ip_reports_requests_tasks';

  // task statuses
  const STATUS_PENDING = 'pending';
  const STATUS_PROCESSING = 'processing';
  const STATUS_FINISHED = 'completed';
  const STATUS_ERROR = 'failed';

  // Count of tasks that will be used for one cron job by default
  const TASK_COUNT = 20;

  /**
   *
   * Create new task
   *
   * @param $task string
   * @param $webform_id string
   * @param $submission_id int
   * @param $organisation_id int
   * @param $conditions array
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   * @throws \Exception
   */
  public static function add($task, $webform_id, $submission_id, $organisation_id, $conditions = []) {
    if (!$organisation_id) {
      $organisation_id = NULL;
    }

    return \Drupal::database()->insert(self::$tasks_table)
                  ->fields([
                    'task'       => $task,
                    'uid'              => \Drupal::currentUser()->id(),
                    'webform_id'       => $webform_id,
                    'submission_id'       => $submission_id,
                    'organisation_id'       => $organisation_id,
                    'conditions' => serialize($conditions),
                    'status'     => self::STATUS_PENDING,
                    'created_at' => \Drupal::time()->getRequestTime(),
                  ])
                  ->execute();
  }

  /**
   *
   * Update task
   *
   * @param $task_id
   * @param array $fields
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public static function update($task_id, $fields = []) {

    if (!is_array($fields)) {
      $fields = [];
    }

    $fields = array_merge($fields,
      [
        'updated_at' => \Drupal::time()->getRequestTime(),
      ]
    );

    try {
      return \Drupal::database()->update(self::$tasks_table)
                    ->fields($fields)
                    ->condition('task_id', $task_id, '=')
                    ->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_reports_request')
             ->error(t('Something went wrong during report request task updating. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_reports_request')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get task by task_id
   *
   * @param $task_id
   *
   * @return mixed
   */
  public static function get($task_id) {
    $query = \Drupal::database()->select(self::$tasks_table, 'tasks');
    $query->fields('tasks', ['task_id', 'task' ,'status', 'created_at', 'updated_at']);
    $query->condition('tasks.task_id', $task_id);
    $query->range(0, 1);

    return $query->execute()->fetchObject();
  }

  /**
   * Get last tasks with not completed status
   *
   * @param $count
   *
   * @return mixed
   */
  public static function getLastInProgressLimit($count = self::TASK_COUNT) {
    $query = \Drupal::database()->select(self::$tasks_table, 'tasks');
    $query->fields('tasks', [
      'task_id',
      'task',
      'uid',
      'webform_id',
      'submission_id',
      'organisation_id',
      'conditions',
      'status',
      'created_at',
      'updated_at',
    ]);
    //$query->condition('tasks.task_id', $task_id);
    //$query->range(0, 1);
    $query->condition('tasks.status', [
      self::STATUS_FINISHED,
      self::STATUS_ERROR,
    ], 'NOT IN');
    $query->orderBy('tasks.updated_at', 'ASC');
    $query->orderBy('tasks.created_at', 'ASC');

    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                   ->limit($count);

    return $pager->execute();
  }

  /**
   * Set error status for task
   *
   * @param $task_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public function setStatusError($task_id) {
    return self::update($task_id, ['status' => self::STATUS_ERROR]);
  }

  /**
   * Set finished status for task
   *
   * @param $task_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public function setStatusFinish($task_id) {
    return self::update($task_id, ['status' => self::STATUS_FINISHED]);
  }

  /**
   * Set processing status for task
   *
   * @param $task_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public function setStatusProcessing($task_id) {
    return self::update($task_id, ['status' => self::STATUS_PROCESSING]);
  }

  /**
   * Set pending status for task
   *
   * @param $task_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public function setStatusPending($task_id) {
    return self::update($task_id, ['status' => self::STATUS_PENDING]);
  }
}
