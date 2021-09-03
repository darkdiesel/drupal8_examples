<?php

namespace Drupal\ip_webform_api\Plugin\WebformHandler;

use Complex\Exception;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ip_webform_api\Model\IpWebformApiTasksLogModel;
use Drupal\ip_webform_api\Model\ipWebformApiTasksModel;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\webformSubmissionInterface;

//use Drupal\Core\Url;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "ip-webfrom-submission-to-laravel",
 *   label = @Translation("Ip Webfrom to Laravel"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Sends submission data to Laravel."),
 *   cardinality =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class SendToLaravelHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $sid     = $webform_submission->id();
    $webform = $this->getWebform();
    $wid     = $webform->id();

    \Drupal::logger('ip_webform_api')->notice(t('Log from webform handler. Webform: @webform and submssion: @submission', ['@webform' => $wid, '@submission' => $sid]));

    $config = \Drupal::config('laravel.settings');

    if (!($base_url = $config->get('base_url'))) {
      \Drupal::logger('ip_webform_api')->error(t('Please setup base_url config for laravel integration!'));

      return FALSE;
    }

    if ($webform_submission->isCompleted()) {
      \Drupal::logger('ip_webform_api')->notice(t('Completed'));

      try {
        $result = \Drupal::httpClient()->post(sprintf('%s/submissions/%s/%s/create', $base_url, $wid, $sid), [
            'headers'       => [
              'Content-type' => 'application/json',
              'X-Requested-With' => 'XMLHttpRequest'
            ],
        ]);

        \Drupal::logger('ip_webform_api')->notice($result->getBody());


        $response = $result->getBody();

        if ($response) {
          $response = json_decode($response, true);
        }

        if (isset($response['success']) && $response['success']) {
          \Drupal::logger('ip_webform_api')->notice(t('Success laravel synchronisation'));
        } else {
          \Drupal::logger('ip_webform_api')->error(t('Some problem with laravel synchronisation'));
        }

        $task_model = new ipWebformApiTasksModel;
        $task_log_model = new IpWebformApiTasksLogModel();

        // create tasks and logs to laravel
        $task = $task_model->add(t("Track submission; webform:@webform; submission:@submission", ['webform' => $wid,'@submission' => $sid]), $wid, $sid);
        $task_log_model->add($task, t("Task for sending submission @submission from form @form to laravel created", ['@submission' => $sid, '@form' => $wid]));
        //$task_log_model->add($task, t("Task for sending submission @submission from form @form to laravel created", ['@submission' => $sid, '@form' => $wid]));

      } catch (\Exception $e) {
        \Drupal::logger('ip_webform_api')->error(t('Some error occurred while sending request to Laravel!'));
        \Drupal::logger('ip_webform_api')->error($e->getMessage());

        return FALSE;
      }
    } else {
      \Drupal::logger('ip_webform_api')->notice(t('Not Completed'));
    }

    return TRUE;
  }
}
