<?php

namespace Drupal\ip_reports_request\Controller;

use Complex\Exception;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\ip_reports_request\Model\IpReportsRequestsModel;
use Drupal\ip_reports_request\Controller\IpReportRequestCalculationServiceController;

/**
 * Defines IpReportRequestListController class.
 */
class IpReportRequestListController extends ControllerBase {

  private $labels = [

  ];

  function __construct() {
    $this->labels = [
      'pdf_url' => t('Download PDF'),
      'html_url' => t('Download HTML'),
      'docx_url' => t('Download DOCX'),
    ];
  }

  public function reportsList() {
    // get reports list
    $reports = IpReportsRequestsModel::getReportsLimit();

    $build['reports_table'] = [
      '#type' => 'table',
      '#prefix'     => '<h1>' . t('Reports') . '</h1>',
      '#caption' => $this->t('list of requested reports'),
      '#header' => [
        $this->t('ID'),
        $this->t('Report Node'),
        //t('Conditions'),
        $this->t('Status'),
        $this->t('Created'),
        $this->t('Last Update'),
        '',
      ],
      '#empty'      => $this->t("No Results Found")
    ];

    foreach ($reports as $report) {
      $build['reports_table'][$report->report_id]['id'] =  [
        '#type' => 'link',
        '#title' => $report->report_id,
        '#title_display' => 'invisible',
        '#url' => Url::fromRoute('ip_reports_request.reports.detail', ['report_id' => $report->report_id], ['absolute' => TRUE]),
      ];

      $build['reports_table'][$report->report_id]['report_node'] = [
        '#type' => 'link',
        '#title' => $report->report_node_id,
        '#title_display' => 'invisible',
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $report->report_node_id], ['absolute' => TRUE]),
      ];

      $build['reports_table'][$report->report_id]['status'] = [
        '#type' => 'markup',
        '#markup' => $report->status
      ];

      $build['reports_table'][$report->report_id]['created_at'] = [
        '#type' => 'markup',
        '#markup' => date('Y-m-d H:i:s', $report->created_at)
      ];

      $build['reports_table'][$report->report_id]['updated_at'] = [
        '#type' => 'markup',
        '#markup' => date('Y-m-d H:i:s', $report->updated_at)
      ];

      $build['reports_table'][$report->report_id]['actions'] =  [
        '#type' => 'link',
        '#title' => $this->t('Detail'),
        '#title_display' => 'invisible',
        //'#url' => Url::fromRoute('entity.node.canonical', ['node' => $report->report_node_id], ['absolute' => TRUE]),
        '#url' => Url::fromRoute('ip_reports_request.reports.detail', ['report_id' => $report->report_id], ['absolute' => TRUE]),
        '#attributes' => [
          'class' => ['button', 'button--primary']
        ]
      ];
    }

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  public function reportDetail($report_id) {
    $report = IpReportsRequestsModel::get($report_id);
    $build = [];

    if (!$report) {
      return $build[] = [
        '#type'   => 'markup',
        '#markup' => sprintf("<div>%s</div>", $this->t('Report not founded!')),
      ];
    };

    $build['report'] = [
      '#type' => 'fieldset',
      '#title' => t('Data'),
    ];

    // report id
    $build['report']['group-id'] = [
      '#type'   => 'fieldgroup',
    ];

    $build['report']['group-id'][] = [
      '#type'   => 'label',
      '#title' => t('ID'),
      '#title_display' => t('ID'),
    ];

    $build['report']['group-id'][] = [
      '#type'   => 'text',
      '#markup' => $report->report_id,
    ];

    // report structure
    $build['report']['group-report-structure'] = [
      '#type'   => 'fieldgroup',
    ];

    $build['report']['group-report-structure'][] = [
      '#type'   => 'label',
      '#title' => t('Report structure'),
      '#title_display' => t('Report structure'),
    ];

    $node = Node::load($report->report_node_id);

    if ($node instanceof Node) {
      $build['report']['group-report-structure'][] = [
        '#type'   => 'link',
        '#title' => $node->getTitle(),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $report->report_node_id], ['absolute' => TRUE]),
      ];
    }

    // report id
    $build['report']['group-status'] = [
      '#type'   => 'fieldgroup',
    ];

    $build['report']['group-status'][] = [
      '#type'   => 'label',
      '#title' => t('Status'),
      '#title_display' => t('Status'),
    ];

    $build['report']['group-status'][] = [
      '#type'   => 'text',
      '#markup' => $report->status,
    ];

    // report created
    $build['report']['group-created'] = [
      '#type'   => 'fieldgroup',
    ];

    $build['report']['group-created'][] = [
      '#type'   => 'label',
      '#title' => t('Created'),
      '#title_display' => t('Created'),
    ];

    $build['report']['group-created'][] = [
      '#type'   => 'text',
      '#markup' => date('Y-m-d H:i:s', $report->created_at),
    ];

    // report updated
    $build['report']['group-updated'] = [
      '#type'   => 'fieldgroup',
    ];

    $build['report']['group-updated'][] = [
      '#type'   => 'label',
      '#title' => t('Last Update'),
      '#title_display' => t('Last Update'),
    ];

    $build['report']['group-updated'][] = [
      '#type'   => 'text',
      '#markup' => date('Y-m-d H:i:s', $report->updated_at),
    ];

    if ($report->result){
      $result = unserialize($report->result, ['allowed_classes' => false]);

      if (is_array($result) && count($result) ) {
        $build['links'] = [
          '#type' => 'fieldset',
          '#title' => t('Links'),
        ];

        $reportRequest = new IpReportRequestCalculationServiceController();
        $base_url = $reportRequest->getBaseUrl();

        foreach ($result as $key =>  $url) {
          $build['links'][$key] = [
            '#type' => 'link',
            '#title' => $this->labels[$key] ? $this->labels[$key] : $key,
            '#title_display' => 'invisible',
            '#url' => Url::fromUri($base_url . $url),
            '#attributes' => [
              'class' => ['button', 'button--primary']
            ]
          ];
        }
      }
    }

    $build['actions'] = [
      '#type' => 'actions'
    ];

    $build['actions']['delete'] = [
      '#type' => 'link',
      '#title' => t('Delete Report'),
      '#title_display' => 'invisible',
      '#url' => Url::fromRoute('ip_reports_request.delete_report', ['report_id' => $report_id], ['absolute' => TRUE]),
      '#attributes' => [
        'class' => ['button']
      ]
    ];

    return $build;
  }
}
