<?php

namespace Drupal\ip_webform_submissions_logging\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines IpWebformSubmissionLogsController class.
 */
class IpWebformSubmissionLogsController extends ControllerBase {

  /**
   * @param \Drupal\webform\Entity\Webform $webform
   * @param \Drupal\webform\Entity\WebformSubmission $webform_submission
   *
   * @return array|bool
   */
  public function webFormLogsBySubmission($webform, $webform_submission) {
    if ($webform instanceof Webform and $webform_submission instanceof WebformSubmission) {
      $result = \Drupal\ip_webform_submissions_logging\Model\IpWebFormSubmissionLogsModel::getAllByWebformAndSubmission($webform->id(), $webform_submission->id());

      return $this->getLogsPage($result);
    }
  }


  /**
   * @param \Drupal\webform\Entity\Webform $webform
   *
   * @return array|bool
   */
  public function webFormLogsByWebform($webform) {
    if ($webform instanceof Webform) {
      $result = \Drupal\ip_webform_submissions_logging\Model\IpWebFormSubmissionLogsModel::getAllByWebform($webform->id());

      return $this->getLogsPage($result);
    }
  }

  /**
   *
   * Return page settings
   *
   * @param $result
   *
   * @return array|bool
   */
  private function getLogsPage($result) {
    if ($result == FALSE) {
      return FALSE;
    }

    $rows = [];

    foreach ($result as $row => $content) {
      $user = \Drupal\user\Entity\User::load($content->uid);

      $user_url  = Url::fromUri('internal:/user/' . $content->uid, ['attributes' => ['target' => '_blank']]);
      $user_link = \Drupal::l($user->getUsername(), $user_url);

      $rows[$content->lid] = [
        $user_link,
        $content->webform,
        $content->submission,
        'log' => [
          'data' => [
            '#markup' =>nl2br($content->log)
          ]
        ],
        date('Y-m-d H:i:s', $content->created_at),
      ];
    }

    $header = ['User', t('Webform'), t('Submission'), t('Log'), t('Date')];

    $build = [
      'table' => [
        //'#prefix'        => '<h1>'.t('Logs').'</h1>',
        '#theme'      => 'table',
        '#attributes' => [
          'data-striping' => 0,
        ],
        '#header'     => $header,
        '#rows'       => $rows,
        '#empty'      => $this->t("No Results Found"),
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }
}
