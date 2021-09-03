<?php

namespace Drupal\ip_webform\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\ip_webform\Model\IpWebFormLogsModel;

/**
 * Defines the content import form.
 */
class IpWebformEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_webform_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $webform_id = \Drupal::routeMatch()->getParameter('webform');
    $webfrom_storage = \Drupal::entityTypeManager()->getStorage('webform');

    $query = $webfrom_storage->getQuery();
    $query->condition('id', $webform_id);
    $entity_ids = $query->execute();

    $webform = false;
    if (!empty($entity_ids)) {
      $webform = $webfrom_storage->load(reset($entity_ids));
    }

    $elements = $webform->getElementsDecoded();

    $form['elements'] = [
      '#type' => 'table',
      '#caption' => $this->t('Elements'),
      '#header' => [
        $this->t('Name'),
        $this->t('Type'),
        $this->t('Required'),
        $this->t('Action')
      ]
    ];

    foreach ($elements as $key => $element) {
      $form['elements'][$key]['name'] = [
        '#type' => 'html_tag',
        '#value' => $element['#title'],
        '#tag' => 'span'
      ];

      $form['elements'][$key]['type'] = [
        '#type' => 'html_tag',
        '#value' => $element['#type'],
        '#tag' => 'span'
      ];

      $form['elements'][$key]['required'] = [
        '#type' => 'html_tag',
        '#value' => isset($element['#required']) ? $this->t($element['#required'] == true ? 'Yes' : 'No') : $this->t('Not defined'),
        '#tag' => 'span'
      ];

      $form['elements'][$key]['action'] = ['#type' => 'html_tag',
        '#value' => '',
        '#tag' => 'span'
      ];

      if($key == 'user_custom') {
        foreach($element as $user_key => $user_element) {
          if($user_key[0] != '#') {
            $form['elements'][$user_key]['name'] = [
              '#type' => 'html_tag',
              '#value' => '- ' . $user_element['#title'],
              '#tag' => 'span'
            ];

            $form['elements'][$user_key]['type'] = [
              '#type' => 'html_tag',
              '#value' => $user_element['#type'],
              '#tag' => 'span'
            ];

            $form['elements'][$user_key]['required'] = [
              '#type' => 'html_tag',
              '#value' => isset($user_element['#required']) ? $this->t($user_element['#required'] == TRUE ? 'Yes' : 'No') : $this->t('Not defined'),
              '#tag' => 'span'
            ];

            if(\Drupal::currentUser()->hasPermission('delete ip webform field')) {
              $form['elements'][$user_key]['action'] = [
                '#type' => 'link',
                '#title' => $this->t('Delete'),
                '#title_display' => 'invisible',
                '#url' => Url::fromUri('internal:/user/webform_dashboard/' . $webform->id() . '/elements/' . $user_key . '/delete'),
                '#attributes' => [
                  'class' => ['btn', 'btn-primary']
                ]
              ];
            }
          }
        }
      }
    }

    if(\Drupal::currentUser()->hasPermission('add ip webform field')) {
      if (!isset($elements['user_custom']) || count($elements['user_custom']) < 7) {
        $form['header'] = [
          '#type' => 'html_tag',
          '#value' => $this->t('Add new element'),
          '#tag' => 'h4'
        ];

        $form['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Name'),
          '#required' => TRUE
        ];

        $form['type'] = [
          '#type' => 'select',
          '#options' => [
            'textfield' => $this->t('Textfield'),
            'checkbox' => $this->t('Yes/No'),
            'webform_likert' => $this->t('Likert')
          ],
          '#required' => TRUE
        ];

        $form['required'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Is required'),
          '#value' => 1
        ];

        $form['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Create element'),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Maybe need in future
  }

  public function slug_name($name) {
    $name = str_replace(' ', '_', $name);
    $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    $name = preg_replace('/_{2,}/', '_', $name);

    return strtolower($name);
  }

  public function array_insert_before($key, array &$array, $new_key, $new_value) {
    if (array_key_exists($key, $array)) {
      $new = array();
      foreach ($array as $k => $value) {
        if ($k === $key) {
          $new[$new_key] = $new_value;
        }
        $new[$k] = $value;
      }
      return $new;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $webform_id = \Drupal::routeMatch()->getParameter('webform');
    $webfrom_storage = \Drupal::entityTypeManager()->getStorage('webform');

    $query = $webfrom_storage->getQuery();
    $query->condition('id', $webform_id);
    $entity_ids = $query->execute();

    $webform = false;
    if (!empty($entity_ids)) {
      $webform = $webfrom_storage->load(reset($entity_ids));
    }

    $elements = $webform->getElementsDecoded();
    $type = \Drupal::request()->request->get('type');

    if(isset($elements['user_custom'])) {
      $fieldset = $elements['user_custom'];
      unset($elements['user_custom']);
    }
    else {
      $fieldset = [
        '#type' => 'fieldset',
        '#title' => 'User custom',
        '#title_display' => 'invisible'
      ];
    }

    if($type != 'webform_likert') {
      $fieldset['user_custom_' . $this->slug_name(\Drupal::request()->request->get('name'))] = [
        '#title' => \Drupal::request()->request->get('name'),
        '#type' => \Drupal::request()->request->get('type'),
        '#required' => \Drupal::request()->request->get('required')
      ];
    }
    else {
      $fieldset['user_custom_' . $this->slug_name(\Drupal::request()->request->get('name'))] = [
        '#title' => \Drupal::request()->request->get('name'),
        '#title_display' => 'invisible',
        '#type' => \Drupal::request()->request->get('type'),
        '#required' => \Drupal::request()->request->get('required'),
        '#answers' => 'likert_would_you',
        '#questions' => [
          \Drupal::request()->request->get('name') => \Drupal::request()->request->get('name')
        ]
      ];
    }

    $elements = $this->array_insert_before('actions', $elements, 'user_custom', $fieldset);

    $webform->setElements($elements);
    $webform->save();

    return;
  }
}
