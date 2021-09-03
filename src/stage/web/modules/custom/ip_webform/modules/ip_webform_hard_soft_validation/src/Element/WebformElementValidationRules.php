<?php

namespace Drupal\ip_webform_hard_soft_validation\Element;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformAccessibilityHelper;
use Drupal\webform\Utility\WebformArrayHelper;
use Drupal\webform\Utility\WebformYaml;

/**
 * Provides a webform element to edit an element's validation rules.
 *
 * @FormElement("webform_element_validation_rules")
 */
class WebformElementValidationRules extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
//      '#empty_states' => 3,
      '#process' => [
        [$class, 'processWebformRules'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#multiple' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE) {
      if (isset($element['#default_value'])) {
        if (is_string($element['#default_value'])) {
          $default_value = Yaml::decode($element['#default_value']);
        }
        else {
          $default_value = $element['#default_value'] ?: [];
        }
        return static::convertFormApiRulesToRulesArray($default_value);
      }
      else {
        return [];
      }
    }
    elseif (is_array($input) && isset($input['validation_rules'])) {
      return (is_string($input['validation_rules'])) ? Yaml::decode($input['validation_rules']) : static::convertFormValuesToRulesArray($input['validation_rules']);
    }
    else {
      return [];
    }
  }

  /**
   * Expand an email confirm field into two HTML5 email elements.
   */
  public static function processWebformRules(&$element, FormStateInterface $form_state, &$complete_form) {
    $element += [
      '#validation_type_options' => static::getValidationTypeOptions(),
      '#trigger_options' => static::getTriggerOptions(),
    ];

    $element['#validation_type_options_flattened'] = OptGroup::flattenOptions($element['#validation_type_options']);
    $element['#trigger_options_flattened'] = OptGroup::flattenOptions($element['#trigger_options']);

    $element['#tree'] = TRUE;

    $edit_source = $form_state->get(static::getStorageKey($element, 'edit_source'));

    // Add validate callback that extracts the associative array of states.
    $element += ['#element_validate' => []];
    array_unshift($element['#element_validate'], [get_called_class(), 'validateWebformElementValidationRules']);

    $warning_message = static::isDefaultValueCustomizedFormApiRules($element);
    if ($warning_message || $edit_source) {
      if ($warning_message) {
        $warning_message .= ' ' . t('Form API #states must be manually entered.');
        $element['warning_messages'] = [
          '#type' => 'webform_message',
          '#message_type' => 'warning',
          '#message_message' => $warning_message,
        ];
      }

      if ($edit_source) {
        $element['edit_source_message'] = [
          '#type' => 'webform_message',
          '#message_message' => t('Creating custom conditional logic (Form API #states) with nested conditions or custom selectors will disable the conditional logic builder. This will require that Form API #states be manually entered.'),
          '#message_type' => 'info',
          '#message_close' => TRUE,
          '#message_storage' => WebformMessage::STORAGE_SESSION,
        ];
      }

      $element['validation_rules'] = [
        '#type' => 'webform_codemirror',
        '#title' => t('Conditional Logic (YAML)'),
        '#title_display' => 'invisible',
        '#mode' => 'yaml',
        '#default_value' => WebformYaml::encode($element['#default_value']),
        '#description' => t('Learn more about Drupal\'s <a href=":href">Form API #rules</a>.', [':href' => 'https://www.lullabot.com/articles/form-api-states']),
        '#webform_element' => TRUE,
        '#more_title' => t('Help'),
        '#more' => static::buildSourceHelp($element),
      ];
      return $element;
    }

    $table_id = implode('_', $element['#parents']) . '_table';

    // Store the number of rows.
    $storage_key = static::getStorageKey($element, 'number_of_rows');
    if ($form_state->get($storage_key) === NULL) {
      if (empty($element['#default_value']) || !is_array($element['#default_value'])) {
        $number_of_rows = 2;
      }
      else {
        $number_of_rows = count($element['#default_value']);
      }
      $form_state->set($storage_key, $number_of_rows);
    }
    $number_of_rows = $form_state->get($storage_key);

    // DEBUG: Disable Ajax callback by commenting out the below callback and
    // wrapper.
    $ajax_settings = [
      'callback' => [get_called_class(), 'ajaxCallback'],
      'wrapper' => $table_id,
      'progress' => ['type' => 'none'],
    ];

    $header = [
      ['data' => t('Validation Type'), 'width' => '25%'],
      ['data' => t('Validation Message'), 'width' => '50%'],
      ['data' => t('Validation Rule'), 'width' => '25%'],
      ['data' => WebformAccessibilityHelper::buildVisuallyHidden(t('Operations'))],
    ];

    // Get states and number of rows.
    if (($form_state->isRebuilding())) {
      $rules = $element['#value'];
    }
    else {
      $rules = (isset($element['#default_value'])) ? static::convertFormApiRulesToRulesArray($element['#default_value']) : [];
    }

    // Track state row indexes for disable/enabled warning message.
    $rule_row_indexes = [];

    // Build state and conditions rows.
    $row_index = 0;
    $rows = [];
    foreach ($rules as $rule_settings) {
      $rows[$row_index] = static::buildRuleRow($element, $rule_settings, $table_id, $row_index, $ajax_settings);
      $rule_row_indexes[] = $row_index;
      $row_index++;
    }

    // Generator empty state with conditions rows.
    if ($row_index < $number_of_rows) {
      $rows[$row_index] = static::buildRuleRow($element, ['type' => 'soft'], $table_id, $row_index, $ajax_settings);
      $rule_row_indexes[] = $row_index;
      $row_index++;
    }

    // Add wrapper to the element.
    $element += ['#prefix' => '', '#suffix' => ''];
    $element['#prefix'] = '<div id="' . $table_id . '">' . $element['#prefix'];
    $element['#suffix'] .= '</div>';

    $element['validation_rules'] = [
                           '#type' => 'table',
                           '#header' => $header,
                           '#attributes' => ['class' => ['webform-validation-rules-table']],
                         ] + $rows;

    $element['actions'] = ['#type' => 'container'];

    // Build add state action.
    if ($element['#multiple']) {
      $element['actions']['add'] = [
        '#type' => 'submit',
        '#value' => t('Add another validation rule'),
        '#limit_validation_errors' => [],
        '#submit' => [[get_called_class(), 'addRuleSubmit']],
        '#ajax' => $ajax_settings,
        '#name' => $table_id . '_add',
      ];
    }

    // Edit source.
//    if (\Drupal::currentUser()->hasPermission('edit webform source')) {
//      $element['actions']['source'] = [
//        '#type' => 'submit',
//        '#value' => t('Edit source'),
//        '#limit_validation_errors' => [],
//        '#submit' => [[get_called_class(), 'editSourceSubmit']],
//        '#ajax' => $ajax_settings,
//        '#attributes' => ['class' => ['button', 'button--danger']],
//        '#name' => $table_id . '_source',
//      ];
//    }

    // Display a warning message when any state is set to disabled or enabled.
//    if (!empty($element['#disabled_message'])) {
//      $total_state_row_indexes = count($state_row_indexes);
//      $triggers = [];
//      foreach ($state_row_indexes as $index => $row_index) {
//        $id = Html::getId('edit-' . implode('-', $element['#parents']) . '-states-' . $row_index . '-state');
//        $triggers[] = [':input[data-drupal-selector="' . $id . '"]' => ['value' => ['pattern' => '^(disabled|enabled)$']]];
//        if (($index + 1) < $total_state_row_indexes) {
//          $triggers[] = 'or';
//        }
//      }
//      if ($triggers) {
//        $element['disabled_message'] = [
//          '#type' => 'webform_message',
//          '#message_message' => t('<a href="https://www.w3schools.com/tags/att_input_disabled.asp">Disabled</a> elements do not submit data back to the server and the element\'s server-side default or current value will be preserved and saved to the database.'),
//          '#message_type' => 'warning',
//          '#states' => ['visible' => $triggers],
//        ];
//      }
//    }

    $element['#attached']['library'][] = 'ip_webform_hard_soft_validation/ip_webform_hard_soft_validation.element.validation_rules';

    return $element;
  }

  /**
   * Build edit source help.
   *
   * @param array $element
   *   An element.
   *
   * @return array
   *   A renderable array.
   */
  protected static function buildSourceHelp(array $element) {
    $build = [];
    $build['types'] = [
      'title' => [
        '#markup' => t('Available validation types'),
        '#prefix' => '<strong>',
        '#suffix' => '</strong>',
      ],
      'items' => static::convertOptionToItemList($element['#validation_type_options']),
    ];
    $build['triggers'] = [
      'title' => [
        '#markup' => t('Available triggers'),
        '#prefix' => '<strong>',
        '#suffix' => '</strong>',
      ],
      'items' => static::convertOptionToItemList($element['#trigger_options']),
    ];
    return $build;
  }

  /**
   * Convert options with optgroup to item list.
   *
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   A renderable array.
   */
  protected static function convertOptionToItemList(array $options) {
    $items = [];
    foreach ($options as $option_name => $option_value) {
      if (is_array($option_value)) {
        $items[$option_name] = [
          'title' => [
            '#markup' => $option_name,
          ],
          'children' => [
            '#theme' => 'item_list',
            '#items' => array_keys($option_value),
          ],
        ];
      }
      else {
        $items[$option_name] = [
          '#markup' => $option_name,
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        ];
      }
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

  protected static function buildRuleRow(array $element, array $rule, $table_id, $row_index, array $ajax_settings) {
    $rule += ['type' => '', 'message' => '', 'trigger' => '', 'value' => ''];

    $element_name = $element['#name'];
    $trigger_selector = ":input[name=\"{$element_name}[validation_rules][{$row_index}][trigger]\"]";

    $row = [
      '#attributes' => [
        'class' => ['webform-validation-rules-table--rule'],
      ],
    ];
    $row['type'] = [
      '#type' => 'radios',
      '#title' => t('Type of Validation'),
      '#title_display' => 'invisible',
      '#options' => $element['#validation_type_options'],
      '#default_value' => $rule['type'],
      '#empty_option' => t('- Select -'),
      '#parents' => [$element_name, 'validation_rules', $row_index , 'type'],
      '#wrapper_attributes' => ['class' => ['webform-validation-rules-table--type']],
      '#error_no_message' => TRUE,
    ];

    $row['message'] = [
      '#type' => 'textfield',
      '#title' => t('Validation rule message'),
      '#title_display' => 'invisible',
      //'#description' => t('If set, this message will be used when an element\'s value are not pass validation rule. Use %value or %name in message for more details'),
      '#default_value' => $rule['message'],
      '#size' => 10,
      '#parents' => [$element_name, 'validation_rules', $row_index , 'message'],
      '#wrapper_attributes' => ['class' => ['webform-validation-rules-table--message']],
      '#error_no_message' => TRUE,
    ];

    $row['rule'] = [
      '#wrapper_attributes' => ['class' => ['webform-validation-rules-table--rule']],
    ];

    $row['rule']['trigger'] = [
      '#type' => 'select',
      '#title' => t('Trigger'),
      '#title_display' => 'invisible',
      '#options' => $element['#trigger_options'],
      '#default_value' => $rule['trigger'],
      '#empty_option' => t('- Select -'),
      '#parents' => [$element_name, 'validation_rules', $row_index , 'trigger'],
      '#wrapper_attributes' => ['class' => ['webform-validation-rules-table--trigger']],
      '#error_no_message' => TRUE,
    ];

    $row['rule']['value'] = [
      '#type' => 'textfield',
      '#title' => t('Value'),
      '#title_display' => 'invisible',
      '#size' => 25,
      '#default_value' => $rule['value'],
      '#placeholder' => t('Enter value…'),
      '#states' => [
        'visible' => [
          [$trigger_selector => ['value' => 'value']],
          'or',
          [$trigger_selector => ['value' => '!value']],
          'or',
          [$trigger_selector => ['value' => 'pattern']],
          'or',
          [$trigger_selector => ['value' => '!pattern']],
          'or',
          [$trigger_selector => ['value' => 'greater']],
          'or',
          [$trigger_selector => ['value' => 'less']],
        ],
      ],
      '#wrapper_attributes' => ['class' => ['webform-validation-rules-table--value']],
      '#parents' => [$element_name, 'validation_rules', $row_index , 'value'],
      '#error_no_message' => TRUE,
    ];

    $row['rule']['pattern'] = [
      '#type' => 'container',
      'description' => ['#markup' => t('Enter a <a href=":href">regular expression</a>', [':href' => 'http://www.w3schools.com/js/js_regexp.asp'])],
      '#states' => [
        'visible' => [
          [$trigger_selector => ['value' => 'pattern']],
          'or',
          [$trigger_selector => ['value' => '!pattern']],
        ],
      ],
    ];

    $row['operations'] = static::buildOperations($table_id, $row_index, $ajax_settings);
    return $row;
  }

  protected static function buildOperations($table_id, $row_index, array $ajax_settings) {
    $operations = [
      '#wrapper_attributes' => ['class' => ['webform-validation-rules-table--operations']],
    ];
    $operations['add'] = [
      '#type' => 'image_button',
      '#title' => t('Add'),
      '#src' => drupal_get_path('module', 'webform_hard_soft_validation') . '/images/icons/plus.svg',
      '#limit_validation_errors' => [],
      '#submit' => [[get_called_class(), 'addConditionSubmit']],
      '#ajax' => $ajax_settings,
      '#row_index' => $row_index,
      '#name' => $table_id . '_add_' . $row_index,
    ];
    $operations['remove'] = [
      '#type' => 'image_button',
      '#title' => t('Remove'),
      '#src' => drupal_get_path('module', 'webform_hard_soft_validation') . '/images/icons/minus.svg',
      '#limit_validation_errors' => [],
      '#submit' => [[get_called_class(), 'removeRowSubmit']],
      '#ajax' => $ajax_settings,
      '#row_index' => $row_index,
      '#name' => $table_id . '_remove_' . $row_index,
    ];
    return $operations;
  }

  /****************************************************************************/
  // Callbacks.
  /****************************************************************************/

  public static function addRuleSubmit(array &$form, FormStateInterface $form_state) {
    // Get the webform states element by going up one level.
    $button = $form_state->getTriggeringElement();
    $element =& NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));

    $values = $element['validation_rules']['#value'];

    if (!$values) {
      $values = [];
    }

    // Add new state and condition.
    $values[] = [
      'type' => 'soft',
      'message' => '',
      'trigger' => '',
      'value' => '',
    ];

    // Update element's #value.
    $form_state->setValueForElement($element['validation_rules'], $values);
    NestedArray::setValue($form_state->getUserInput(), $element['validation_rules']['#parents'], $values);

    // Update the number of rows.
    $form_state->set(static::getStorageKey($element, 'number_of_rows'), count($values));

    // Rebuild the form.
    $form_state->setRebuild();
  }

  /**
   * Form submission handler for removing a state or condition.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function removeRowSubmit(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -4));

    $row_index = $button['#row_index'];
    $values = $element['validation_rules']['#value'];

    if (isset($values[$row_index])) {
      unset($values[$row_index]);
      }

    // Reset values.
    $values = array_values($values);

    // Set values.
    $form_state->setValueForElement($element['validation_rules'], $values);
    NestedArray::setValue($form_state->getUserInput(), $element['validation_rules']['#parents'], $values);

    // Update the number of rows.
    $form_state->set(static::getStorageKey($element, 'number_of_rows'), count($values));

    // Rebuild the form.
    $form_state->setRebuild();
  }

  /**
   * Form submission handler for editing source.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function editSourceSubmit(array &$form, FormStateInterface $form_state) {
    // Get the webform states element by going up one level.
    $button = $form_state->getTriggeringElement();
    $element =& NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));

    // Set edit source.
    $form_state->set(static::getStorageKey($element, 'edit_source'), TRUE);

    // Convert states to editable string.
    $value = $element['#value'] ? Yaml::encode($element['#value']) : '';
    $form_state->setValueForElement($element['validation_rules'], $value);
    NestedArray::setValue($form_state->getUserInput(), $element['validation_rules']['#parents'], $value);

    // Rebuild the form.
    $form_state->setRebuild();
  }

  /**
   * Webform submission Ajax callback the returns the validation rules table.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $parent_length = (isset($button['#row_index'])) ? -4 : -2;
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, $parent_length));
    return $element;
  }

  /**
   * Validates webform validation rules element.
   */
  public static function validateWebformElementValidationRules(&$element, FormStateInterface $form_state, &$complete_form) {
    if (isset($element['validation_rules']['#value']) && is_string($element['validation_rules']['#value'])) {
      $rules = Yaml::decode($element['validation_rules']['#value']);
    }
    else {
      $errors = [];
      $rules = static::convertElementValueToFormApiRules($element, $errors);
      if ($errors) {
        $form_state->setError($element, reset($errors));
      }
    }
    $form_state->setValueForElement($element, NULL);

    $element['#value'] = $rules;
    $form_state->setValueForElement($element, $rules);
  }

  /****************************************************************************/
  // Helper functions.
  /****************************************************************************/

  /**
   * Get unique key used to store the number of options for an element.
   *
   * @param array $element
   *   An element.
   * @param string $name
   *   The name.
   *
   * @return string
   *   A unique key used to store the number of options for an element.
   */
  protected static function getStorageKey(array $element, $name) {
    return 'webform_rules__' . $element['#name'] . '__' . $name;
  }

  /****************************************************************************/
  // Convert functions.
  /****************************************************************************/
  protected static function convertFormApiRulesToRulesArray(array $fapi_rules) {
    $index = 0;
    $rules = [];
    foreach ($fapi_rules as $trigger => $options) {
      $rules[$index] = [
        'type' => $options['type'],
        'message' => $options['message'],
        'trigger' => $trigger,
        'value' => $options['value'],
      ];
      $index++;
    }
    return $rules;
  }

  protected static function convertElementValueToFormApiRules(array $element, array &$errors = []) {
    $rules = [];
    $rules_array = static::convertFormValuesToRulesArray($element['validation_rules']['#value']);
    foreach ($rules_array as $rule_array) {
      $trigger = $rule_array['trigger'];
      if (!$trigger) {
        continue;
      }

      // Check for duplicate states.
      if (isset($rules[$trigger])) {
        static::setFormApiRuleError($element, $errors, $trigger);
      }

      $type = $rule_array['type'];
      $message = $rule_array['message'];
      $value = $rule_array['value'];

      $rules[$trigger] = [
        'type'    => $type,
        'message' => $message,
        'trigger' => $trigger,
        'value'   => $value,
      ];
    }

    return $rules;
  }

  protected static function setFormApiRuleError(array $element, array &$errors, $trigger = NULL, $selector = NULL) {
    $trigger_options = $element['#trigger_options_flattened'];

    if ($trigger && !$selector) {
      $t_args = [
        '%trigger' => $trigger[$trigger],
      ];
      $errors[] = t('The %trigger validation rule is declared more than once. There can only be one declaration per rule.', $t_args);
    }
    elseif ($trigger && $selector) {
      $t_args = [
        '%trigger' => $trigger_options[$trigger],
      ];
      $errors[] = t('The %selector element is used more than once within the %trigger validation rule. To use multiple values within a trigger try using the pattern trigger.', $t_args);
    }
  }

  protected static function convertFormValuesToRulesArray(array $values = []) {
    $index = 0;

    $rules = [];
    foreach ($values as $value) {
      $index++;
      $rules[$index] = [
        'type'    => $value['type'],
        'message' => (isset($value['message'])) ? $value['message'] : '',
        'trigger' => (isset($value['trigger'])) ? $value['trigger'] : '',
        'value'   => (isset($value['value'])) ? $value['value'] : '',
      ];
    }
    return $rules;
  }

  protected static function isDefaultValueCustomizedFormApiRules(array $element) {
    // Empty default values are not customized.
    if (empty($element['#default_value'])) {
      return FALSE;
    }

    // #states must always be an array.
    if (!is_array($element['#default_value'])) {
      return t('Conditional logic (Form API #rules) is not an array.');
    }

//    $state_options = OptGroup::flattenOptions($element['#state_options']);
//    $states = $element['#default_value'];
//    foreach ($states as $state => $conditions) {
//      if (!isset($state_options[$state])) {
//        return t('Conditional logic (Form API #states) is using a custom %state state.', ['%state' => $state]);
//      }
//
//      // If associative array we can assume that it not customized.
//      if (WebformArrayHelper::isAssociative(($conditions))) {
//        $trigger = reset($conditions);
//        if (count($trigger) > 1) {
//          return t('Conditional logic (Form API #states) is using multiple triggers.');
//        }
//        continue;
//      }
//
//      $operator = NULL;
//      foreach ($conditions as $condition) {
//        // Make sure only one condition is being specified.
//        if (is_array($condition) && count($condition) > 1) {
//          return t('Conditional logic (Form API #states) is using multiple nested conditions.');
//        }
//        elseif (is_string($condition)) {
//          if (!in_array($condition, ['and', 'or', 'xor'])) {
//            return t('Conditional logic (Form API #states) is using the %operator operator.', ['%operator' => mb_strtoupper($condition)]);
//          }
//
//          // Make sure the same operator is being used between the conditions.
//          if ($operator && $operator != $condition) {
//            return t('Conditional logic (Form API #states) has multiple operators.', ['%operator' => mb_strtoupper($condition)]);
//          }
//
//          // Set the operator.
//          $operator = $condition;
//        }
//      }
//    }
    return FALSE;
  }

  public static function getTriggerOptions() {
    return [
      'empty' => t('Empty'),
      'filled' => t('Filled'),
      'checked' => t('Checked'),
      'unchecked' => t('Unchecked'),
      'value' => t('Value is'),
      '!value' => t('Value is not'),
      'pattern' => t('Pattern'),
      '!pattern' => t('Not Pattern'),
      'less' => t('Less than'),
      'greater' => t('Greater than'),
    ];
  }

  public static function getValidationTypeOptions() {
    return [
      'soft' => t('Soft'),
      'hard' => t('Hard'),
    ];
  }

}
