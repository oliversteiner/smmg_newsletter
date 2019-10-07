<?php

namespace Drupal\smmg_newsletter\Models;

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
  public const name = 'Newsletter';
  public const type = 'smmg_message';
  public const module = 'smmg_message';

  public const field_design_template = 'field_smmg_design_template';
  public const field_is_send = ' field_smmg_message_is_send';
  public const field_is_template = 'field_smmg_message_is_template';
  public const field_category = 'field_smmg_message_group';
  public const field_text = 'field_smmg_message_text';
  public const field_body = 'body';
  public const field_send_date = 'field_smmg_send_date';
  public const field_group = 'field_smmg_subscriber_group';

  /* Drupal Fields */

  /* Drupal Taxonomy */
  public const term_subscriber_group = 'smmg_subscriber_group';
  public const term_category = 'smmg_message_group';
}


