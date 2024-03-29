<?php

namespace Drupal\smmg_newsletter\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mollo_utils\Utility\MolloUtils;
use Drupal\smmg_newsletter\Controller\NewsletterController;

/**
 * Implements SubscribeForm form FormBase.
 *
 */
class SubscribeForm extends FormBase
{
  public $options_gender;
  public $default_gender;

  public $options_country;
  public $default_country;

  /**
   *  constructor.
   */
  public function __construct()
  {
    // Load Gender Options from Taxonomy
    $gender_options[0] = t('Please Chose');

    $vid_gender = 'smmg_gender';
    $this->options_gender = MolloUtils::getTermsByID($vid_gender);
    $this->options_gender[0] = t('Please Chose');
    $this->default_gender = 0;

    $vid_country = 'smmg_country';
    $this->options_country = MolloUtils::getTermsByID($vid_country);
    $this->default_country = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'smmg_newsletter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // $values = $form_state->getUserInput();

    // Spam and Bot Protection
    honeypot_add_form_protection($form, $form_state, [
      'honeypot',
      'time_restriction',
    ]);

    // JS and CSS
    $form['#attached']['library'][] = 'smmg_newsletter/smmg_newsletter.main';

    // Disable browser HTML5 validation
    $form['#attributes']['novalidate'] = 'novalidate';

    // Subscribe
    // ==============================================

    $form['email'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email'),
      '#attributes' => ['class' => ['']],
    ];

    // eMail
    $form['email']['email'] = [
      '#type' => 'email',
      '#title' => t('Email'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => true,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // Newsletter
    // ===============================================
    $form['email']['subscribe'] = [
      '#title' => $this->t('I want to receive the newsletter.'),
      '#type' => 'checkbox',
      '#default_value' => 1,
    ];

    // Address
    // ==============================================

    $form['postal_address'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Address'),
      '#attributes' => ['class' => ['']],
    ];

    $form['postal_address']['info'] = [
      '#theme' => '',
      '#markup' => $this->t(
        'These details are not mandatory, but will help us to get to know you better.'
      ),
    ];

    $form['postal_address']['gender'] = [
      '#type' => 'select',
      '#title' => t('Gender'),
      '#default_value' => $this->default_gender,
      '#options' => $this->options_gender,
      '#required' => false,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // firstName
    $form['postal_address']['first_name'] = [
      '#type' => 'textfield',
      '#title' => t('First Name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => false,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // lastName
    $form['postal_address']['last_name'] = [
      '#type' => 'textfield',
      '#title' => t('Last Name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => false,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // Strasse und Nr.:
    $form['postal_address']['street_and_number'] = [
      '#type' => 'textfield',
      '#title' => t('Street and Nr.'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => false,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // PLZ
    $form['postal_address']['zip_code'] = [
      '#type' => 'textfield',
      '#title' => t('ZIP'),
      '#size' => 8,
      '#maxlength' => 8,
      '#required' => false,
      '#prefix' => '<div class="form-group form-group-zip-city">',
    ];

    // Ort
    $form['postal_address']['city'] = [
      '#type' => 'textfield',
      '#title' => t('City'),
      '#size' => 50,
      '#maxlength' => 50,
      '#required' => false,
      '#suffix' => '</div>',
    ];

    $form['postal_address']['country'] = [
      '#type' => 'select',
      '#title' => t('Country'),
      '#default_value' => $this->default_country,
      '#options' => $this->options_country,
      '#required' => false,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];
    // Phone
    $form['postal_address']['phone'] = [
      '#type' => 'textfield',
      '#title' => t('Phone'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => false,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // Submit
    // ===============================================

    $token = MolloUtils::generateToken();

    $form['token'] = [
      '#type' => 'hidden',
      '#value' => $token,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['save_data'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
      '#allowed_tags' => ['style'],
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state):void
  {
    $values = $form_state->getValues();

    $email = $values['email'];

    // Newsletter
    $subscribe = $values['subscribe'];

    if ($subscribe === 1) {
      // Empty Email
      if ($email == '') {
        // No email specified
        $form_state->setErrorByName(
          'email',
          $this->t('An email address is required to receive the newsletter.')
        );
      } else {
        $valiated_email = \Drupal::service('email.validator')->isValid($email);

        if (false === $valiated_email) {
          $form_state->setErrorByName(
            'email',
            $this->t('There is something wrong with this email address.')
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Form Values
    $values = $form_state->getValues();
    $token = $values['token'];

    // Redirect Form Url Arguments
    $arg = [];

    // Send Newsletter Member
    if ($values['subscribe'] == 1) {
      try {
        $result = NewsletterController::newSubscriber($values);
      } catch (InvalidPluginDefinitionException $e) {
      } catch (PluginNotFoundException $e) {
      }

      if ($result && $result['status']) {
        $arg['nid'] = (int)$result['nid'];
        $arg['token'] = $token;

        $form_state->setRedirect('smmg_newsletter.thank_you', $arg);
      } else {
        // Error on create new Member
        if ($result['message']) {
          $this->messenger()->addMessage($result['message'], 'error');
        }
      }
    }
  }
}
