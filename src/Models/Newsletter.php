<?php

namespace Drupal\smmg_newsletter\Types;

use Drupal\node\Entity\Node;
use Drupal\small_messages\Models\Message;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * Class Newsletter
 * @package Drupal\smmg_newsletter\Types
 *
 * Fields
 * -----------------------------
 *  - field_smmg_design_template
 *  - field_smmg_message_is_send
 *  - field_smmg_message_is_template
 *  - field_smmg_message_group
 *  - field_smmg_message_text
 *  - field_smmg_send_date
 *  - field_smmg_subscriber_group
 *
 *
 */
class Newsletter extends Message
{

  public const name = "Newsletter";


}
