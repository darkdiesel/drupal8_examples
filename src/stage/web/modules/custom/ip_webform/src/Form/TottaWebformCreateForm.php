<?php

namespace Drupal\ip_webform\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Defines the content import form.
 */
class IpWebformCreateForm extends FormBase {

  private function isAdmin() {
    $user = User::load(\Drupal::currentUser()->id());
    return ($user->hasPermission('administer webform') || $user->hasPermission('edit any webform'));
  }

  private function getTemplates($keys = '', $category = '') {
    $webfrom_storage = \Drupal::entityTypeManager()->getStorage('webform');

    $query = $webfrom_storage->getQuery();
    $query->condition('template', TRUE);
    $query->condition('archive', FALSE);
    // Filter by key(word).
    if ($keys) {
      $or = $query->orConditionGroup()
        ->condition('title', $keys, 'CONTAINS')
        ->condition('description', $keys, 'CONTAINS')
        ->condition('category', $keys, 'CONTAINS')
        ->condition('elements', $keys, 'CONTAINS');
      $query->condition($or);
    }

    // Filter by category.
    if ($category) {
      $query->condition('category', $category);
    }

    $query->sort('title');

    $entity_ids = $query->execute();
    if (empty($entity_ids)) {
      return [];
    }

    /* @var $entities \Drupal\webform\WebformInterface[] */
    $entities = $webfrom_storage->loadMultiple($entity_ids);

    // If the user is not a webform admin, check view access to each webform.
    if (!$this->isAdmin()) {
      foreach ($entities as $entity_id => $entity) {
        if (!$entity->access('view')) {
          unset($entities[$entity_id]);
        }
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_webform_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $templates = $this->getTemplates();

    $options = [];
    foreach($templates as $template) {
      $options[$template->id()] = $template->label();
    }

    $form['template'] = [
      '#type' => 'select',
      '#title' => $this->t('Template'),
      '#options' => $options
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => true
    ];

    $form['municipality_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Municipality name'),
      '#required' => true
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create webform'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Maybe need in future
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
    $role_id = 'beheerder';
    $role = \Drupal\user\Entity\Role::load($role_id);

    if($role == false) {
      $role = \Drupal\user\Entity\Role::create(array('id' => $role_id, 'label' => 'Beheerder'));
      $role->save();
    }

    $email = $form_state->getValue('email');
    $user = user_load_by_mail($email);

    if ($user == false) {
      $user = User::create();

      $user->setPassword(user_password());
      $user->setEmail($email);
      $user->setUsername(explode('@', $email)[0]);

      $user->set("init", 'email');

      if(!$user->hasRole($role_id)) {
        $user->addRole($role_id);
      }

      $user->activate();

      $user->save();
    }

    $webform = \Drupal::entityTypeManager()->getStorage('webform')->load($form_state->getValue('template'));

    $duplicate = $webform->createDuplicate();

    $name = $form_state->getValue('municipality_name');

    $duplicate->set('id', $user->id() . '_' . $this->slug_name($name));
    $duplicate->set('title', $name);
    $duplicate->set('template', false);
    $duplicate->setOwnerId($user->id());

    $duplicate->save();

    $form_state->setRedirectUrl(Url::fromRoute('entity.webform.edit_form', ['webform' => $duplicate->id()]));
    return;
  }
}
