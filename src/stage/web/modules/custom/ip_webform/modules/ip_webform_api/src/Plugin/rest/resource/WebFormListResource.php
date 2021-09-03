<?php

namespace Drupal\ip_webform_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionForm;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get webforms list.
 *
 * @RestResource(
 *   id = "get_webforms_list",
 *   label = @Translation("Get webforms list"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/list"
 *   }
 * )
 */
class WebFormListResource extends ResourceBase {

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
   */
  public function get() {
    $statuses = ['open', 'scheduled'];
    $status   = [];

    $query = \Drupal::request()->query;

    if ($query->has('status')) {
      if (in_array($query->get('status'), $statuses)) {
        $status[] = $query->get('status');
      }
    }
    else {
      $status = $statuses;
    }

    //    $values = [
    //      'status' => $status,
    //    ];

    //    $webforms = Drupal::entityTypeManager()
    //                      ->getStorage('webform')
    //                      ->loadByProperties($values);

    $webform_storage = Drupal::entityTypeManager()
                             ->getStorage('webform');

    $webform_query = $webform_storage->getQuery();

    //    $webform_query->condition('archive', 0);
    //
    //    //if ($this->moduleHandler()->moduleExists('webform_templates')) {
    //      $webform_query->condition('template', FALSE);
    //    //}

    $webforms_ids = $webform_query
      ->condition('status', $status, 'IN')
            ->sort('title', 'ASC')
      ->sort('weight', 'ASC', NULL)
      ->execute();

    $webforms = $webform_storage->loadMultiple($webforms_ids);

    $response = ['forms' => []];

    /**
     * @var $webform \Drupal\webform\Entity\Webform
     */
    foreach ($webforms as $webform) {
      if (!$webform instanceof Webform) {
        continue;
      }

      if (!$webform->access('submission_create', $this->currentUser)) {
        continue;
      }

      //      if ($webform->isClosed() === TRUE) {
      //        continue;
      //      }

      $response['forms'][] = [
        'id'          => $webform->id(),
        'title'       => $webform->get('title'),
        'description' => $webform->get('description'),
        'status'      => $webform->get('status'),
        'weight'      => $webform->get('weight'),
        'open'        => $webform->get('open'),
        'close'       => $webform->get('close'),
      ];
    }

    return (new ResourceResponse($response, 200))
      ->addCacheableDependency($this->without_cache);
  }

}
