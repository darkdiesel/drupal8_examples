<?php

namespace Drupal\ip_webform_remarks\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Access\AccessResult;
use Drupal\ip_webform_remarks\Model\IpWebformRemarksModel;

/**
 * Provides a resource to get and save webform fields remarks
 *
 * @RestResource(
 *   id = "get_webform_remarks",
 *   label = @Translation("Get Webform remarks by field indicator"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/{wid}/field/{indicator}/remarks",
 *     "create" = "/api/v1/webform/{wid}/field/{indicator}/remarks",
 *   }
 * )
 */
class WebFormRemarksResource extends ResourceBase {

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
   * @param $indicator
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($indicator) {
    $response = [
      'remarks' => []
    ];

    /**
     * var $profile \Drupal\profile\Entity\Profile
     */
    $profile = current(\Drupal::entityTypeManager()
                              ->getStorage('profile')
                              ->loadByProperties([
                                'uid' => $this->currentUser->id(),
                                'type' => 'profile',
                              ]));

    $organisation = $profile->get('field_organisation')->getValue();

    if ($organisation) {
      $organisation = current($organisation)['target_id'];
    } else {
      $response['status'] = FALSE;

      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t("User not attached for organisation!");

      return (new ResourceResponse($response, 401))
        ->addCacheableDependency($this->without_cache);
    }

    $field_remarks = IpWebformRemarksModel::get($organisation, $indicator);

    if ($field_remarks) {
      $account = \Drupal\user\Entity\User::load($field_remarks->uid);

      $response['remarks']['user'] = $account->getUsername();
      $response['remarks']['text'] = $field_remarks->text;
      $response['remarks']['created_at'] =  \Drupal::service('date.formatter')
                                                   ->format($field_remarks->created_at, 'custom', 'Y-m-d\TH:i:s');
      $response['remarks']['updated_at'] =  \Drupal::service('date.formatter')
                                             ->format($field_remarks->updated_at, 'custom', 'Y-m-d\TH:i:s');
    }

    return (new ResourceResponse($response))
      ->addCacheableDependency($this->without_cache);
  }

  /**
   * Responds to POST requests.
   *
   * @param $wid
   * @param $indicator
   * @param $entity_data
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function post($wid, $indicator, $entity_data) {
    $response = [
      'status' => TRUE
    ];

    if (isset($entity_data['data']) && is_array($entity_data['data'])) {
      if (isset($entity_data['data']['text']) && is_string($entity_data['data']['text'])) {

        /**
         * var $profile \Drupal\profile\Entity\Profile
         */
        $profile = current(\Drupal::entityTypeManager()
                                  ->getStorage('profile')
                                  ->loadByProperties([
                                    'uid' => $this->currentUser->id(),
                                    'type' => 'profile',
                                  ]));

        $organisation = $profile->get('field_organisation')->getValue();

        if ($organisation) {
          $organisation = current($organisation)['target_id'];
        } else {
          $response['status'] = FALSE;

          if (!isset($response['errors'])) {
            $response['errors'] = [];
          }

          $response['errors'][] = t("User not attached for organisation!");

          return (new ResourceResponse($response, 401))
            ->addCacheableDependency($this->without_cache);
        }

        // check if remark for indicator already exist
        $field_remarks = IpWebformRemarksModel::get($organisation, $indicator);

        if ($field_remarks) {
          // if text changed save it
          if ($field_remarks->text != $entity_data['data']['text']){
            $result = IpWebformRemarksModel::setText($field_remarks->remark_id, $entity_data['data']['text']);
          } else {
            $result = TRUE;
          }
        } else {
          $result = IpWebformRemarksModel::add($organisation, $indicator, $entity_data['data']['text']);
        }

        if ($result === FALSE) {
          $response['status'] = FALSE;

          if (!isset($response['errors'])) {
            $response['errors'] = [];
          }

          $response['errors'][] = t("Some error occurred while create or update remarks.");
        }
      } else {
        $response['status'] = FALSE;

        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t("No data founded.");
      }
    } else {
      $response['status'] = FALSE;

      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t("No data founded.");
    }

    if (isset($response['errors']) && count ($response['errors'])){
      return (new ResourceResponse($response, 401))
        ->addCacheableDependency($this->without_cache);
    } else {
      return (new ResourceResponse($response, 200))
        ->addCacheableDependency($this->without_cache);
    }
  }

}
