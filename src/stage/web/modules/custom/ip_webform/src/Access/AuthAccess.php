<?php

namespace Drupal\ip_webform\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;

class AuthAccess implements AccessInterface{
  public function access(AccountInterface $account) {
    return \Drupal::currentUser()->isAuthenticated() ? AccessResult::allowed() : AccessResult::forbidden();
  }
}
