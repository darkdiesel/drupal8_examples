<?php

namespace Drupal\ip_webform_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get webform submissions list by webform id.
 *
 * @RestResource(
 *   id = "get_webform_results",
 *   label = @Translation("Get webform results by webform id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/{wid}/results"
 *   }
 * )
 */
class WebFormResultsResource extends ResourceBase {

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
  public function get($wid) {
    $webform = \Drupal::entityTypeManager()
                      ->getStorage('webform')
                      ->loadByProperties(['id' => $wid]);

    $webform = reset($webform);

    if ($webform instanceof Webform) {
      $response['submissions'] = [];

      if ($webform->hasSubmissions()) {
        $submissions = \Drupal::entityQuery('webform_submission')
                              ->condition('webform_id', $wid)
                              ->condition('completed', 'null', '!=')
                              ->execute();

//        foreach ($submissions as $submission) {
//          $webform_submission = \Drupal\webform\Entity\WebformSubmission::load($submission);
//
//          $submission_owner = $webform_submission->getOwner();
//
//          /**
//           * var $profile \Drupal\profile\Entity\Profile
//           */
//          $profile = current(\Drupal::entityTypeManager()
//                                    ->getStorage('profile')
//                                    ->loadByProperties([
//                                      'uid'  => $submission_owner->id(),
//                                      'type' => 'profile',
//                                    ]));
//
//          $organisaion = $profile->get('field_organisation')->getValue();
//
//          if ($organisaion) {
//            $organisaion = current($organisaion)['target_id'];
//          }
//
//          $response['submissions'][] = [
//            'id'              => $submission,
//            'organisation_id' => ($organisaion) ? $organisaion : '',
//          ];
//        }

        $response['submissions'] = $submissions;
      }

      return (new ResourceResponse($response, 200))
        ->addCacheableDependency($this->without_cache);
    }
    else {
      $response['errors'][] = t('Webform with id @wid is not found.', ['@wid' => $wid]);

      return (new ResourceResponse($response))
        ->addCacheableDependency($this->without_cache);
    }
  }

}

