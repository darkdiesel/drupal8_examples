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
class IpWebformDashboardForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_webform_dashboard_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $webfrom_storage = \Drupal::entityTypeManager()->getStorage('webform');

    $query = $webfrom_storage->getQuery();
    $query->condition('uid', \Drupal::currentUser()->id());
    $query->condition('status', 'open');
    $query->sort('title');
    $entity_ids = $query->execute();

    if (empty($entity_ids)) {
      $entities = [];
    }
    else {
      /* @var $entities \Drupal\webform\WebformInterface[] */
      $entities = $webfrom_storage->loadMultiple($entity_ids);
    }

    $form['webforms'] = [
      '#type' => 'table',
      '#caption' => $this->t('Webforms'),
      '#header' => [
        $this
          ->t('Name'),
        $this
          ->t('Action'),
      ]
    ];

    foreach ($entities as $key => $entity) {
      $form['webforms'][$key]['name'] = [
        '#type' => 'link',
        '#title' => $entity->label(),
        '#title_display' => 'invisible',
        '#url' => $entity->toLink()->getUrl()
      ];

      $form['webforms'][$key]['action']['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#title_display' => 'invisible',
        '#url' => Url::fromUri('internal:/user/webform_dashboard/' . $entity->id()),
        '#attributes' => [
          'class' => ['btn', 'btn-primary']
        ]
      ];

      if(\Drupal::currentUser()->hasPermission('view ip webform logs')) {
        $form['webforms'][$key]['action']['divider-1'] = [
          '#type' => 'text',
          '#markup' => '&nbsp;'
        ];

        $form['webforms'][$key]['action']['log'] = [
          '#type' => 'link',
          '#title' => $this->t('Logs'),
          '#title_display' => 'invisible',
          '#url' => Url::fromRoute('ip_webform.logs', array('webform' => $entity->id())),
          '#attributes' => [
            'class' => ['btn', 'btn-primary']
          ]
        ];
      }

      if(\Drupal::currentUser()->hasPermission('get ip webform results csv')) {
        $form['webforms'][$key]['action']['divider-2'] = [
          '#type' => 'text',
          '#markup' => '&nbsp;'
        ];

        $form['webforms'][$key]['action']['result_csv'] = [
          '#type' => 'link',
          '#title' => $this->t('Results CSV'),
          '#title_display' => 'invisible',
          '#url' => Url::fromRoute('ip_webform.results.csv', array('webform' => $entity->id())),
          '#attributes' => [
            'class' => ['btn', 'btn-primary']
          ]
        ];
      }
    }

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

    if ($role == FALSE) {
      $role = \Drupal\user\Entity\Role::create([
        'id' => $role_id,
        'label' => 'Beheerder',
      ]);
      $role->save();
    }

    $email = $form_state->getValue('email');
    $user = user_load_by_mail($email);

    if ($user == FALSE) {
      $user = User::create();

      $user->setPassword(user_password());
      $user->setEmail($email);
      $user->setUsername(explode('@', $email)[0]);

      $user->set("init", 'email');

      if (!$user->hasRole($role_id)) {
        $user->addRole($role_id);
      }

      $user->activate();

      $user->save();
    }

    $webform = \Drupal::entityTypeManager()
      ->getStorage('webform')
      ->load($form_state->getValue('template'));

    $duplicate = $webform->createDuplicate();

    $name = $form_state->getValue('municipality_name');

    $duplicate->set('id', $user->id() . '_' . $this->slug_name($name));
    $duplicate->set('title', $name);
    $duplicate->set('template', FALSE);
    $duplicate->setOwnerId($user->id());

    $duplicate->save();

    $form_state->setRedirectUrl(Url::fromRoute('entity.webform.edit_form', ['webform' => $duplicate->id()]));
    return;
  }
}
