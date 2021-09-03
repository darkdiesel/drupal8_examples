<?php

namespace Drupal\ip_webform\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines IpWebformLogController class.
 */
class IpWebformResultsController extends ControllerBase {

  public function webFormCSResults($webform){

    $webform_id = Xss::filter($webform);
    $webform = \Drupal\webform\Entity\Webform::load($webform_id);

    $filename = sprintf('%s_results_report.csv', $webform_id);

    $submission_exporter = \Drupal::service('webform_submission.exporter');
    $export_options = $submission_exporter->getDefaultExportOptions();

    $export_options['delimiter'] = ';';
    $export_options['multiple_delimiter'] = ',';
    $export_options['range_type'] = 'all';

    $submission_exporter->setWebform($webform);
    $submission_exporter->setExporter($export_options);
    $submission_exporter->generate();
    $file_path = $submission_exporter->getExportFilePath();

    $content =  file_get_contents($file_path);

    unlink($file_path);

    $response = new Response($content);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition','attachment; filename="'.$filename.'"');

    return $response;
  }

}
