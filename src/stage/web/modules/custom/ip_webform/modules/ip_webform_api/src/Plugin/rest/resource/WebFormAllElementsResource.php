<?php

namespace Drupal\ip_webform_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ip_indicator\Model\IpIndicatorModel;
use Drupal\ip_webform_api\Controller\WebformOptionsController;
use Drupal\ip_webform_api\Controller\WebformSubmissionController;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get webform internal elements by webform id and
 * container id.
 *
 * @RestResource(
 *   id = "get_webform_all_elements",
 *   label = @Translation("Get webform all elements by webform id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/{wid}/all-elements"
 *   }
 * )
 */
class WebFormAllElementsResource extends ResourceBase {

  private $without_cache = array(
    '#cache' => array(
      'max-age' => 0,
    ),
  );

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('ip_webform_api'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param  $wid
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($wid) {
    $webform = \Drupal::entityTypeManager()
                      ->getStorage('webform')
                      ->loadByProperties(['id' => $wid]);

    $webform = reset($webform);

    if ($webform instanceof Webform) {
      $response = ['status' => TRUE];

      if ($webform->isClosed() === TRUE) {
        $response['status'] = FALSE;

        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t('Webform @wid is closed.', ['@wid' => $wid]);
        return (new ResourceResponse($response, 403))
          ->addCacheableDependency($this->without_cache);
      }

      $response['webform']['elements'] = [];
      $response['webform']['options'] = WebformOptionsController::getWebformOptions($webform);

      $elements = $webform->getElementsInitializedAndFlattened();

      $only_basic = FALSE;

      // load submission
      $submission_id = current(\Drupal::entityQuery('webform_submission')
                                      ->condition('webform_id', $wid)
                                      ->condition('uid', $this->currentUser->getAccount()
                                                                           ->id())
                                      ->execute());

      if ($submission_id) {
        $webform_submission = \Drupal\webform\Entity\WebformSubmission::load($submission_id);
        $submission_data =  $webform_submission->getData();
      } else {
        $submission_data = [];
      }

      $progress = WebformSubmissionController::calculateProgress($elements, $submission_data);

      if (isset($progress['basic'])) {
        if ($progress['basic']['filled_required'] != $progress['basic']['count_required']) {
          $only_basic = TRUE;
        }
      }

      foreach ($elements as $element) {
        if ($only_basic) {
          if (!self::is_category_basic($element, $elements) ) {
            continue;
          }
        }

        $_element = $this->provide_api_element($element, $elements, $only_basic);

        if ($_element !== FALSE){
          $response['webform']['elements'][] = $_element;
        }
      }

      return (new ResourceResponse($response))
        ->addCacheableDependency($this->without_cache);
    }
    else {
      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t('Webform with id @wid is not found.', ['@wid' => $wid]);

      return (new ResourceResponse($response))
        ->addCacheableDependency($this->without_cache);
    }
  }

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
  public static function is_category_basic($element, $elements) {
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

  function provide_api_element($element, &$elements, $only_basic){

    if (isset($element['#type']) && $element['#type'] == 'category_container') {
      if (isset($element['#admin_category']) && $element['#admin_category'] == 1) {
        // exclude all child elements under admin category
        $this->processAllChill($element, $elements);

        return FALSE;
      }

      if ($only_basic) {
        if (!self::is_category_basic($element, $elements) ) {
          $this->processAllChill($element, $elements);

          return FALSE;
        }
      }
    }

    if (isset($elements[$element['#webform_key']]['element_processed']) && ($elements[$element['#webform_key']]['element_processed'])){
      return FALSE;
    }

    $_element = [
      'type'               => $element['#type'],
      'title'              => $element['#title'],
      'webform_key'        => $element['#webform_key'],
      'webform_parent_key' => $element['#webform_parent_key'],
      'webform_depth'      => $element['#webform_depth'],
    ];

//    $_element['webform_main_parent_key'] = $this->getMainParentKey($element, $elements);

    // Add indicator to element
    $indicator = IpIndicatorModel::get($element['#webform'], $element['#webform_key']);

    if ($indicator !== FALSE) {
      $_element['webform_indicator'] = $indicator;
      $indicator = explode(".", $indicator);

      if (is_array($indicator) && isset($indicator[1])){
        $_element['element_indicator'] = $indicator[1];
      }
    }

    if (isset($element['#required'])) {
      $_element['required'] = $element['#required'];
    }

    if (isset($element['#description'])) {
      $_element['description'] = $element['#description'];
    }

    $_element['basic_category'] = isset($element['#basic_category']) ? ($element['#basic_category'] ? true : false ) : false;

    if (isset($element['#help_title'])) {
      $_element['help_title'] = $element['#help_title'];
    }

    if (isset($element['#help'])) {
      $_element['help'] = $element['#help'];
    }

    if (isset($element['#help_display'])) {
      $_element['help_display'] = $element['#help_display'];
    }

    if (isset($element['#more_title'])) {
      $_element['more_title'] = $element['#more_title'];
    }

    if (isset($element['#more'])) {
      $_element['more'] = $element['#more'];
    }

    if (isset($element['#mode'])) {
      $_element['mode'] = $element['#mode'];
    }

    if (isset($element['#hide_empty'])) {
      $_element['hide_empty'] = $element['#hide_empty'];
    }

    if (isset($element['#webform_children'])) {
      $_element['webform_children']= [];
      $_element['elements']= [];
      foreach ($element['#webform_children'] as $webform_children) {
        $_element['webform_children'][] = $webform_children;

        $el = $this->provide_api_element($elements[$webform_children], $elements, $only_basic);

        if ($el) {
          $_element['elements'][] = $el;
        }

        unset($el);
      }
    }

    if (isset($element['#options'])) {
      $_element['options'] = [];

      foreach ($element['#options'] as $option_key => $option_value) {
        $_element['options'][] = [
          'key' => $option_key,
          'value' => $option_value
        ];
      }
    }

    if (isset($element['#markup'])) {
      $_element['markup'] = $element['#markup'];
    }

    $elements[$_element['webform_key']]['element_processed'] = TRUE;

    return $_element;
  }

  /**
   * Mark processed all elements under $element
   *
   * @param $element
   * @param $elements
   */
  function processAllChill($element, &$elements){
    if (isset($element['#webform_children'])) {
      foreach ($element['#webform_children'] as $webform_children) {
        $this->processAllChill($elements[$webform_children], $elements);
      }
    }

    $elements[$element['#webform_key']]['element_processed'] = TRUE;
  }

}

