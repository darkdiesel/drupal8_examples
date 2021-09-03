<?php

namespace Drupal\ip_webform_hard_soft_validation;

class WebformCustomValidationRulesValidator {

  public static function validateElement($element, $submission_data) {
    $validation = [
      'soft' => [],
      'hard'   => [],
    ];

    $validation_rules = [];

    if (isset($element['#validation']['validation_rules'])) {
      $validation_rules = $element['#validation']['validation_rules'];
    }

    if (is_array($validation_rules) && count($validation_rules)) {

      $element_value = $submission_data[$element['#webform_key']];

      foreach ($validation_rules as $validation_rule) {
        extract($validation_rule);

        switch ($trigger) {
          case 'empty':
            $result = ($element_value !== '');

            if ($result) {
              $validation[$type][] = self::getTriggerMessage($element, $element_value, $value, $type, $trigger, $message);
            }
            break;
          case 'value':
            $result = ($element_value !== '' && floatval($value) == floatval($element_value));

            if (!$result) {
              $validation[$type][] = self::getTriggerMessage($element, $element_value, $value, $type, $trigger, $message);
            }
            break;
          case '!value':
            $result = ($element_value !== '' && floatval($value) == floatval($element_value));

            if ($result) {
              $validation[$type][] = self::getTriggerMessage($element, $element_value, $value, $type, $trigger, $message);
            }
            break;
        }
      }
    }

    return $validation;
  }

  public static function getTriggerMessage($element, $element_value, $value, $type, $trigger, $message = null) {

    if ($message) {
      $validation_message = $message;
    } else {
      $messages = self::getTriggerMessages();

      $validation_message = $messages[$type][$trigger];
    }

    $replace_key = [
      '%element_value',
      '%value',
      '%name'
    ];

    $replace_value = [
      $element_value,
      $value,
      $element['#title']
    ];

    return $validation_message = str_replace($replace_key, $replace_value, $validation_message);
  }

  public static function getTriggerMessages() {
    return [
      'soft' => [
        'empty' => t('You enter value %element_value for %name element but recommended left empty.'),
        'filled' => t('Filled'),
        'checked' => t('Checked'),
        'unchecked' => t('Unchecked'),
        'value' => t('You enter %element_value for %name element but recommended value is equal %value.'),
        '!value' => t('You enter %element_value for %name element but recommended value is not equal %value.'),
        'pattern' => t('Pattern'),
        '!pattern' => t('Not Pattern'),
        'less' => t('Less than'),
        'greater' => t('Greater than'),
      ],
      'hard' => [
        'empty' => t('Element %name should be empty.'),
        'filled' => t('Filled'),
        'checked' => t('Checked'),
        'unchecked' => t('Unchecked'),
        'value' => t('Value %element_value for %name element should be equal %value.'),
        '!value' => t('Value %element_value for %name element should not be equal %value.'),
        'pattern' => t('Pattern'),
        '!pattern' => t('Not Pattern'),
        'less' => t('Value %element_value for %name element should be less than %value.'),
        'greater' => t('Value %element_value for %name element should be greater than %value.'),
      ]
    ];
  }
}
