<?php

namespace Drupal\ip_webform_api\Model;

class IpWebformSubmissionOrganisationModel {

  public static $webform_submission_organisation_table = 'ip_webform_submission_organisation';

  /**
   *
   * Create new task
   *
   * @param $webform_id string
   * @param $submission_id int
   * @param $organisation_id int
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   * @throws \Exception
   */
  public static function add($webform_id, $submission_id, $organisation_id) {
    try {
      return \Drupal::database()
                    ->insert(self::$webform_submission_organisation_table)
                    ->fields([
                      'webform_id'      => $webform_id,
                      'submission_id'   => $submission_id,
                      'organisation_id' => $organisation_id,
                    ])
                    ->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_api')
             ->error(t('Something went wrong during adding submission for organisation. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get task by task_id
   *
   * @param $webform_id string
   * @param $organisation_id int
   *
   * @return mixed
   */
  public static function get($webform_id, $organisation_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$webform_submission_organisation_table, 'wso');
      $query->fields('wso', ['webform_id', 'submission_id', 'organisation_id']);
      $query->condition('wso.webform_id', $webform_id);
      $query->condition('wso.organisation_id', $organisation_id);
      $query->range(0, 1);

      return $query->execute()->fetchObject();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_api')
             ->error(t('Something went wrong during getting submission for organisation. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * get task by task_id
   *
   * @param $webform_id string
   * @param $organisation_id int
   *
   * @return mixed
   */
  public static function getSubmissionId($webform_id, $organisation_id) {
    try {
      $query = \Drupal::database()
                      ->select(self::$webform_submission_organisation_table, 'wso');
      $query->fields('wso', ['webform_id', 'submission_id', 'organisation_id']);
      $query->condition('wso.webform_id', $webform_id);
      $query->condition('wso.organisation_id', $organisation_id);
      $query->range(0, 1);

      $obj = $query->execute()->fetchObject();

      if ($obj) {
        return $obj->submission_id;
      }
      else {
        return FALSE;
      }
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_api')
             ->error(t('Something went wrong during getting submission for organisation. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   *
   * Delete webform submission organisation connection by Submission ID
   *
   * @param $submission_id
   *
   * @return bool|int
   */
  public static function deleteByWebformSubmissionID($webform_id, $submission_id) {
    try {
      return \Drupal::database()
                    ->delete(self::$webform_submission_organisation_table)
                    ->condition('webform_id', $webform_id)
                    ->condition('submission_id', $submission_id)
                    ->execute();
    } catch (\Exception $e) {
      \Drupal::logger('ip_webform_api')
             ->error(t('Something went wrong during webform submission organisation deleting. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_webform_api')->error($e->getMessage());
      return FALSE;
    }
  }
}
