<?php

namespace Drupal\smmg_newsletter\Utility;

use Drupal\small_messages\Utility\Email;
use Drupal\smmg_newsletter\Controller\NewsletterController;

trait NewsletterTrait
{
    public static function getModuleName()
    {
        return 'smmg_newsletter';
    }

    public static function sendNotivicationMail($nid, $token)
    {
        $module = self::getModuleName();
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
        $module = self::getModuleName();
        return Email::getEmailAddressesFromConfig($module);

    }
}