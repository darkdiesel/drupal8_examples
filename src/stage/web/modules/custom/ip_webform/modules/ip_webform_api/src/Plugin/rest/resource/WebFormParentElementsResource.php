<?php

namespace Drupal\ip_webform_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get webform parent elements by webform id.
 *
 * @RestResource(
 *   id = "get_webform_parent_elements",
 *   label = @Translation("Get webform parent elements by webform id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/{wid}/parent-elements"
 *   }
 * )
 */
class WebFormParentElementsResource extends ResourceBase {

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
    $webform = \Drupal::entityTypeManager()
                      ->getStorage('webform')
                      ->loadByProperties(['id' => $wid]);

    $webform = reset($webform);

    if ($webform instanceof Webform) {
      $elements = $webform->getElementsInitializedAndFlattened();
      $response['webform']['elements'] = [];

      $allowed_types = [
        'container',
        'category_container',
        'webform_wizard_page'
      ];

      foreach ($elements as $element) {
        if (in_array($element['#type'], $allowed_types)) {
          $response['webform']['elements'][] = $element;
        }
      }

      return new ResourceResponse($response);
    } else {
      $response['message'] = 'Webform with id ' . $wid . ' is not found';
      return new ResourceResponse($response);
    }
  }

}

