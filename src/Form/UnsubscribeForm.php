<?php

namespace Drupal\smmg_newsletter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\smmg_newsletter\Controller\NewsletterController;


/**
 * Implements UnsubscribeForm form FormBase.
 *
 */
class UnsubscribeForm extends FormBase
{
    public $member_nid;

    /**
     *  constructor.
     */
    public function __construct()
    {
        $this->member_nid = null;
    }


    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'smmg_newsletter_unsubscribe_form';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $member_nid = NULL)
    {


        // Validate Nid Number:
        $member_nid = trim($member_nid);
        $member_nid = intval($member_nid);

        // Add JS and CSS
        $form['#attached']['library'][] = 'smmg_newsletter/smmg_newsletter.main';


        if (($member_nid == '') || ($member_nid == NULL)) {

            // Email
            $form['email'] = [
                '#type' => 'email',
                '#title' => t('Email:'),
                '#size' => 60,
                '#maxlength' => 128,
                '#required' => FALSE,
                '#prefix' => '<div class="form-group">',
                '#suffix' => '</div>',
            ];

            $form['action_yes'] = [
                '#type' => 'submit',
                '#value' => t('unsubscribe'),
            ];

        } else {

            $this->member_nid = $member_nid;

            // Text: Do you really want to unsubscribe?
            $form['question'] = [
                '#value' => t('Do you really want to unsubscribe?'),
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['']],
            ];

            // Button: Yes, Unsubscrige
            $form['action_yes'] = [
                '#type' => 'submit',
                '#value' => t('Yes, unsubscribe'),
                '#description' => $this->t('unsubscribe'),
            ];
        }

        // Button: No back to Homepage
        $url = Url::fromRoute('<front>');
        $form['back_to_front'] = [
            '#type' => 'link',
            '#url' => $url,
            '#title' => t('Back to Homepage'),
        ];

        return $form;
    }


    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if (!$this->member_nid) {

            $values = $form_state->getValues();
            $email = $values['email'];

            if ($email == '') {

                // Empty email address
                $form_state->setErrorByName('email',
                    $this->t('An email address is required.'));

            } else {

                $valiated_email = \Drupal::service('email.validator')
                    ->isValid($email);

                // Invalid Email Address
                if (FALSE === $valiated_email) {
                    $form_state->setErrorByName('email',
                        $this->t('There\'s something wrong with the email address.'));
                } else {

                    // Valid Email Address
                    $member_nid = NewsletterController::isEmailInUse($email);
                    if (!$member_nid) {

                        // Email Address not found
                        $form_state->setErrorByName('email',
                            $this->t('This email is not known to us.'));
                    } else {

                        // Email Address Found!
                        // save Member Nid globaly for Submit
                        $this->member_nid = $member_nid;
                    }
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
        $result = NewsletterController::unsubscribe($this->member_nid);
        dpm($result);

        if ($result['token']) {

            $arg['token'] = $result['token'];
            $arg['nid'] = $result['nid'];
            $form_state->setRedirect('smmg_newsletter.bye', $arg);

        }

    }


}
