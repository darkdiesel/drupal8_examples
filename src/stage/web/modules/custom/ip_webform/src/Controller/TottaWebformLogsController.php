<?php

namespace Drupal\ip_webform\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines IpWebformLogsController class.
 */
class IpWebformLogsController extends ControllerBase {

  public function webFormLogs($webform){
    $connection = \Drupal::database();

    $query = $connection->select('ip_webform_logs', 'l');

    $query->fields('l', array('lid', 'uid', 'webform', 'log', 'timestamp'));

    $query->condition('l.webform',  Xss::filter($webform), '=');

    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);

    $result = $pager->execute();

    $rows = array();

    foreach ($result as $row => $content) {
      $user = \Drupal\user\Entity\User::load($content->uid);

      $csv_download_url = Url::fromUri('internal:/user/'.$content->uid, array('attributes' => array('target' => '_blank')));
      $csv_download_link = \Drupal::l($user->getUsername(), $csv_download_url);

      $rows[] = [$csv_download_link, $content->log, date ( 'Y-m-d H:i:s', $content->timestamp )];
    }

    $header = array('User', t('Log'), t('Date'));

    $build = [
      'table'           => [
        //'#prefix'        => '<h1>'.t('Logs').'</h1>',
        '#theme'         => 'table',
        '#attributes'    => [
          'data-striping' => 0
        ],
        '#header' => $header,
        '#rows'   => $rows,
        '#empty' => $this->t("No Results Found")
      ],
    ];

    $build['pager'] = array(
      '#type' => 'pager'
    );
    return $build;
  }

}
