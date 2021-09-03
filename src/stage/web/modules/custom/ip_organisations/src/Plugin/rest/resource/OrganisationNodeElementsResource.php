<?php

namespace Drupal\ip_organisations\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\ip_users\Controller\IpUserDataController;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get organisation node elements
 *
 *
 * @RestResource(
 *   id = "get_organisation_node_elements",
 *   label = @Translation("Get organisation node elements"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/organisation/elements",
 *   }
 * )
 */
class OrganisationNodeElementsResource extends ResourceBase {

  private $without_cache = [
    '#cache' => [
      'max-age' => 0,
    ],
  ];

  // taxonomy for levels

  private $return_node_fields = [
    'title'                     => 'title',
    //'field_level'               => 'level',
    //'field_parent_organisation' => 'parent_organisation',
    //'field_email_address'       => 'email',
  ];

  const level_taxonomy_v = 'organisation_levels';

  // terms for taxonomy
  private $levels_terms = [];

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
   * @param $oid
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($oid) {
    $response = [
      'status'   => TRUE,
      'elements' => [],
    ];

    if ($this->currentUser) {
      //@TODO; move from deprecated method
      $bundle_fields = \Drupal::entityManager()
                              ->getFieldDefinitions('node', 'organisation');

      //title
      $field = $bundle_fields['title'];

      $element = [
        'key' => $this->return_node_fields['title'],
        'title'       => $field->label(),
        'description' => $field->get('description'),
        'required'    => $field->get('required'),
        'type'        => 'textfield',//@TODO: Get type from field widget
        'field_type'  => $field->getType(),
      ];

      $response['elements'][] = $element;

      // level
//      $field = $bundle_fields['field_level'];
//
//      $element = [
//        'key' => $this->return_node_fields['field_level'],
//        'title'       => $field->label(),
//        'description' => $field->get('description'),
//        'required'    => $field->get('required'),
//        'type'        => 'radios', //@TODO: Get type from field widget
//        'field_type'  => $field->getType(),
//        'options'     => [],
//      ];
//
//      $settings = $field->getSettings();
//
//      $terms = \Drupal::entityTypeManager()
//                      ->getStorage($settings['target_type'])
//                      ->loadTree(self::level_taxonomy_v);
//
//      foreach ($terms as $term) {
//        $parents = [];
//
//        if (isset($term->parents) && $term->parents[0] != 0) {
//          $parents = $term->parents;
//        }
//
//        $name = $term->name;
//
//        if ($term->depth) {
//
//          for ($i = 0; $i < $term->depth; $i++) {
//            $name = '-' . $name;
//          }
//        }
//
//        $element['options'][] = [
//          'key'     => $term->tid,
//          'value'   => $name,
//          'depth'   => $term->depth,
//          'parents' => $parents,
//        ];
//      }
//
//      $response['elements'][] = $element;

      // parent organisation
//      $field = $bundle_fields['field_parent_organisation'];
//
//      $element = [
//        'key' => $this->return_node_fields['field_parent_organisation'],
//        'title'       => $field->label(),
//        'description' => $field->get('description'),
//        'required'    => $field->get('required'),
//        'type'        => 'select',//@TODO: Get type from field widget
//        'field_type'  => $field->getType(),
//        'options'     => [],
//      ];
//
//      $values = [
//        'type' => 'organisation',
//      ];
//
//      //@TODO: filter by selected level
//      $organisation_nodes = \Drupal::entityTypeManager()
//                                   ->getStorage('node')
//                                   ->loadByProperties($values);
//
//      foreach ($organisation_nodes as $organisation) {
//        $element['options'][] = [
//          'key'   => $organisation->id(),
//          'value' => $organisation->getTitle(),
//        ];
//      }
//
//      $response['elements'][] = $element;

      // email
//      $field = $bundle_fields['field_email_address'];
//
//      $element = [
//        'key' => $this->return_node_fields['field_email_address'],
//        'title'       => $field->label(),
//        'description' => $field->get('description'),
//        'required'    => $field->get('required'),
//        'type'        => 'email',//@TODO: Get type from field widget
//        'field_type'  => $field->getType(),
//      ];

      //$response['elements'][] = $element;

      return (new ResourceResponse($response, 200))
        ->addCacheableDependency($this->without_cache);
    }
    else {
      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t('Current user not found.');
      $response['status']   = FALSE;

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }

}
