<?php

namespace Drupal\smmg_newsletter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\smmg_newsletter\Controller\NewsletterController;

class NewsletterSettingsForm extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'smmg_newsletter_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['smmg_newsletter.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // Load Settings
    $config = $this->config('smmg_newsletter.settings');

    // load all Template Names
    $template_list = NewsletterController::getTemplateNames();

    // Options for Root Path
    $options_path_type = [
      'included' => 'Included',
      'module' => 'Module',
      'theme' => 'Theme'
    ];

    // Fieldset General
    //
    // Fieldset Email
    //   - Email Address From
    //   - Email Address To
    //   - Email Test
    //
    // Fieldset  Templates Root Path
    //     - Module or Theme
    //     - Name of Module or Theme
    //
    // Twig Templates
    //

    // Fieldset General
    // -------------------------------------------------------------
    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General'),
      '#attributes' => ['class' => ['coupon-settings-general']]
    ];

    // Fieldset Email
    // -------------------------------------------------------------
    $form['email'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email Settings'),
      '#attributes' => ['class' => ['coupon-email-settings']]
    ];

    // - Email From
    $form['email']['email_from'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email: From (newsletter@example.com)'),
      '#default_value' => $config->get('email_from')
    );

    // - Email To
    $form['email']['email_to'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email: to (sale@example.com, info@example.com)'),
      '#default_value' => $config->get('email_to')
    );

    // - Email Test
    $form['email']['email_test'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Testmode: Don\'t send email to Subscriber.'),
      '#default_value' => $config->get('email_test')
    );

    // - Exclude List
    $form['email']['email_exclude'] = array(
      '#type' => 'textfield',
      '#title' => $this->t(
        'Exclude these email-addresses fom Statistic and Errors, Separate with comma'
      ),
      '#default_value' => $config->get('email_exclude')
    );


    // - Invalid Email List
    $form['email']['invalid_email'] = array(
      '#type' => 'textarea',
      '#title' => $this->t(
        'Invalid Email List, Separate with comma'
      ),
      '#default_value' => $config->get('invalid_email')
    );

    // Fieldset Twig Templates Root Path
    // -------------------------------------------------------------

    $form['templates'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Templates'),
      '#attributes' => ['class' => ['coupon-settings-templates']]
    ];

    //   - Root of Templates
    $form['templates']['root_of_templates'] = array(
      '#markup' => $this->t('Path of Templates')
    );

    //     - Module or Theme
    $form['templates']['get_path_type'] = array(
      '#type' => 'select',
      '#options' => $options_path_type,
      // '#value' => $default_number,
      '#title' => $this->t('Module or Theme'),
      '#default_value' => $config->get('get_path_type')
    );

    //     - Name of Module or Theme
    $form['templates']['get_path_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Name of Module or Theme'),
      '#default_value' => $config->get('get_path_name')
    );

    //   - Root of Templates
    $form['templates']['templates'] = array(
      '#markup' => $this->t('Templates')
    );

    //  Twig Templates
    // -------------------------------------------------------------

    foreach ($template_list as $template) {
      $name = str_replace('_', ' ', $template);
      $name = ucwords(strtolower($name));
      $name = 'Template ' . $name;

      $form['templates']['template_' . $template] = array(
        '#type' => 'textfield',
        '#title' => $name,
        '#default_value' => $config->get('template_' . $template)
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $template_list = NewsletterController::getTemplateNames();

    // Retrieve the configuration
    $this->configFactory
      ->getEditable('smmg_newsletter.settings')
      //
      //
      // Fieldset General
      // -------------------------------------------------------------
      // - Currency
      ->set('currency', $form_state->getValue('currency'))
      // - Coupon Name Singular
      ->set(
        'coupon_name_singular',
        $form_state->getValue('coupon_name_singular')
      )
      // - Coupon Name Plural
      ->set('coupon_name_plural', $form_state->getValue('coupon_name_plural'))
      //
      //
      // Fieldset Email
      // -------------------------------------------------------------
      // - Email From
      ->set('email_from', $form_state->getValue('email_from'))
      // - Email to
      ->set('email_to', $form_state->getValue('email_to'))
      // - Email Test
      ->set('email_test', $form_state->getValue('email_test'))
      // - email_exclude
      ->set('email_exclude', $form_state->getValue('email_exclude'))
      // - invalid email list
      ->set('invalid_email', $form_state->getValue('invalid_email'))
      //
      //
      // Fieldset Twig Templates Root Path
      // -------------------------------------------------------------
      // - Module or Theme
      ->set('get_path_type', $form_state->getValue('get_path_type'))
      // - Name of Module or Theme
      ->set('get_path_name', $form_state->getValue('get_path_name'))
      //
      ->save();

    //  Twig Templates
    // -------------------------------------------------------------
    $config = $this->configFactory->getEditable('smmg_newsletter.settings');

    foreach ($template_list as $template) {
      $template_name = 'template_' . $template;
      $config->set($template_name, $form_state->getValue($template_name));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }
}
