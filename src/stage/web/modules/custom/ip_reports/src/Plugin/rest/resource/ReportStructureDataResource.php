<?php

namespace Drupal\ip_reports\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a report data by report id.
 *
 * @RestResource(
 *   id = "get_report_structure_node_data",
 *   label = @Translation("Get report structure data by report id"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/report-structure/{rid}"
 *   }
 * )
 */
class ReportStructureDataResource extends ResourceBase {

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
   * @param $rid
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function get($rid) {
    $report_node = current(\Drupal::entityTypeManager()->getStorage('node')
                                  ->loadByProperties([
                                    'type' => 'report',
                                    'nid'  => $rid,
                                  ])
    );

    if ($report_node instanceof Node) {

      $report_category = $report_node->get('field_report_category');

        if (count($report_category)){
          $report_category = $report_category->first()
            ->get('entity')
            ->getTarget()
            ->getValue()->getName();
        } else {
          $report_category = '';
        }

      $response['report'] = [
        'id'    => $report_node->id(),
        'title' => $report_node->getTitle(),
        'description' => $report_node->get('field_report_description')->value,
        'status' => $report_node->get('status')->value,
        'created_at' => $report_node->get('created')->value,
        'updated_at' => $report_node->get('changed')->value,
        'publish_on' => $report_node->get('publish_on')->value,
        'unpublish_on' => $report_node->get('unpublish_on')->value,
        'category' => $report_category,
        'download_pdf' => $report_node->get('field_download_pdf')->value,
        'download_docx' => $report_node->get('field_download_docx')->value,
        'show_print_button' => $report_node->get('field_print_button')->value,
        'own_segmentation' => $report_node->get('field_own_segmentation')->value,
        'edit_comparison_group_name' => $report_node->get('field_edit_comparison_group_name')->value,
        'report_settings' => $report_node->get('field_report_settings')->value,
        'header' => $report_node->get('field_report_header')->value,
        'footer' => $report_node->get('field_report_footer')->value,
        'note' => $report_node->get('field_report_note')->value,
        'margins' => [
          'top' => $report_node->get('field_report_margin_top')->value,
          'left' => $report_node->get('field_report_margin_left')->value,
          'right' => $report_node->get('field_report_margin_right')->value,
          'bottom' => $report_node->get('field_report_margin_bottom')->value,
        ],
        'custom_inapplicable' => $report_node->get('field_custom_inapplicable')->value,
        'custom_not_available' => $report_node->get('field_custom_not_available')->value,
        'custom_not_enough_data' => $report_node->get('field_custom_not_enough_data')->value,
        'comparison_groups' => $report_node->get('field_comparison_groups')->value,
        'available_chapters' => $report_node->get('field_available_chapters')->value,
        'selected_chapters' => $report_node->get('field_selected_chapters')->value,
        'report_content' => []
      ];

      if ($webform = $report_node->get('field_report_webform')->getValue()){
        if (isset($webform[0]['target_id'])){
          $response['report']['webform'] = $webform[0]['target_id'];
        }
      }

      $report_content_blocks = $report_node->get('field_report_content')->getValue();

      foreach ($report_content_blocks as $report_content_block) {
        if (!isset($report_content_block['target_revision_id']) || !isset($report_content_block['target_id'])) {
          continue;
        }

        $report_content_entity = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($report_content_block['target_revision_id']);

        if ($report_content_entity) {
          $response['report']['report_content'][] =
            [
              'type' => $report_content_entity->getType(),
              'id' => $report_content_block['target_id'],
              'revision_id' => $report_content_block['target_revision_id']
            ];
        }
      }

      //$report_node->get('field_report_content')

      return new ResourceResponse($response);
    }
    else {
      $response['errors'][] = t('Report with id @rid not founded.', ['@rid' => $rid]);
      return (new ResourceResponse($response, 404))
        ->addCacheableDependency($this->without_cache);
    }
  }
}

