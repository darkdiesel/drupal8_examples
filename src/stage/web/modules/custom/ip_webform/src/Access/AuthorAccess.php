<?php

namespace Drupal\ip_webform\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;

class AuthorAccess implements AccessInterface{
  public function access(AccountInterface $account) {
    if(!\Drupal::currentUser()->isAuthenticated()) {
      return AccessResult::forbidden();
    }

    $webform_id = \Drupal::routeMatch()->getParameter('webform');
    $webfrom_storage = \Drupal::entityTypeManager()->getStorage('webform');

    $query = $webfrom_storage->getQuery();
    $query->condition('id', $webform_id);
    $entity_ids = $query->execute();

    $webform = false;
    if (!empty($entity_ids)) {
      $webform = $webfrom_storage->load(reset($entity_ids));
    }

    if($webform->getOwnerId() != \Drupal::currentUser()->id()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }
}
