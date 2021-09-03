<?php

namespace Drupal\ip_organisations\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\ip_users\Controller\IpUserDataController;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get / update organisation node data
 *
 *
 * @RestResource(
 *   id = "get_organisation_node_data",
 *   label = @Translation("Get organisation data"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/organisation/{oid}",
 *     "create" = "/api/v1/organisation/{oid}",
 *   }
 * )
 */
class OrganisationDataResource extends ResourceBase {

  private $without_cache = [
    '#cache' => [
      'max-age' => 0,
    ],
  ];

  // taxonomy for levels
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
    $response['status'] = TRUE;


    if ($this->currentUser) {
      $organisation_node = current(
        \Drupal::entityTypeManager()->getStorage('node')
               ->loadByProperties([
                 'type' => 'organisation',
                 'nid'  => $oid,
               ])
      );

      if (!$organisation_node) {
        if (!isset($response['errors'])) {
          $response['errors'] = [];
        }

        $response['errors'][] = t('Organisation @oid not found.', ['@oid' => $oid]);
        $response['status']   = FALSE;

        return (new ResourceResponse($response, 404))
          ->addCacheableDependency($this->without_cache);
      }

      $parent_organisation = $organisation_node->get('field_parent_organisation')
                                               ->getValue();
      if (is_array($parent_organisation) && count($parent_organisation)) {
        $parent_organisation = $parent_organisation[0]['target_id'];

        $parent_organisation = \Drupal::entityTypeManager()
                                      ->getStorage('node')
                                      ->load($parent_organisation);

        if ($parent_organisation) {
          $parent_organisation = $parent_organisation->id();
          //$parent_organisation    = $parent_organisation->getTitle();
        }
      }
      else {
        $parent_organisation = '';
        //$parent_organisation    = '';
      }

      $level = $organisation_node->get('field_level')->getValue();
      if (is_array($level) && count($level)) {
        $level = $level[0]['target_id'];

        //        $level = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($level);
        //        if ($level) {
        //          $level = $level->getName();
        //        }
      }
      else {
        $level = NULL;
      }

      $response['organisation'] = [
        'id'                  => $organisation_node->id(),
        'title'               => $organisation_node->getTitle(),
        'level'               => $level,
        'parent_organisation' => $parent_organisation,
        'email'               => $organisation_node->get('field_email_address')->value,
      ];

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

  /**
   * @param $oid
   * @param $entity_data
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post($oid, $entity_data) {

    $organisation_data = [];

    $response = [
      'status' => TRUE,
    ];


    if (is_numeric($oid)) {
      $organisation_node = current(\Drupal::entityTypeManager()
                                          ->getStorage('node')
                                          ->loadByProperties([
                                            'type' => 'organisation',
                                            'nid'  => trim($oid),
                                          ])
      );
    }
    else {
      $organisation_node = FALSE;
    }

    if (!$organisation_node && strtolower(trim($oid)) != 'create') {
      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t('Organisation @oid not found.', ['@oid' => $oid]);
      $response['status']   = FALSE;

      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }

    if (isset($entity_data['data'])) {
      foreach ($entity_data['data'] as $data) {
        if (isset($data['key']) && isset($data['value'])) {
          $organisation_data[$data['key']] = $data['value'];
        }
      }
    }

    if (!count($organisation_data)) {
      $response['status'] = FALSE;

      if (!isset($response['errors'])) {
        $response['errors'] = [];
      }

      $response['errors'][] = t('No data for organisation.');

      return (new ResourceResponse($response, 400))
        ->addCacheableDependency($this->without_cache);
    }

    if (!$organisation_node && isset($organisation_data['title'])) {
      $organisation_node = Node::create([
        'type'  => 'organisation',
        'title' => trim($organisation_data['title']),
      ]);

      $response['new'] = TRUE;
    }
    elseif ($organisation_node) {
      $response['exist'] = TRUE;
    }

    // title
    $organisation_node->set('title', trim($organisation_data['title']));

    // level
    $levels = [];

    if (isset($organisation_data['level'])) {

      // Build list of levels terms
      $terms = \Drupal::entityTypeManager()
                      ->getStorage('taxonomy_term')
                      ->loadTree(self::level_taxonomy_v);

      foreach ($terms as $term) {
        $this->levels_terms[] = [
          'name'  => $term->name,
          'depth' => $term->depth,
          'id'    => $term->tid,
        ];
      }

      //$organisation_levels = explode(',', trim($organisation[$this->column_pointers[4]]));

      $key = array_search(trim($organisation_data['level']), array_column($this->levels_terms, 'id'));

      if ($key !== FALSE) {
        $key = $this->levels_terms[$key]['id'];

        if ($key) {
          $levels[] = ['target_id' => $key];
        }
      }

      $organisation_node->set('field_level', $levels);
    }

    // save parent organisation
    if (isset($organisation_data['parent_organisation'])) {
      $parent_organisation = [];

      //@TODO: Check if parent organisation exist
      $parent_organisation[] = ['target_id' => $organisation_data['parent_organisation']];

      $organisation_node->set('field_parent_organisation', $parent_organisation);
    }

    // email
    if (isset($organisation_data['email'])) {
      $organisation_node->set('field_email_address', trim($organisation_data['email']));
    }

    // save organisation
    $organisation_node->save();

    $response['id'] = $organisation_node->id();

    if (strtolower(trim($oid)) == 'create') {
      // assign created organisation to user
      $profile = IpUserDataController::getUserProfile($this->currentUser);

      if ($profile) {
        $response['profile'] = TRUE;

        $profile_organisation = $profile->get('field_organisation')->getValue();

        $profile_organisation[] = [
          "target_id" => $organisation_node->id(),
        ];

        $profile->set('field_organisation', $profile_organisation);
        $profile->save();

        $response['profile_organisation'] = $profile_organisation;
      }
    }

    return (new ResourceResponse($response, 200))
      ->addCacheableDependency($this->without_cache);
  }

}
