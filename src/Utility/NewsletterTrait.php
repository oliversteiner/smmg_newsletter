<?php

namespace Drupal\smmg_newsletter\Utility;

use Drupal\small_messages\Utility\Email;
use Drupal\smmg_newsletter\Controller\NewsletterController;

trait NewsletterTrait
{


    public static function sendNotivicationMail($nid, $token)
    {
        $module = 'smmg_newsletter';
        $data = NewsletterController::newsletterVariables($nid, $token);
        $templates = NewsletterController::getTemplates();

        Email::sendNotificationMail($module, $data, $templates);
    }

    public static function sendmail($data)
    {
        Email::sendmail($data);
    }

    public static function generateMessageHtml($message)
    {
        return Email::generateMessageHtml($message);
    }

    public static function getEmailAddressesFromConfig()
    {
        $module = 'smmg_newsletter';
        return Email::getEmailAddressesFromConfig($module);

    }
}