<?php

namespace Drupal\ip_react_app\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ip_react_app\Controller\IpReactAppUpdateCheckerController;
use Drupal\ip_settings\Controller\IpSettingsController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;


/**
 * Defines the content import form.
 */
class IpReactAppDownloadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_react_app_download_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['container'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Update React Application'),
    ];

    $last_time = IpReactAppUpdateCheckerController::getLastTimeBuilded();

    $form['container']['last-time-container'] = [
      '#type' => 'fieldgroup',
    ];

    $form['container']['last-time-container'][] = [
      '#type'          => 'label',
      '#title'         => t('Time of last updated build'),
      '#title_display' => t('Time of last updated build'),
    ];

    $form['container']['last-time-container'][] = [
      '#type'   => 'text',
      '#markup' => \Drupal::service('date.formatter')
                          ->format($last_time, 'custom', 'Y-m-d H:i:s'),
    ];

    $form['container']['force_update'] = [
      '#type'        => 'checkbox',
      '#title'       => $this->t('Force update?'),
      '#description' => $this->t('Use if you need force reinstall current react application'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Update'),
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

    $validation = TRUE;

    $config = \Drupal::config('ip.react_app.build');
    $config = $config->get();

    if (is_array($config)) {
      if (!isset($config['url']) || !$config['url']) {
        \Drupal::messenger()
               ->addError($this->t('Configuration missed. Please add ip.react_app.build.url to settings file.'));
        $validation = FALSE;
      }

      if (!isset($config['token']) || !$config['token']) {
        \Drupal::messenger()
               ->addError($this->t('Configuration missed. Please add ip.react_app.build.token to settings file.'));
        $validation = FALSE;
      }

      $react_settings = IpSettingsController::getReactSettings();

      $domain = $react_settings->get('react_domain');

      if (!$domain) {
        \Drupal::logger('ip_react_app')
               ->error($this->t('Configuration missed. Please add react domain to admin-system.'));
      }

      if (!isset($config['local_dir']) || !$config['local_dir']) {
        \Drupal::messenger()
               ->addError($this->t('Configuration missed. Please add ip.react_app.build.local_dir to settings file.'));
        $validation = FALSE;
      }
    }
    else {
      $validation = FALSE;
    }

    if (!$validation) {
      return;
    }

    $response = IpReactAppUpdateCheckerController::checkBuildUpdates($config['url'], $config['token'], $domain);

    $force_update = $form_state->getValue('force_update');

    // installed build time
    $last_time = IpReactAppUpdateCheckerController::getLastTimeBuilded();

    // response build time
    $response_build_time = (isset($response['site']['last_update']) && $response['site']['last_update'] > 0) ? \Drupal::service('date.formatter')
                                                                                                                      ->format($response['site']['last_update'], 'custom', 'Y-m-d H:i:s') : t('Not Found!');

    if ((($response['site']['last_update'] > 0) && ($response['site']['last_update'] != $last_time)) || $force_update) {
      $errors = FALSE;

      if (isset($response['build_file']) && $response['build_file']) {
        $uri = $config['url'] . $response['build_file'];

        if (IpReactAppUpdateCheckerController::updateBuild($uri, $config) === FALSE) {
          $errors = TRUE;
        }
        elseif ($response['site']['last_update'] != $last_time) {
          IpReactAppUpdateCheckerController::saveLastTimeBuilded($response['site']['last_update']);
        }
      }
      else {
        $errors = TRUE;
      }

      if ($errors) {
        \Drupal::messenger()
               ->addError($this->t('Some error occurred while saving react app build file.'));
      }
      else {
        \Drupal::messenger()
               ->addStatus($this->t('React application successfully updated for domain @domain.', ['@domain' => $domain]));
        \Drupal::messenger()
               ->addStatus($this->t('Application time on build server: @time', ['@time' => $response_build_time]));
      }
    }
    else {
      \Drupal::messenger()
             ->addStatus($this->t('You already installed last version of react application for domain @domain.', ['@domain' => $domain]));
      \Drupal::messenger()
             ->addStatus($this->t('Application from builder service with time: @time', ['@time' => $response_build_time]));
    }
  }
}
