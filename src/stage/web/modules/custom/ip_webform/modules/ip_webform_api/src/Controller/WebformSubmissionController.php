<?php

namespace Drupal\ip_webform_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Defines WebformSubmissionController class.
 */
class WebformSubmissionController extends ControllerBase {
  private static $container_types = [
    'container',
    'category_container',
    'webform_wizard_page',
  ];

  private static $exclude_progress = [
    'markup',
    'webform_markup'
  ];

  /**
   * @param $type
   *
   * @return bool
   */
  public static function is_category_type($type){
    return $type === 'category_container';
  }

  /**
   * Check if element is category type and basic
   *
   * @param $element
   *
   * @return bool
   */
  public static function is_category_basic($element, $elements){
    if (self::is_category_type($element['#type'])) {
      if ((isset($element['#basic_category']) && $element['#basic_category'])) {
        return TRUE;
      } else {
        return FALSE;
      }
    }
    else {
      if (isset($element['#webform_parent_key']) && $element['#webform_parent_key']) {
        return self::is_category_basic($elements[$element['#webform_parent_key']], $elements);
      }
      else {
        return FALSE;
      }
    }
  }

  /**
   * Return array of container type elements
   *
   * @return array
   */
  public static function getContainerElements(){
    return self::$container_types;
  }

  /**
   * Return array of elements that should be excluded from calculation progress
   *
   * @return array
   */
  public static function getExcludedCalculationElements(){
    return self::$exclude_progress;
  }

  /**
   * Calculate progress for webform
   *
   * @param $elements
   * @param $data
   * @param array $progress
   *
   * @return array
   */
  public static function calculateProgress(
    $elements, $data, $progress = [
    'form' => [
      'filled' => 0,
      'count'  => 0,
      'filled_required' => 0,
      'count_required' => 0,
    ],
    'basic' => [
      'filled' => 0,
      'count'  => 0,
      'filled_required' => 0,
      'count_required' => 0,
    ],
    'elements' => [],
  ]
  ) {
    $basic_categories = [];

    foreach ($elements as $key => $element) {
      if (in_array($element['#type'], self::getExcludedCalculationElements())){
        continue;
      }

      if (in_array($element['#type'], self::getContainerElements())) {
        // create container element in array for calculation progress for it
        if(!isset($progress['elements'][$key])) {
          $progress['elements'][$key] = [
            'filled' => 0,
            'count'  => 0,
            'filled_required' => 0,
            'count_required' => 0,
          ];

          if (self::is_category_basic($element, $elements)) {
            $basic_categories[] = $key;
          }
        }
      } else {
        $required = isset($element['#required']) ? $element['#required'] : FALSE;
        $filled = (isset( $data[$key]) && ($data[$key] != ""));

        $progress['form']['count'] += 1;

        if ($required) {
          $progress['form']['count_required'] += 1;
        }

        if ($filled) {
          $progress['form']['filled'] += 1;

          if ($required) {
            $progress['form']['filled_required'] += 1;
          }
        }

        if ($element['#webform_parent_key'] && $element['#webform_parent_key']) {
          if (self::is_category_basic($elements[$element['#webform_parent_key']], $elements)){
            $progress['basic']['count'] += 1;

            if ($required) {
              $progress['basic']['count_required'] += 1;
            }

            if ($filled) {
              $progress['basic']['filled'] += 1;

              if ($required) {
                $progress['basic']['filled_required'] += 1;
              }
            }
          }

          self::updateContainerProgress($progress, $elements, $element['#webform_parent_key'], $filled, $required);
        }
      }
    }
    $elements_progress = $progress['elements'];

    $progress['elements'] = [];

    foreach ($elements_progress as $key => $element_progress) {
      $progress['elements'][] = array_merge(['key' => $key], $element_progress);
    }

    return $progress;
  }

  /**
   * Update progress recursive for relative parent containers
   *
   * @param array $progress
   * @param array $elements
   * @param int $parent_id
   * @param bool $filled
   * @param bool $required
   */
  static function updateContainerProgress(&$progress, $elements, $parent_id, $filled = FALSE, $required = FALSE) {
    if (!isset($progress['elements'][$parent_id])) {
      $progress['elements'][$parent_id] = [
        'filled' => 0,
        'count'  => 0,
        'filled_required' => 0,
        'count_required' => 0,
      ];
    }

    $progress['elements'][$parent_id]['count'] += 1;

    if ($required) {
      $progress['elements'][$parent_id]['count_required'] += 1;
    }

    if ($filled) {
      $progress['elements'][$parent_id]['filled'] += 1;
      if ($required) {
        $progress['elements'][$parent_id]['filled_required'] += 1;
      }
    }

    if (isset($elements[$parent_id]['#webform_parent_key']) && $elements[$parent_id]['#webform_parent_key']) {
      self::updateContainerProgress($progress, $elements, $elements[$parent_id]['#webform_parent_key'], $filled, $required);
    }
  }

  /**
   *
   * Programmatically validate submission
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   * @param bool $validate_only
   *
   * @return array
   *
   * @see \Drupal\webform\WebformSubmissionForm::submitWebformSubmission for source of this method
   */
  public static function validateSubmission(WebformSubmissionInterface $webform_submission, $validate_only = TRUE){
    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = \Drupal::entityTypeManager()->getFormObject('webform_submission', 'api');
    $form_object->setEntity($webform_submission);

    // Create an empty form state which will be populated when the submission
    // form is submitted.
    $form_state = new FormState();

    // Set the triggering element to an empty element to prevent
    // errors from managed files.
    // @see \Drupal\file\Element\ManagedFile::validateManagedFile
    $form_state->setTriggeringElement(['#parents' => []]);

    // Get existing error messages.
    $error_messages = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_ERROR);

    // Submit the form.
    \Drupal::formBuilder()->submitForm($form_object, $form_state);

    // Get the errors but skip drafts.
    $errors = ($webform_submission->isDraft() && !$validate_only) ? [] : $form_state->getErrors();

    $warnings = $form_state->get('warnings');

    // Delete all form related error messages.
    \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);

    // Restore existing error message.
    foreach ($error_messages as $error_message) {
      \Drupal::messenger()->addError($error_message);
    }

    $response = [];

    if ($errors) {
      $response['errors'] = $errors;
    }
    if ($warnings) {
      $response['warnings'] = $warnings;
    }

    return $response;
  }
}
