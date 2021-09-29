<?php

namespace Drupal\fastly_streamline_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class FsaConfigForm extends ConfigFormBase {

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
    $config = $this->config('fastly_streamline_access.settings');

    // Page title field.
    $form['acl_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Standard Access Control List (ACL) name:'),
      '#default_value' => $config->get('acl_name'),
      '#description' => $this->t(
        'This will determine the ACL names used on Fastly'
      ),
    ];

    // Page title field.
    $form['acl_long_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Long lived Access Control List (ACL) name:'),
      '#default_value' => $config->get('acl_long_name'),
      '#description' => $this->t(
        'This will determine the ACL names used on Fastly'
      ),
    ];

    $form['passphrase_fieldset'] = [
      '#type' => 'details',
      '#title' => t('Passphrase'),
      '#open' => empty($config->get('passphrase')),
    ];

    if (!empty($config->get('passphrase'))) {
      $form['passphrase_fieldset']['override_passphrase'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override passphrase:'),
        '#description' => $this->t(
          'The passphrase is currently set - check here to override it'
        ),
      ];
    }

    // Page title field.
    $form['passphrase_fieldset']['passphrase'] = [
      '#type' => 'password',
      '#title' => $this->t('Api Pass phrase:'),
      '#default_value' => $config->get('passphrase'),
      '#description' => $this->t(
        'This is the pass phrase used to decrypt the fastly API key'
      ),
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
    $config = $this->config('fastly_streamline_access.settings');
    $config->set('acl_name', $form_state->getValue('acl_name'));
    $config->set('acl_long_name', $form_state->getValue('acl_long_name'));

    if (empty($config->get('passphrase')) || $form_state->getValue(
        'override_passphrase'
      ) == 1) {
      $config->set('passphrase', $form_state->getValue('passphrase'));
    }
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return ['fastly_streamline_access.settings'];
  }

}
