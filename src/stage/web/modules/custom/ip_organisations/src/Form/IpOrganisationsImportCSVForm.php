<?php

namespace Drupal\ip_organisations\Form;

//require(__DIR__ . '/../../vendor/autoload.php');

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use \Drupal\node\Entity\Node;

/**
 * Defines the content import form.
 */
class IpOrganisationsImportCSVForm extends FormBase {
  const tags_taxonomy_v = 'organisation_tags';
  const group_taxonomy_v = 'organisation_group';
  const level_taxonomy_v = 'organisation_levels';

  const NODE_UPDATED = 'UPDATED';
  const NODE_NEW = 'NEW';

  private $countries = [];

  // taxonomies
  private $tags_terms = [];
  private $groups_terms = [];
  private $levels_terms = [];

  // organisations
  private $organisations = [];

  private $processed_rows = [];

  private $nodes_new    = [];
  private $nodes_updated = [];
  private $nodes_rejected = [];

  private $headers_columns = [];
  private $column_pointers = [];

  public function getHeaderColumns(){
    return [
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
    ];
  }

  /**
   * Return default array of pointers data and column in csv file
   *
   * @return array
   */
  public function getColumnPointersDefault(){
    return [
      0  => 0, //organisation_id
      1  => 1, //organisation_name
      2  => 2, //parent_organisation_id
      3  => 3, //parent_organisation
      4  => 4, //level
      5  => 5, //group
      6  => 6, //country
      7  => 7, //address
      8  => 8, //postal_code
      9  => 9, //city
      10 => 10, //email
      11 => 11, // telephone_number
      12 => 12, //remarks
      13 => 13, //tags
      14 => 14, //highchart_code
      15 => 15 //multilevel_pass
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_organisations_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['import_file'] = [
      '#type'        => 'file',
      '#title'       => $this->t('Import file'),
      '#description' => $this->t('Allowed types: @extensions.', ['@extensions' => 'csv']),
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Upload'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    if (!empty($all_files['import_file'])) {
      $file_upload = $all_files['import_file'];
      if ($file_upload->isValid()) {
        $form_state->setValue('import_file', $file_upload->getRealPath());
        return;
      }
    }
    $form_state->setErrorByName('import_file', $this->t('The file could not be uploaded.'));
  }

  public function slug_name($name) {
    $name = str_replace(' ', '_', $name);
    $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    $name = preg_replace('/_{2,}/', '_', $name);

    return strtolower($name);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fid = $form_state->getValue('import_file');

    $csv_file = file_get_contents($fid);

    //$this->organisations = explode("\n", $csv_file);
    $this->organisations = preg_split('/\r\n|\r|\n/', $csv_file);

    $this->organisations = array_map(function ($el) {  if ($el) {return explode(';', $el); } else {return FALSE;} }, $this->organisations);

    $this->headers_columns = $this->getHeaderColumns();

    $this->column_pointers = $this->getColumnPointersDefault();

    if (count($this->headers_columns) != count($this->organisations[0])) {
      \Drupal::messenger()
             ->addError(t('Your file does not have the correct fields or format') );
      return;
    }

    foreach ($this->organisations[0] as $index => $column) {
      $column = preg_replace("/[\n\r]/","", trim($column));

      $key = array_search( $column, $this->headers_columns);

      if ($key !== FALSE) {
        $this->column_pointers[$key] = $index;
      } else {
        \Drupal::messenger()
               ->addError(t('Your file does not have the correct fields or format') );
        return;
      }
    }

    // setup lists of taxonomies and countries
    $this->buildLists();

    // ORGANISATION
    for ($i = 1; $i < count($this->organisations); $i++) {

      if ($this->organisations[$i] !== FALSE){
        $result = $this->processOrganisation($i);

        if ($result === FALSE) {
          $this->nodes_rejected[] = $i;

          $this->processed_rows[$i] = FALSE;
        }
      }
    }

    unlink($fid);

    \Drupal::messenger()
           ->addMessage(t('Organisations are imported.') . " " . t('Total new Organisations:') . ' ' . count ($this->nodes_new) . '. ' . t('Total updated organisations:') . ' ' . count($this->nodes_updated));

    if (count ($this->nodes_rejected)) {
      \Drupal::messenger()
             ->addWarning(t('Total rejected Organisations:')  . ' ' .  count ($this->nodes_rejected) );
    }

  }

  private function buildLists(){
    // Build list of countries
    $country_manager = \Drupal::service('country_manager');
    $list            = $country_manager->getList();

    foreach ($list as $key => $value) {
      $val             = $value->__toString();
      $this->countries[$val] = $key;
    }

    // Build list of tags terms
    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree(self::tags_taxonomy_v);

    foreach ($terms as $term) {
      $this->tags_terms[$term->tid] = $term->name;
    }

    // Build list of groups terms
    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree(self::group_taxonomy_v);

    foreach ($terms as $term) {
      $this->groups_terms[$term->tid] = $term->name;
    }

    // Build list of levels terms
    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree(self::level_taxonomy_v);

    foreach ($terms as $term) {
      $this->levels_terms[] = ['name' => $term->name, 'depth' => $term->depth, 'id' => $term->tid];
    }
  }

  /**
   * @param integer $row
   *
   * @return array|bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function processOrganisation($row){
    $organisation = $this->organisations[$row];

    if (count($this->headers_columns) != count($organisation)) {
      \Drupal::messenger()
             ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Incorrect column counts.', ['@row' => $row + 1]));

      return FALSE;
    }

    if (isset($this->processed_rows[$row])) {
      return TRUE;
    }

    // Check requirement fields for organisation
    $requirements = [1, 4, 10]; // name, level, email

    foreach ($requirements as $required) {
      if (trim($organisation[$this->column_pointers[$required]]) == '') {
        \Drupal::messenger()
               ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Missed required field: <strong>@field</strong>.', ['@row' => $row + 1, '@field' => $this->headers_columns[$required]]));

        return FALSE;
      }
    }

    //validate email
    if (!\Drupal::service('email.validator')->isValid(trim($organisation[$this->column_pointers[10]]))){
      \Drupal::messenger()
             ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Incorrect value for Email.', ['@row' => $row + 1]));

      return FALSE;
    };

    // check country data, if country exist address, post code and city should be required
    if (trim($organisation[$this->column_pointers[6]])){

      $requirements = [7, 8, 9]; //address, postal code, city

      foreach ($requirements as $required) {
        if (trim($organisation[$this->column_pointers[$required]]) == '') {
          \Drupal::messenger()
                 ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Missed required field: <strong>@field</strong>.', ['@row' => $row + 1, '@field' => $this->headers_columns[$required]]));

          return FALSE;
        }
      }
    }

    // validate level and parent organisation
    $organisation_levels = explode(',', $organisation[$this->column_pointers[4]]);

    if (count($organisation_levels) > 1) {
      \Drupal::messenger()
             ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Level should be only one value.', ['@row' => $row + 1]));
      return FALSE;
    } else {
      reset($organisation_levels);
      $level = current($organisation_levels);

      $key = array_search(trim($level), array_column($this->levels_terms, 'name'));

      if ($key === FALSE) {
        \Drupal::messenger()
               ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Level <strong>@level</strong> not exist.', ['@row' => $row + 1, '@level' => $level]));
        return FALSE;
      }

      $depth = $this->levels_terms[$key]['depth'];

      $requirements = [3]; // parent organisation id, parent organisation name

      if (isset($depth) && $depth) {
        // if level not main then parent organisation fields should required
        foreach ($requirements as $required) {
          if (trim($organisation[$this->column_pointers[$required]]) == '') {
            \Drupal::messenger()
                   ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Missed required field: <strong>@field</strong>.', ['@row' => $row + 1, '@field' => $this->headers_columns[$required]]));

            return FALSE;
          }
        }

        // check level of parent organisation

        $parent_organisation_node = FALSE;

        // if id provided in table get parent from db and check level
        if (trim($organisation[$this->column_pointers[2]]) != '') {
          $parent_organisation_node = current(\Drupal::entityTypeManager()->getStorage('node')
                                        ->loadByProperties([
                                          'type' => 'organisation',
                                          'nid'  => trim($organisation[$this->column_pointers[2]]),
                                        ])
          );
        } else {
          $key = array_search (trim($organisation[$this->column_pointers[3]]), array_column($this->organisations, 1));

          if ($key !== FALSE) {
            if (!isset($this->processed_rows[$key])) {
              // process parent organisation recursive
              $result = $this->processOrganisation($key);

              if ($result === FALSE) {
                $this->nodes_rejected[] = $key;

                $this->processed_rows[$key] = FALSE;

                return FALSE;
              }
            }

            if ($this->processed_rows[$key]) {
              $parent_organisation_node = current(\Drupal::entityTypeManager()->getStorage('node')
                                                         ->loadByProperties([
                                                           'type' => 'organisation',
                                                           'nid'  => $this->processed_rows[$key],
                                                         ])
              );
            } else {
              \Drupal::messenger()
                     ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Parent organisation from row <strong>@$key</strong> processed with errors.', ['@row' => $row + 1, '@key' => $key]));

              return FALSE;
            }
          }
        }

        // if parent not found reject this organisation
        if (!$parent_organisation_node) {
          \Drupal::messenger()
                 ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Parent organisation not found.', ['@row' => $row + 1]));

          return FALSE;
        }

        $parent_level = $parent_organisation_node->get('field_level')->getValue();

        if (is_array($parent_level) && count($parent_level)) {
          $parent_level = current($parent_level);

          $key = array_search(trim($parent_level['target_id']), array_column($this->levels_terms, 'id'));

          if ($key === FALSE) {
            \Drupal::messenger()
                   ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Level of Parent organisation <strong>@level</strong> not exist.', ['@row' => $row + 1, '@level' => $level]));
            return FALSE;
          }

          $parent_depth = $this->levels_terms[$key]['depth'];

          if ($parent_depth >= $depth) {
            \Drupal::messenger()
                   ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Parent organisation level should be top-level for <strong>@level</strong> level.', ['@row' => $row + 1, '@level' => $level]));
            return FALSE;
          }
        }
      } else {
        // if level main then parent organisation fields should be empty
        foreach ($requirements as $required) {
          if (trim($organisation[$this->column_pointers[$required]]) != '') {
            \Drupal::messenger()
                   ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Field: <strong>@field</strong> should be empty for main level.', ['@row' => $row + 1, '@field' => $this->headers_columns[$required]]));

            return FALSE;
          }
        }
      }
    }

    // save organisation

    $organisation_status = '';

    if (trim($organisation[$this->column_pointers[0]]) != '') {
      $organisation_node = current(\Drupal::entityTypeManager()->getStorage('node')
                                          ->loadByProperties([
                                            'type' => 'organisation',
                                            'nid'  => trim($organisation[$this->column_pointers[0]]),
                                          ])
      );

      if (!$organisation_node) {
        \Drupal::messenger()
               ->addWarning(t('Organisation not added from row: <strong>@row</strong>. Organisation with ID: <strong>@id</strong> not found.', ['@row' => $row + 1, '@id' => trim($organisation[$this->column_pointers[0]])]));

        return FALSE;
      } else {
        $organisation_status = self::NODE_UPDATED;

        $this->nodes_updated[] = $row;
      }
    } else {
      $organisation_node = Node::create([
        'type'  => 'organisation',
        'title' => trim($organisation[$this->column_pointers[1]]),
      ]);

      $organisation_status = self::NODE_NEW;

      $this->nodes_new[] = $row;
    }

    // save parent organisation
    $parent_organisation = [];

    if (isset($parent_organisation_node) && $parent_organisation_node !== FALSE) {
      $parent_organisation[] = ['target_id' => $parent_organisation_node->id()];
    }

    $organisation_node->set('field_parent_organisation', $parent_organisation);

    // title
    $organisation_node->set('title', trim($organisation[$this->column_pointers[1]]));

    // email
    $organisation_node->set('field_email_address', trim($organisation[$this->column_pointers[10]]));

    // remarks
    $organisation_node->set('field_remarks', trim($organisation[$this->column_pointers[12]]));

    // telephone number
    $organisation_node->set('field_telephone_number', str_replace("'", "", trim($organisation[$this->column_pointers[11]])));

    // highchart code
    $organisation_node->set('field_highchart_code', trim($organisation[$this->column_pointers[14]]));

    // address
    $address_lines = explode(',', trim($organisation[$this->column_pointers[7]]));
    if (trim($organisation[$this->column_pointers[6]])) {
      $country = $this->countries[trim($organisation[$this->column_pointers[6]])];
    }
    else {
      $country = '';
    }

    $organisation_node->set('field_organisation_address',
      [
        'locality'      => trim($organisation[$this->column_pointers[9]]),
        'address_line1' => (isset($address_lines[0])) ? $address_lines[0] : '',
        'address_line2' => (isset($address_lines[1])) ? $address_lines[1] : '',
        'postal_code'   => trim($organisation[$this->column_pointers[8]]),
        'country_code'  => $country,
      ]);

    // tags
    $tags = [];

    if (trim($organisation[$this->column_pointers[13]]) != '') {
      $organisation_tags = explode(',', trim($organisation[$this->column_pointers[13]]));

      foreach ($organisation_tags as $tag) {
        $key = array_search(trim($tag), $this->tags_terms);

        if ($key !== FALSE && $key) {
          $tags[] = ['target_id' => $key];
        }
      }
    }

    $organisation_node->set('field_organisation_tags', $tags);

    // group
    $groups = [];

    if (trim($organisation[$this->column_pointers[5]]) != '') {
      $organisation_groups = explode(',', trim($organisation[$this->column_pointers[5]]));

      foreach ($organisation_groups as $group) {
        $key = array_search(trim($group), $this->groups_terms);

        if ($key !== FALSE && $key) {
          $groups[] = ['target_id' => $key];
        }
      }
    }

    $organisation_node->set('field_group', $groups);

    // level
    $levels = [];

    if (trim($organisation[$this->column_pointers[4]]) != '') {
      $organisation_levels = explode(',', trim($organisation[$this->column_pointers[4]]));

      foreach ($organisation_levels as $level) {
        $key = array_search(trim($level), array_column($this->levels_terms, 'name'));

        if ($key !== FALSE) {
          $key = $this->levels_terms[$key]['id'];

          if ($key) {
            $levels[] = ['target_id' => $key];
          }
        }
      }
    }

    $organisation_node->set('field_level', $levels);

    // Multilevel Pass
    if (trim($organisation[$this->column_pointers[15]]) != '') {
      if (strtoupper(trim($organisation[$this->column_pointers[15]])) == 'YES') {
        $multilevel_pass = 1;
      }
      else {
        $multilevel_pass = 0;
      }
    }
    else {
      $multilevel_pass = 0;
    }

    $organisation_node->set('field_multilevel_passthrough_org', $multilevel_pass);

    $organisation_node->save();

    $this->processed_rows[$row] = $organisation_node->id();

    return ['id' => $organisation_node->id(), 'status' => $organisation_status];
  }
}
