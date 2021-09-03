<?php

namespace Drupal\ip_webform_api\Model;

class IpWebformApiTasksLogModel {

  public static $logs_table = 'ip_webform_api_tasks_logs';

  /**
   * @param $task_id
   * @param $message
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   * @throws \Exception
   */
  public static function add($task_id, $message) {
    return \Drupal::database()->insert(self::$logs_table)
                  ->fields([
                    'task_id'    => $task_id,
                    'message'    => $message,
                    'created_at' => \Drupal::time()->getRequestTime(),
                  ])
                  ->execute();
  }
}
