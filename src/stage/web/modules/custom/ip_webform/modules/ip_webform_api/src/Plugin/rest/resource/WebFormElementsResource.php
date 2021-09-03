<?php

namespace Drupal\ip_webform_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ip_indicator\Model\IpIndicatorModel;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get webform elements by webform id.
 *
 * @RestResource(
 *   id = "get_webform_elements",
 *   label = @Translation("Get webform elements by webform id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/webform/{wid}/elements"
 *   }
 * )
 */
class WebFormElementsResource extends ResourceBase {

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

      foreach ($elements as $element) {
        // Add indicator to element
        $indicator = IpIndicatorModel::get($webform->id(), $element['#webform_key']);
        $element['#webform_indicator'] = $indicator;

        $response['webform']['elements'][] = $element;
      }

      return (new ResourceResponse($response, 200))
        ->addCacheableDependency($this->without_cache);
    } else {
      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t('Webform with @wid not found.', ['@wid' => $wid]);

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }
}

