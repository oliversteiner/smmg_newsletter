<?php

namespace Drupal\smmg_newsletter\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\small_messages\Utility\Helper;
use Drupal\smmg_newsletter\Controller\NewsletterController;


/**
 * Implements SubscribeForm form FormBase.
 *
 */
class SubscribeForm extends FormBase
{
    public $gender_options;


    /**
     *  constructor.
     */
    public function __construct()
    {
        // Load Gender Options from Taxonomy
        $gender_options[0] = t('Please Chose');

        $vid = 'gender';

        $terms = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadTree($vid);

        foreach ($terms as $term) {
            $gender_options[$term->tid] = $term->name;
        }

        $this->gender_options = $gender_options;

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
        $values = $form_state->getUserInput();

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
        // eMail
        $form['email'] = [
            '#type' => 'email',
            '#title' => t('Email'),
            '#size' => 60,
            '#maxlength' => 128,
            '#required' => True,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];


        // Newsletter
        // ===============================================
        $form['subscribe'] = [
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
            '#markup' => $this->t('These details are not mandatory, but will help us to get to know you better.')
        ];

        // Gender Options
        $gender_options = $this->gender_options;


        $form['postal_address']['gender'] = [
            '#type' => 'select',
            '#title' => t('Gender'),
            '#default_value' => $gender_options[0],
            '#options' => $gender_options,
            '#required' => FALSE,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];

        // firstName
        $form['postal_address']['first_name'] = [
            '#type' => 'textfield',
            '#title' => t('First Name'),
            '#size' => 60,
            '#maxlength' => 128,
            '#required' => FALSE,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];


        // lastName
        $form['postal_address']['last_name'] = [
            '#type' => 'textfield',
            '#title' => t('Last Name'),
            '#size' => 60,
            '#maxlength' => 128,
            '#required' => FALSE,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];

        // Strasse und Nr.:
        $form['postal_address']['street_and_number'] = [
            '#type' => 'textfield',
            '#title' => t('Street and Nr.'),
            '#size' => 60,
            '#maxlength' => 128,
            '#required' => FALSE,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];

        // PLZ
        $form['postal_address']['zip_code'] = [
            '#type' => 'textfield',
            '#title' => t('ZIP'),
            '#size' => 8,
            '#maxlength' => 8,
            '#required' => FALSE,
            '#prefix' => '<div class="form-group form-group-zip-city">',
        ];

        // Ort
        $form['postal_address']['city'] = [
            '#type' => 'textfield',
            '#title' => t('City'),
            '#size' => 50,
            '#maxlength' => 50,
            '#required' => FALSE,
            '#suffix' => '</div>',
        ];


        // Telephone
        $form['postal_address']['telephone'] = [
            '#type' => 'textfield',
            '#title' => t('Phone'),
            '#size' => 60,
            '#maxlength' => 128,
            '#required' => FALSE,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];


        // Submit
        // ===============================================

        $token = Helper::generateToken();

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
    public
    function validateForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();

        $email = $values['email'];

        // Newsletter
        $subscribe = $values['subscribe'];

        if ($subscribe === 1) {

            // Empty Email
            if ($email == '') {

                // No email specified
                $form_state->setErrorByName('email',
                    $this->t('An email address is required to receive the newsletter.'));

            } else {

                $valiated_email = \Drupal::service('email.validator')
                    ->isValid($email);

                if (FALSE === $valiated_email) {
                    $form_state->setErrorByName('email',
                        $this->t('There is something wrong with this email address.'));
                }

            }
        }


    }

    /**
     * {@inheritdoc}
     */
    public
    function submitForm(array &$form, FormStateInterface $form_state)
    {
        // Form Values
        $values = $form_state->getValues();

        // Redirect Form Url Arguments
        $arg = [];

        // Send Newsletter Member
        if ($values['subscribe'] == 1) {

            $result = NewsletterController::newSubscriber($values);

            if ($result && $result['status']) {

                dpm($result);

                $arg['nid'] = intval($result['nid']);
                $arg['token'] = $result['token'];

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
