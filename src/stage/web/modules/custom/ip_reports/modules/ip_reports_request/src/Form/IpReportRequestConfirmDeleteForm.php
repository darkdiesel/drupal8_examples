<?php

namespace Drupal\ip_reports_request\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ip_reports_request\Model\IpReportsRequestsModel;
use Drupal\ip_reports_request\Controller\IpReportRequestCalculationServiceController;

class IpReportRequestConfirmDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_reports_submit_report_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $report_id = \Drupal::routeMatch()->getParameter('report_id');

    $report = IpReportsRequestsModel::get($report_id);

    if (!$report) {
      \Drupal::messenger()
             ->addError(t('Report not founded.'));

      $form_state->setRedirect('ip_reports_request.reports');
    }

    if ($report) {

      $form['report_options_container'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('Delete report'),
        '#open'  => TRUE,
      ];

      $form['report_options_container']['text'] = [
        '#type'   => 'markup',
        '#markup' => t('Are you sure want to delete this report?'),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['cancel'] = [
      '#type'          => 'link',
      '#title'         => $report ? t('Cancel') : t('To Reports List'),
      '#title_display' => 'invisible',
      '#url'           => $report ?
        Url::fromRoute('ip_reports_request.reports.detail', ['report_id' => $report_id], ['absolute' => TRUE]) :
        Url::fromRoute('ip_reports_request.reports', [], ['absolute' => TRUE]),
      '#attributes'    => [
        'class' => ['button', 'form-submit'],
      ],
    ];

    if ($report) {

      $form['actions']['submit'] = [
        '#type'  => 'submit',
        '#value' => $this->t('Delete Report'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $report_id = \Drupal::routeMatch()->getParameter('report_id');

    $report_request_service = new IpReportRequestCalculationServiceController();

    //get report data to delete from calculation service
    $report = IpReportsRequestsModel::get($report_id);

    // send request to delete report from calculation service
    $report_request_service->deleteReport($report->report_node_id, $report->remote_report_id);

    // delete report in our DB
    $report = IpReportsRequestsModel::delete($report_id);

    if ($report != FALSE) {
      \Drupal::messenger()
             ->addMessage(t('Report successfully deleted.'));

      $form_state->setRedirect('ip_reports_request.reports');
    }
    else {
      \Drupal::messenger()
             ->addError(t('Some error occurred.'));
      return FALSE;
    }
  }
}
