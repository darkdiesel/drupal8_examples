<?php

namespace Drupal\ip_webform_api\Plugin\rest\resource;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ip_users\Controller\IpUserDataController;
use Drupal\ip_webform_api\Controller\WebformPermissionController;
use Drupal\ip_webform_api\Controller\WebformSubmissionController;
use Drupal\ip_webform_submissions_logging\Model\IpWebFormSubmissionLogsModel;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\WebformSubmissionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Access\AccessResult;
use Drupal\ip_webform_api\Model\IpWebformSubmissionOrganisationModel;

/**
 * Provides a resource to check if  webform has submissions from current
 *
 * @RestResource(
 *   id = "get_webform_submission",
 *   label = @Translation("Check webform has submissions for current user"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/{wid}/submission",
 *     "create" = "/api/v1/webform/{wid}/submission",
 *   }
 * )
 */
class WebFormSubmissionResource extends ResourceBase {

  private $without_cache = [
    '#cache' => [
      'max-age' => 0,
    ],
  ];

  // exclude container element that not fillable
  private $container_types = [
    'container',
    'category_container',
    'webform_wizard_page',
  ];

  private $exclude_progress = [
    'markup',
    'webform_markup',
  ];

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
   * @param $wid
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($wid) {
    $webform = current(\Drupal::entityTypeManager()
                              ->getStorage('webform')
                              ->loadByProperties(['id' => $wid]));

    $response = [
      'status'          => TRUE,
      'in_draft'        => NULL,
      'completed'       => NULL,
      'created_at'      => NULL,
      'updated_at'      => NULL,
      'completed_at'    => NULL,
      'organisation_id' => NULL,
      'data'            => [],
      'progress'        => NULL,
    ];


    if ($webform instanceof Webform) {
      if (!WebformPermissionController::canView($this->currentUser, $webform)) {

        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        //throw new AccessDeniedHttpException();
        $response['errors'][] = t('You not allow to view answer for this webform.');
        return (new ResourceResponse($response, 403))
          ->addCacheableDependency($this->without_cache);
      }

      if ($webform->isClosed() === TRUE) {
        $response['status'] = FALSE;

        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t('Webform @wid is closed', ['@wid' => $wid]);
        return (new ResourceResponse($response, 400))
          ->addCacheableDependency($this->without_cache);
      }

      $organisation = IpUserDataController::getOrganisation($this->currentUser);

      $response['organisation_id'] = ($organisation) ? $organisation : '';

      // if user not select organisation in session try to find submission by id
      if ($organisation == NULL) {
        $submission_id = current(
          $query = \Drupal::entityQuery('webform_submission')
                          ->condition('webform_id', $wid)
                          ->condition('uid', $this->currentUser->getAccount()
                                                               ->id())
                          ->execute()
        );
      }
      else {
        $submission_id = IpWebformSubmissionOrganisationModel::getSubmissionId($webform->id(), $organisation);
      }


      if ($submission_id) {
        $webform_submission = \Drupal\webform\Entity\WebformSubmission::load($submission_id);

        $response['submission_id'] = $submission_id;

        $response['in_draft']  = $webform_submission->isDraft();
        $response['completed'] = $webform_submission->isCompleted();

        $response['created_at'] = \Drupal::service('date.formatter')
                                         ->format($webform_submission->getCreatedTime(), 'custom', 'Y-m-d\TH:i:s');

        if ($updated_at = $webform_submission->getChangedTime()) {
          $response['updated_at'] = \Drupal::service('date.formatter')
                                           ->format($updated_at, 'custom', 'Y-m-d\TH:i:s');
        }

        if ($completed_at = $webform_submission->getCompletedTime()) {
          $response['completed_at'] = \Drupal::service('date.formatter')
                                             ->format($completed_at, 'custom', 'Y-m-d\TH:i:s');
        }

        $response['permissions']['can_reopen'] = WebformPermissionController::canReopen($this->currentUser, $webform);

        $response['data'] = [];

        $elements        = $webform->getElementsInitializedAndFlattened();
        $submission_data = $webform_submission->getData();

        // calculate progress for webform
        $progress = WebformSubmissionController::calculateProgress($elements, $submission_data);

        $response['progress'] = $progress;

        $query = \Drupal::request()->query;

        if ($query->has('key')) {
          $key = $query->get('key');

          if (isset($elements[$key])) {
            $container = $elements[$key];

            $container_children = $container['#webform_children'];

            if (is_array($container_children)) {
              foreach ($container_children as $container_child) {
                if (isset($elements[$container_child])) {
                  if (!in_array($elements[$container_child]['#type'], $this->container_types)) {
                    $response['data'][] = [
                      'key'   => $container_child,
                      'value' => isset($submission_data[$container_child]) ? $submission_data[$container_child] : NULL,
                    ];
                  }
                }
              }
            }
          }
        }
        else {
          foreach ($elements as $key => $element) {
            if (!in_array($element['#type'], $this->container_types) && !in_array($element['#type'], $this->exclude_progress)) {
              $response['data'][] = [
                'key'   => $key,
                'value' => isset($submission_data[$key]) ? $submission_data[$key] : NULL,
              ];
            }
          }
        }

        //        foreach ($webform_submission->getData() as $key => $data) {
        //          $element = array_search(trim($key), array_column($response['data'], 'key'));
        //
        //          if ($element !== FALSE) {
        //            $response['data'][$element] = [
        //              'key'   => $key,
        //              'value' => $data,
        //            ];
        //          }
        //        }
      }

      return (new ResourceResponse($response, 200))
        ->addCacheableDependency($this->without_cache);
    }
    else {
      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['status'] = FALSE;

      $response['errors'][] = t('Webform with id @wid is not found', ['@wid' => $wid]);

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }

  /**
   * Responds to POST requests.
   *
   * @param $wid
   * @param $entity_data
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post($wid, $entity_data) {
    $response = [];

    $webform = current(\Drupal::entityTypeManager()
                              ->getStorage('webform')
                              ->loadByProperties(['id' => $wid]));

    if ($webform instanceof Webform) {
      if (!$webform->access('submission_create')) {
        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['status'] = FALSE;

        //throw new AccessDeniedHttpException();
        $response['errors'][] = t('You not allow to create answers for this webform.');
        return (new ResourceResponse($response, 403))
          ->addCacheableDependency($this->without_cache);
      }

      if ($webform->isClosed() === TRUE) {
        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['status'] = FALSE;

        $response['errors'][] = t("Webform is closed, you can't create or update the submission while it closed.");
        return (new ResourceResponse($response, 400))
          ->addCacheableDependency($this->without_cache);
      }

      // flag fro creating submission
      $create_new = TRUE;

      $organisation = IpUserDataController::getOrganisation($this->currentUser);

      $response['organisation_id'] = ($organisation) ? $organisation : '';

      // if user not select organisation in session try to find submission by id
      if ($organisation == NULL) {
        $submission_id = current(
          $query = \Drupal::entityQuery('webform_submission')
                          ->condition('webform_id', $wid)
                          ->condition('uid', $this->currentUser->getAccount()
                                                               ->id())
                          ->execute()
        );
      }
      else {
        $submission_id = IpWebformSubmissionOrganisationModel::getSubmissionId($webform->id(), $organisation);
      }

      if ($submission_id) {
        /**
         * @var \Drupal\webform\Entity\WebformSubmission $webform_submission WebformSubmission
         */
        $webform_submission = WebformSubmission::load($submission_id);
        $create_new         = FALSE;

        $response['new'] = FALSE;
      }

      if ($create_new) {
        $webform_submission = \Drupal::entityTypeManager()
                                     ->getStorage('webform_submission')
                                     ->create(
                                       [
                                         'webform_id'  => $wid,
                                         'remote_addr' => \Drupal::request()
                                                                 ->getClientIp(),
                                         'is_draft'    => TRUE,
                                         'completed'   => FALSE,
                                       ]
                                     );

        $webform_submission->set('in_draft', TRUE);

        $webform_submission->save();

        $response['new'] = TRUE;
      }

      $response['sid'] = $webform_submission->id();


      if (isset($entity_data['reopen']) && $entity_data['reopen']) {

        // Check permission
        $can_reopen = WebformPermissionController::canReopen($this->currentUser, $webform);

        if ($can_reopen) {
          $webform_submission->set('in_draft', TRUE);
          $webform_submission->save();

          if (class_exists('IpWebFormSubmissionLogsModel')) {
            // logger
            $log = t('Submission @submission reopened', ['@submission' => $webform_submission->id()]);
            IpWebFormSubmissionLogsModel::add($log, $webform->id(), $webform_submission->id());
          }
        }
        else {
          if (!isset($response['errors'])) {
            $response['errors'] = ['form' => []];
          }

          $response['errors']['form'] = t('You not allow to reopen questionnaire.');

          return (new ResourceResponse($response, 400))
            ->addCacheableDependency($this->without_cache);
        }
      }

      if ($webform_submission->isCompleted()) {
        $response['status'] = FALSE;

        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t("Submission is completed, you can't update it.");
        return (new ResourceResponse($response, 400))
          ->addCacheableDependency($this->without_cache);
      }

      try {
        // form elements
        $elements        = $webform->getElementsInitializedAndFlattened();
        $submission_data = $webform_submission->getData();

        if (isset($entity_data['data'])) {
          foreach ($entity_data['data'] as $data) {
            if (isset($data['key']) && isset($data['value'])) {
              if (isset($elements[$data['key']])) {
                $submission_data[$data['key']] = $data['value'];
              }
              else {
                if (!isset($response['errors'])) {
                  $response['errors'] = ['form' => []];
                }

                $response['errors']['form'][] = t('Element @element does not exist on the form', ['@element' => $data['key']]);
              }
            }
          }

          $webform_submission->setData($submission_data);
          // $validation = WebformSubmissionForm::validateWebformSubmission($webform_submission);
          $validation = WebformSubmissionController::validateSubmission($webform_submission);

          // check if isset key in request and return errors only for category with key
          if (isset($entity_data['key']) && $entity_data['key'] != "") {

            if (isset($elements[$entity_data['key']])) {
              $container_children = $elements[$entity_data['key']];
            }
            else {
              $container_children = NULL;
            }

            if ($container_children && is_array($container_children['#webform_children'])) {
              foreach ($container_children['#webform_children'] as $container_child) {
                if (isset($elements[$container_child])) {
                  // find errors for element of container
                  if (isset($validation['errors'][$container_child])) {
                    // check if errors array exist
                    if (!isset($response['errors'])) {
                      $response['errors'] = [];
                    }

                    $response['errors'][$container_child] = $validation['errors'][$container_child];
                  }

                  // find warnings for element of container
                  if (isset($validation['warnings'][$container_child])) {
                    // check if warnings array exist
                    if (!isset($response['warnings'])) {
                      $response['warnings'] = [];
                    }

                    $response['warnings'][$container_child] = $validation['warnings'][$container_child];
                  }
                }
              }
            }
          }
          else {
            // if key not exist return all validation errors
            if (isset($validation['errors'])) {
              // check if errors array exist
              if (!isset($response['errors'])) {
                $response['errors'] = [];
              }
              $response['errors'] = $validation['errors'];
            }

            if (isset($validation['warnings'])) {
              $response['warnings'] = $validation['warnings'];
            }
          }
          //@todo: Resets the data in $webform_submission to its original values if have errors?
        }

        // set submission complete
        if (isset($entity_data['finish']) && $entity_data['finish']) {
          if (empty($response['errors'])) {
            $webform_submission->set('in_draft', FALSE);

            if (class_exists('IpWebFormSubmissionLogsModel')) {
              $log = t('Submission @submission completed', ['@submission' => $webform_submission->id()]);
              IpWebFormSubmissionLogsModel::add($log, $webform->id(), $webform_submission->id());
            }
          }
          else {
            if (!isset($response['errors'])) {
              $response['errors'] = ['form' => []];
            }

            $response['errors']['form'][] = t("You can not complete questionnaire while it has errors.");
          }
        }

        // Save submission.
        $webform_submission->save();

        // Check Errors
        if (isset($response['error']) && is_array($response['error']) && count($response['errors'])) {
          $response['status'] = 'WITH ERRORS';
        }
        else {
          $response['status'] = TRUE;
        }

        //        return (new ResourceResponse($response, 200))
        //          ->addCacheableDependency($this->without_cache);

        // return new ResourceResponse($response, 200);

        return new Response(json_encode($response), 200);
      } catch (\Exception $e) {
        $response['status'] = FALSE;

        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t('Something went wrong during submission creation. Please check your data or contact site administrator.');

        \Drupal::logger('ip_webform_api')->error($e->getMessage());

        return (new ResourceResponse($response, 400))
          ->addCacheableDependency($this->without_cache);
      }

    }
    else {
      $response['status'] = FALSE;

      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t('Webform with id @wid is not found.', ['@wid' => $wid]);

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }
}
