<?php

namespace Drupal\ops_if\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class OpsIfConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ops_id_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form constructor.
    $form = parent::buildForm($form, $form_state);
    // Default settings.
    $config = $this->config('ops_if.settings');

    // Page title field.
    $form['acl_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service name on Fastly:'),
      '#default_value' => $config->get('acl_name'),
      '#description' => $this->t('This will determine the ACL names used on Fastly'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ops_if.settings');
    $config->set('acl_name', $form_state->getValue('acl_name'));

    $config->save();
    return parent::submitForm($form, $form_state);
  }


  protected function getEditableConfigNames() {
    return ['ops_if.settings'];
  }

}
