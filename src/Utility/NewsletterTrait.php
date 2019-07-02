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

  public static function sendNotificationMail($nid, $token)
  {
    $module = self::getModuleName();
    $data = NewsletterController::newsletterVariables($nid, $token);
    $templates = NewsletterController::getTemplates();

    Email::sendNotificationMail($module, $data, $templates);
  }


}
