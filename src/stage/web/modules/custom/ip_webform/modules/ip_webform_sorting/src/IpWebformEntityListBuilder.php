<?php

namespace Drupal\ip_webform_sorting;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\webform\Element\WebformHtmlEditor;
use Drupal\webform\Utility\WebformDialogHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\webform\WebformEntityListBuilder;

class IpWebformEntityListBuilder extends WebformEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $headers = parent::buildHEader();

    $weight = [
      'weight' => [
        'data'      => $this->t('Weight'),
        'class'     => [RESPONSIVE_PRIORITY_MEDIUM],
        'specifier' => 'weight',
        'field'     => 'weight',
      ],
    ];

    $postition_last = count($headers) - 1;

    $headers = array_merge(array_slice($headers, 0, $postition_last), $weight, array_slice($headers, $postition_last));

    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);

    $postition_last = count($row) - 1;

    $weight_val = [
      'weight' => [
        'data' => [
          '#markup' => $entity->getWeight()
        ]
      ]
    ];

    $row = array_merge(array_slice($row, 0, $postition_last), $weight_val, array_slice($row, $postition_last));

    return $row;
  }
}
