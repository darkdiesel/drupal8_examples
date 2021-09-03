<?php

namespace Drupal\ip_webform_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\WebformSubmission;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ip_indicator\Model\IpIndicatorModel;

/**
 * Provides a resource to get webform submission by submission id.
 *
 * @RestResource(
 *   id = "get_submission",
 *   label = @Translation("Get webform submission by submission id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/submission/{sid}"
 *   }
 * )
 */
class SubmissionResource extends ResourceBase {

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
   * @param $sid
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($sid) {
    $response = [
      'status' => TRUE
    ];

    $submission = current(\Drupal::entityTypeManager()
                     ->getStorage('webform_submission')
                     ->loadByProperties(['sid' => $sid]));

    if ($submission instanceof WebformSubmission) {
      $submission_data  = $submission->getData();
      $submission_owner = $submission->getOwner();
      $submission_roles = $submission_owner->getRoles();
      $webform = $submission->getWebform();

      $created_at = \Drupal::service('date.formatter')
                           ->format($submission->getCreatedTime(), 'custom', 'Y-m-d\TH:i:s');

      if ($updated_at = $submission->getChangedTime()) {
        $updated_at = \Drupal::service('date.formatter')
                             ->format($updated_at, 'custom', 'Y-m-d\TH:i:s');
      }

      if ($completed_at = $submission->getCompletedTime()) {
        $completed_at = \Drupal::service('date.formatter')
                                           ->format($completed_at, 'custom', 'Y-m-d\TH:i:s');
      }

      /**
       * var $profile \Drupal\profile\Entity\Profile
       */
      $profile = current(\Drupal::entityTypeManager()
                                ->getStorage('profile')
                                ->loadByProperties([
                                  'uid' => $submission_owner->id(),
                                  'type' => 'profile',
                                ]));

      if ($profile) {
        $organisation = $profile->get('field_organisation')->getValue();

        if ($organisation) {
          $organisation = current($organisation)['target_id'];
        } else {
          $organisation = NULL;
        }
      } else {
        $organisation = NULL;
      }

      $data = [];
      $elements = $webform->getElementsInitializedAndFlattened();
      foreach ($submission_data as $field => $value) {
        if (isset($elements[$field])) {
          // used indicator instead of element key
          $indicator = IpIndicatorModel::get($webform->id(), $field);
          $data[$indicator] = $value;
        }
      }

      $response['submission']['data']  = $data;
      $response['submission']['roles'] = $submission_roles;
      $response['submission']['dates'] =
      [
        'created_at' => $created_at,
        'updated_at' => ($updated_at) ? $updated_at : '',
        'completed_at' => ($completed_at) ? $completed_at : '',
      ];

      $response['submission']['organisation_id'] = $organisation;

      return (new ResourceResponse($response, 200))
        ->addCacheableDependency($this->without_cache);
    }
    else {
      $response['submission'] = NULL;

      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['status'] = FALSE;

      $response['errors'][] = t('Submission @sid not found.', ['@sid' => $sid]);

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }

}
