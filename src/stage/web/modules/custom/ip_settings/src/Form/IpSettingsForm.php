<?php

namespace Drupal\ip_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure api settings for sending request to laravel.
 */
class IpSettingsForm extends ConfigFormBase {

  /**
   * Config laravel settings.
   *
   * @var string
   */
  const LARAVEL_KEY = 'laravel.settings';

  /**
   * Config react settings.
   *
   * @var string
   */
  const REACT_KEY = 'ip.react_app.build';

  /**
   * Config API settings.
   *
   * @var string
   */
  const API_SETTINGS = 'ip.webform.api.settings';

  /**
   * Config settings.
   *
   * @var string
   */
  const REACT_SETTINGS = 'ip.react.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::LARAVEL_KEY,
      static::REACT_KEY,
      static::API_SETTINGS,
      static::REACT_SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config_laravel = $this->config(static::LARAVEL_KEY);
    $config_react = $this->config(static::REACT_KEY);
    $config_webform_api = $this->config(static::API_SETTINGS);
    $config_react_app = $this->config(static::REACT_SETTINGS);

    $form['ip_calculation_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Ip calculate application settings'),
    ];

    $form['ip_calculation_settings']['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Calculation URL'),
      '#description' => $this->t('Calculation Service API Address'),
      '#required' => TRUE,
      '#default_value' => $config_laravel->get('base_url') ?? 'https://api.cviewsapp.nl/api',
    ];

    $form['ip_calculation_settings']['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site ID'),
      '#description' => $this->t('Unique site id that used for requesting to calculation service.'),
      '#required' => TRUE,
      '#default_value' => $config_webform_api->get('site_id'),
    ];

    $form['ip_react_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toota react build application settings'),
    ];

    $form['ip_react_settings']['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Build URL'),
      '#description' => $this->t('React Build Service API Address'),
      '#required' => TRUE,
      '#default_value' => $config_react->get('url') ?? 'https://build.cviewsapp.nl',
    ];

    $form['ip_react_settings']['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#description' => $this->t('React Build Service Token'),
      '#required' => TRUE,
      '#default_value' => $config_react->get('token') ?? '9bJdVSbgBcrBsV8S',
    ];

    $form['ip_react_settings']['local_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local Directory'),
      '#description' => $this->t('React Build Service API Local Directory'),
      '#required' => TRUE,
      '#default_value' => $config_react->get('local_dir') ?? 'reports_ui',
    ];

    $form['ip_react_settings']['react_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#description' => $this->t('Domain will be used for react application for this site'),
      '#required' => TRUE,
      '#default_value' => $config_react_app->get('react_domain'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->configFactory->getEditable(static::LARAVEL_KEY)
      ->set('base_url', $form_state->getValue('base_url'))
      ->save();

    $this->configFactory->getEditable(static::REACT_KEY)
      ->set('url', $form_state->getValue('url'))
      ->set('token', $form_state->getValue('token'))
      ->set('local_dir', $form_state->getValue('local_dir'))
      ->save();

    $this->configFactory->getEditable(static::API_SETTINGS)
      ->set('site_id', $form_state->getValue('site_id'))
      ->save();

    $this->configFactory->getEditable(static::REACT_SETTINGS)
      ->set('react_domain', $form_state->getValue('react_domain'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
