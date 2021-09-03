<?php

namespace Drupal\ip_webform_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\Webform;

/**
 * Defines WebformPermissionController class.
 */
class WebformPermissionController extends ControllerBase {

  /**
   * Check user permission for reopening submission
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   * @param \Drupal\webform\Entity\Webform $webform
   *
   * @return bool
   */
  public static function canReopen($user, $webform) {

    if (!$webform instanceof Webform) {
      return FALSE;
    }

    $can_reopen = FALSE;

    if ($user->hasPermission('edit any webform submission')) {
      $can_reopen = TRUE;
    }
    elseif ($user->hasPermission('edit own webform submission')) {
      $can_reopen = TRUE;
    }
    elseif ($webform->access('submission_update_any')) {
      $can_reopen = TRUE;
    }
    elseif ($webform->access('submission_update_own')) {
      $can_reopen = TRUE;
    }

    return $can_reopen;
  }

  /**
   * Check user permission for viewing submission
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   * @param \Drupal\webform\Entity\Webform $webform
   *
   * @return bool
   */
  public static function canView($user, $webform) {
    if (!$webform instanceof Webform) {
      return FALSE;
    }

    $can_view = FALSE;

    if ($user->hasPermission('view any webform submission')) {
      $can_view = TRUE;
    }
    elseif ($user->hasPermission('view own webform submission')) {
      $can_view = TRUE;
    }
    elseif ($webform->access('submission_view_any')) {
      $can_view = TRUE;
    }
    elseif ($webform->access('submission_view_own')) {
      $can_view = TRUE;
    }

    return $can_view;
  }
}
