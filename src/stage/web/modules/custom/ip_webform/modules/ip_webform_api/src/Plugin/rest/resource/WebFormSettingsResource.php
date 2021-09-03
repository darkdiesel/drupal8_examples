<?php

namespace Drupal\ip_webform_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ip_users\Controller\IpUserDataController;
use Drupal\ip_webform_api\Controller\WebformSubmissionController;
use Drupal\ip_webform_api\Model\IpWebformSubmissionOrganisationModel;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get webform settings by webform id.
 *
 * @RestResource(
 *   id = "get_webform_settinns",
 *   label = @Translation("Get webform settings by webform id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/{wid}/settings"
 *   }
 * )
 */
class WebFormSettingsResource extends ResourceBase {

  private $without_cache = [
    '#cache' => [
      'max-age' => 0,
    ],
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
    AccountProxyInterface $current_user) {
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
   */
  public function get($wid) {
    $webform = current(\Drupal::entityTypeManager()
                      ->getStorage('webform')
                      ->loadByProperties(['id' => $wid]));

    if ($webform instanceof Webform) {
      $response = [
        'id' => $webform->id(),
        'title' => $webform->get('title'),
        'description' => $webform->get('description'),
        'status' => $webform->get('status'),
        'open' => $webform->get('open'),
        'close' => $webform->get('close'),
        'progress' => NULL,
        'completed' => NULL
      ];

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

         if ($webform_submission) {
           $response['completed'] = $webform_submission->isCompleted();

           $elements = $webform->getElementsInitializedAndFlattened();
           $submission_data =  $webform_submission->getData();

           // calculate progress for webform
           $progress = WebformSubmissionController::calculateProgress($elements, $submission_data);

           $response['progress'] = $progress['form'];
         }
      }

      return (new ResourceResponse($response, 200))
        ->addCacheableDependency($this->without_cache);
    } else {

      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t('Webform with id @wid is not found.', ['@wid' => $wid]);

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }

}

