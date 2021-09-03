<?php

namespace Drupal\ip_reports_request\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ip_reports_request\Controller\IpReportRequestController;
use Drupal\ip_reports_request\Model\IpReportsRequestsModel;
use Drupal\ip_reports_request\Controller\IpReportRequestCalculationServiceController;

/**
 * Defines the content import form.
 */
class IpReportRequestReportForm extends FormBase {
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


    $form['report_options_container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Report options'),
      '#open' => TRUE,
    ];

    $form['report_options_container']['Message'] = [
      '#type' => 'markup',
      '#markup' => '<h1>Manually creating reports temporary do not work</h1>'
    ];

    $form['report_options_container']['report'] = [
      '#type' => 'select',
      '#title' => $this->t('Report'),
      '#description' => $this->t('Select report structure'),
      '#default_value' => '',
      '#options' => $this->get_reports(),
      '#required' => TRUE,
    ];

    $form['report_options_container']['organisation'] = [
      '#type' => 'select',
      '#title' => $this->t('Organisation'),
      '#description' => $this->t('Select organisation'),
      '#default_value' => '',
      '#options' => $this->get_organisations(),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Create report'),
    ];

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
    $report_id = $form_state->getValue('report');
    $org_id = $form_state->getValue('organisation');

    $conditions = [
      'organisation_id' => $org_id
    ];

    $report_request_service = new IpReportRequestCalculationServiceController();

    $result = $report_request_service->createReport($report_id, $conditions);

    if ($result !== FALSE) {
      if (isset($result['success']) && $result['success']) {

        if (isset($result['report']['status']) && isset($result['report']['id'])) {
          // save report to db
          $report = IpReportsRequestsModel::add($report_id, $result['report']['id'], $conditions);

          if (!$report){
            \Drupal::messenger()
                   ->addError(t('Some error occurred.') );

            return TRUE;
          }

          \Drupal::messenger()
                 ->addMessage(t('Report requested successfully.') );

          $status = strtolower($result['report']['status']);

          switch ($status) {
            case IpReportsRequestsModel::STATUS_PENDING:
              IpReportsRequestsModel::setStatusPending($report);
              break;
            case IpReportsRequestsModel::STATUS_PROCESSING:
              IpReportsRequestsModel::setStatusProcessing($report);
              break;
            case IpReportsRequestsModel::STATUS_ERROR:
              IpReportsRequestsModel::setStatusError($report);
              break;
            case IpReportsRequestsModel::STATUS_FINISHED:
              $site_id = $report_request_service->getSiteID();

              IpReportsRequestsModel::update($report, ['result' => IpReportRequestController::buildSerializedResults($report_id, $result['report']['id'], $site_id)]);
              IpReportsRequestsModel::setStatusFinish($report);
              break;
            default:
              break;
          }

          $form_state->setRedirect('ip_reports_request.reports');
        }
      }
    } else {
      \Drupal::messenger()
             ->addError(t('Some error occurred.') );
      return FALSE;
    }
  }

  /**
   * Returned array of nodes with report content type
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function get_reports(){
    $report_node_properties = [
      'type' => 'report'
    ];

    $report_nodes = \Drupal::entityTypeManager()
                           ->getStorage('node')
                           ->loadByProperties($report_node_properties);

    $report_options = [];

    foreach ($report_nodes as $report) {
      $report_options[$report->id()] = $report->getTitle();
    }

    return $report_options;
  }

  /**
   * Returned array of nodes with organisation content type
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function get_organisations(){
    $organisation_node_properties = [
      'type' => 'organisation'
    ];

    $organisation_nodes = \Drupal::entityTypeManager()
                           ->getStorage('node')
                           ->loadByProperties($organisation_node_properties);

    $organisation_options = [];

    foreach ($organisation_nodes as $organisation) {
      $organisation_options[$organisation->id()] = $organisation->getTitle();
    }

    return $organisation_options;
  }
}
