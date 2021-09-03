<?php

namespace Drupal\ip_reports_request\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\ip_reports_request\Model\IpReportsRequestsTasksModel;

/**
 * Defines TaskLogsController class.
 */
class TaskLogsController extends ControllerBase {

  public function cronTasks($status = FALSE) {
    $connection = Database::getConnection();

    //@TODO Move getting tasks method to model
    $sth = $connection->select('ip_reports_requests_tasks_logs', 'logs');
    $sth->fields('logs', ['task_id']);
    $sth->leftJoin('ip_reports_requests_tasks', 'tasks', 'logs.task_id = tasks.task_id');
    $sth->addField('tasks', 'status', 'task_status');
    $sth->addField('tasks', 'task', 'task_text');

    switch ($status) {
      case IpReportsRequestsTasksModel::STATUS_PENDING:
      case IpReportsRequestsTasksModel::STATUS_PROCESSING:
      case IpReportsRequestsTasksModel::STATUS_FINISHED:
      case IpReportsRequestsTasksModel::STATUS_ERROR:
        $sth->condition('tasks.status', $status);
        break;
    }

    $sth->addExpression('COUNT(logs.task_id)', 'logs_count');
    $sth->addExpression('MAX(logs.created_at)', 'created_at');
    $sth->groupBy('logs.task_id, tasks.task, tasks.status');
    $sth->orderBy('logs.created_at', 'DESC');

    $pager = $sth->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                 ->limit(15);

    $result = $pager->execute();

    $rows = [];

    foreach ($result as $row => $content) {
//      $task_url = Url::fromUri('internal:/admin/reports/ip_reports_request_cron_tasks/' . $content->task_id . '/info');
//      $task_url = Url::fromRoute('ip_reports_request.cron_tasks.task_detail', ['task_id' => $content->task_id], ['absolute' => TRUE]);
//      $task_link = Link::fromTextAndUrl($content->task_id, $task_url);

      $task_link = Link::createFromRoute($content->task_id, 'ip_reports_request.cron_tasks.task_detail', ['task_id' => $content->task_id], ['absolute' => TRUE]);

      $rows[] = [
        $task_link,
        $content->task_text,
        $content->task_status,
        $content->logs_count,
        date('Y-m-d H:i:s', $content->created_at),
      ];
    }

    $header = [t('ID'), t('Task'), t('Status'), t('Logs Count'), t('Last Update')];

    $build = [
      'table' => [
        '#prefix'        => '<h1>'.t('Tasks').'</h1>',
        '#theme' => 'table',
        '#attributes' => [
          'data-striping' => 0,
        ],
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t("No Results Found")
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

  public function pendingTasks(){
    return $this->cronTasks(IpReportsRequestsTasksModel::STATUS_PENDING);
  }

  public function processingTasks(){
    return $this->cronTasks(IpReportsRequestsTasksModel::STATUS_PROCESSING);
  }

  public function finishedTasks(){
    return $this->cronTasks(IpReportsRequestsTasksModel::STATUS_FINISHED);
  }

  public function failedTasks(){
    return $this->cronTasks(IpReportsRequestsTasksModel::STATUS_ERROR);
  }

  public function cronTasksLogs() {
    $connection = Database::getConnection();

    //@TODO Move getting logs method to model
    $sth = $connection->select('ip_reports_requests_tasks_logs', 'logs');
    $sth->fields('logs', ['log_id', 'task_id', 'message', 'created_at']);
    //$sth->leftJoin('ip_reports_requests_tasks', 'links', 'logs.task_id = links.task_id');
    //$sth->addField('links', 'url', 'link_url');
    $sth->orderBy('logs.created_at', 'DESC');

    $pager = $sth->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                 ->limit(15);

    $result = $pager->execute();

    $rows = [];

    foreach ($result as $row => $content) {
      $task_link = Link::createFromRoute($content->task_id, 'ip_reports_request.cron_tasks.task_detail', ['task_id' => $content->task_id], ['absolute' => TRUE]);

      $rows[] = [
        $content->log_id,
        $task_link,
        $content->message,
        date('Y-m-d H:i:s', $content->created_at),
      ];
    }

    $header = [t('Log ID'), t('Task ID'), t('Message'), t('Date')];

    $build = [];

    $build[] = [
      '#prefix'        => '<h1>'.t('Logs').'</h1>',
      'table' => [
        '#theme' => 'table',
        '#attributes' => [
          'data-striping' => 0,
        ],
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t("No Logs Found")
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

  public function taskDetail($task_id) {
    $build = [];

    $task = IpReportsRequestsTasksModel::get($task_id);

    $build[] = [
      '#type' => 'markup',
      '#markup' => sprintf("<div>%s</div>", $this->t('<strong>ID:</strong> @id', [
        '@id' => $task_id,
      ]))
    ];

    $build[] = [
      '#type' => 'markup',
      '#markup' => sprintf("<div>%s</div>", $this->t('<strong>Task:</strong> @task', [
        '@task' => $task->task,
      ]))
    ];

    $build[] = [
      '#type' => 'markup',
      '#markup' => sprintf("<div>%s</div>", $this->t('<strong>Status:</strong> @status', [
        '@status' => $task->status,
      ]))
    ];

    $build[] = [
      '#type' => 'markup',
      '#markup' => sprintf("<div>%s</div>", $this->t('<strong>Created:</strong> @date', [
        '@date' => date('Y-m-d H:i:s', $task->created_at),
      ]))
    ];

    $build[] = [
      '#type' => 'markup',
      '#markup' => sprintf("<div>%s</div>", $this->t('<strong>Last Update:</strong> @date', [
        '@date' => date('Y-m-d H:i:s', $task->updated_at),
      ]))
    ];

    return $build;
  }

  public function taskDetailLogs($task_id) {
    $connection = Database::getConnection();

    $sth = $connection->select('ip_reports_requests_tasks_logs', 'logs');
    $sth->fields('logs', ['log_id', 'task_id', 'message', 'created_at']);
    $sth->condition('logs.task_id', $task_id);
//    $sth->leftJoin('ip_reports_requests_tasks', 'links', 'logs.task_id = links.task_id');
//    $sth->addField('links', 'url', 'link_url');
    $sth->orderBy('logs.created_at', 'DESC');

    $pager = $sth->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                 ->limit(15);

    $result = $pager->execute();

    $rows = [];

    foreach ($result as $row => $content) {
      $rows[] = [
        $content->log_id,
        $content->task_id,
        $content->message,
        date('Y-m-d H:i:s', $content->created_at),
      ];
    }

    $header = [t('Log ID'), t('Task ID'), t('Message'), t('Date')];

    $build = [];

    $build[] = [
      'table' => [
        '#theme' => 'table',
        '#attributes' => [
          'data-striping' => 0,
        ],
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t("No Logs Found")
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

}
