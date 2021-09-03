<?php

namespace Drupal\ip_organisations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines IpOrganisationsExportCSVController class.
 */
class IpOrganisationsExportCSVController extends ControllerBase {

  public function exportOrganisationsPage(){
    $build = [];

    $build['download_organisations_report'] = [
      '#type' => 'link',
      '#title' => t('Get Organisations CSV'),
      '#weight' => -1000,
      '#url' => \Drupal\Core\Url::fromUri('internal:/admin/config/development/ip_export_organisation_csv/get_report'),
      '#attributes' => [
        'class' => ['button', 'button--primary']
      ]
    ];

    return $build;
  }

  public function createReport() {

    $nids = \Drupal::entityQuery('node')->condition('type','organisation')->execute();
    $organisations =  \Drupal\node\Entity\Node::loadMultiple($nids);

    $filename = sprintf('organisations_report_%s.csv',  \Drupal::time()->getRequestTime());

    $csv_rows = [];

    $csv_rows[] = implode(';',
      [
        t('Organisation_id'),
        t('Name'),
        t('Parent Organisation ID'),
        t('Parent Organisation'),
        t('Level'),
        t('Group'),
        t('Country'),
        t('Address'),
        t('Postal code'),
        t('City'),
        t('Email'),
        t('Telephone nr'),
        t('Remarks'),
        t('Tags'),
        t('HighChart Code'),
        t('Multi Level Pass'),
      ]
    );

    if (is_array($organisations) && count($organisations)){
      $countries = \Drupal\Core\Locale\CountryManager::getStandardList();

      foreach ($organisations as $organisation) {

        $parent_organisation = $organisation->get('field_parent_organisation')->getValue();
        if (is_array($parent_organisation) && count($parent_organisation)) {
          $parent_organisation = $parent_organisation[0]['target_id'];

          $parent_organisation = \Drupal::entityTypeManager()->getStorage('node')->load($parent_organisation);

          if ($parent_organisation) {
            $parent_organisation_id = $parent_organisation->id();
            $parent_organisation = $parent_organisation->getTitle();
          }
        } else {
          $parent_organisation_id = '';
          $parent_organisation = '';
        }

        $level = $organisation->get('field_level')->getValue();
        if (is_array($level) && count($level)) {
          $level = $level[0]['target_id'];

          $level = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($level);
          if ($level) {
            $level = $level->getName();
          }
        } else {
          $level = '';
        }

        // group field
        $groups = $organisation->get('field_group')->getValue();
        if (is_array($groups) && count($groups)) {
          $group_cell = '';

          foreach ($groups as $group) {
            $group = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($group['target_id']);

            if ($group) {
              if ($group_cell) {
                $group_cell .= ', '.$group->getName();
              } else {
                $group_cell = $group->getName();
              }
            }
          }
        } else {
          $group_cell = '';
        }

        // tags field
        $tags = $organisation->get('field_organisation_tags')->getValue();
        if (is_array($tags) && count($tags)) {
          $tags_cell = '';

          foreach ($tags as $tag) {
            $tag = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tag['target_id']);

            if ($tag) {
              if ($tags_cell) {
                $tags_cell .= ', '.$tag->getName();
              } else {
                $tags_cell = $tag->getName();
              }
            }
          }
        } else {
          $tags_cell = '';
        }

        $email = $organisation->get('field_email_address')->getValue();
        if (is_array($email) && count($email)) {
          $email = $email[0]['value'];
        } else {
          $email = '';
        }

        $remarks = $organisation->get('field_remarks')->getValue();
        if (is_array($remarks) && count($remarks)) {
          $remarks = $remarks[0]['value'];
        } else {
          $remarks = '';
        }

        $tel = $organisation->get('field_telephone_number')->getValue();
        if (is_array($tel) && count($tel)) {
          $tel = $tel[0]['value'];
        } else {
          $tel = '';
        }

        $multilevel_pass = $organisation->get('field_multilevel_passthrough_org')->getValue();
        if (is_array($multilevel_pass) && count($multilevel_pass)) {

          if ($multilevel_pass[0]['value']) {
            $multilevel_pass = 'YES';
          } else {
            $multilevel_pass = 'NO';
          }
        } else {
          $multilevel_pass = 'NO';
        }

        $highchart_code = $organisation->get('field_highchart_code')->getValue();
        if (is_array($highchart_code) && count($highchart_code)) {

          $highchart_code = $highchart_code[0]['value'];
        } else {
          $highchart_code = '';
        }

        $address = $organisation->get('field_organisation_address')->getValue();
        if (is_array($address) && count($address)) {
          $address_line = $address[0]['address_line1'];

          if ($address[0]['address_line2']) {
            $address_line .= ', '.$address[0]['address_line2'];
          }

          $address_postal = $address[0]['postal_code'];
          $address_city = $address[0]['locality'];
          $address_country = $countries[$address[0]['country_code']]->__toString();
        } else {
          $address_line = '';
          $address_postal = '';
          $address_city = '';
          $address_country = '';
        }

        $csv_rows[] = implode(';',
          [
            $organisation->id(), //organisation_id
            $organisation->getTitle(), //Organisation name
            $parent_organisation_id, //Parent Organisation ID
            $parent_organisation, //Parent Organisation
            $level, //Level
            $group_cell, //Group
            $address_country, //Organisation_Country
            $address_line, //Organisation_Address
            $address_postal, //Organisation_Postal
            $address_city, //Organisation_City
            $email, //Organisation_Email
            ($tel) ? sprintf("'%s'", $tel) : '', //Organisation_Telephonenr
            $remarks, //Organisation_remarks
            $tags_cell, //Organisation_tags
            $highchart_code, //High chart code
            $multilevel_pass, //Multilevel Pass
          ]
        );
      }
    }

    $content = implode("\n", $csv_rows);

    $response = new Response($content);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition','attachment; filename="'.$filename.'"');

    return $response;
  }
}
