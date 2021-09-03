<?php

namespace Drupal\ip_webform\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\ip_webform\Model\IpWebFormLogsModel;

/**
 * Defines IpWebformEditController class.
 */
class IpWebformEditController extends ControllerBase {

  public function deleteElement($webform, $element) {
    $webform_id = $webform;
    $webfrom_storage = \Drupal::entityTypeManager()->getStorage('webform');

    $query = $webfrom_storage->getQuery();
    $query->condition('id', $webform_id);
    $entity_ids = $query->execute();

    $webform = false;
    if (!empty($entity_ids)) {
      $webform = $webfrom_storage->load(reset($entity_ids));
    }

    $elements = $webform->getElementsDecoded();

    unset($elements['user_custom'][$element]);

    $webform->setElements($elements);
    $webform->save();

    return new RedirectResponse(Url::fromRoute('ip_webform.edit', ['webform' => $webform->id()])->toString());
  }

}
