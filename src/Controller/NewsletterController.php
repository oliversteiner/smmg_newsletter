<?php

namespace Drupal\smmg_newsletter\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\small_messages\Utility\Helper;
use Drupal\smmg_member\Models\Member;
use Drupal\smmg_newsletter\Models\Newsletter;
use Drupal\smmg_newsletter\Utility\NewsletterTrait;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Zend\Diactoros\Response\JsonResponse;

class NewsletterController extends ControllerBase
{
  use NewsletterTrait;

  public const Module_Name = 'smmg_newsletter';


  /**
   * @return mixed
   */
  public function landing_page()
  {
    $url_unsubscribe = Url::fromRoute(Newsletter::module.'.unsubscribe.form');
    $url_subscribe = Url::fromRoute(Newsletter::module.'.subscribe.form');

    $variables['url']['subscribe'] = $url_subscribe;
    $variables['url']['unsubscribe'] = $url_unsubscribe;

    $templates = self::getTemplates();
    $template = file_get_contents($templates['landing_page']);

    $build = [
      'description' => [
        '#type' => 'inline_template',
        '#template' => $template,
        '#attached' => ['library' => [Newsletter::module.'/smmg_newsletter.main']],
        '#context' => $variables,
      ],
    ];
    return $build;
  }

  /**
   * @param null $email
   * @return array
   */
  public static function subscribeDirect($email = null): array
  {
    $email = trim($email);
    $token = Helper::generateToken();

    $valid_email = \Drupal::service('email.validator')->isValid($email);

    if (!empty($email) && $valid_email) {
      // Subscribe direct
      $data['email'] = $email;
      $data['subscribe'] = true;
      $data['token'] = $token;

      $result = self::newSubscriber($data);

      if ($result['status']) {
        $output = self::thankYouPage($result['nid'], $token);
      } else {
        $output['error'] = [
          '#markup' => 'Something went wrong...',
        ];
      }
    } else {
      $output['error'] = [
        '#markup' => 'Invalid email',
      ];
    }
    return $output;
  }

  /**
   * @param $nid
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public static function subscribe($nid): array
  {
    return self::updateSubscriber($nid, true);
  }

  /**
   * @param $nid
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws \Exception
   *
   * @route smmg_newsletter.unsubscribe
   */
  public static function unSubscribe($nid, $message_id = null): array
  {
    $result = self::updateSubscriber($nid, false, $message_id);

    // get Template
    $templates = self::getTemplates();
    $template = file_get_contents($templates['bye_bye']);

    $build = [
      'description' => [
        '#type' => 'inline_template',
        '#template' => $template,
        '#attached' => ['library' => [Nesletter::module.'/smmg_newsletter.main']],
        '#context' => self::newsletterVariables($nid, $result['token']),
      ],
    ];
    return $build;
  }

  /**
   * @param null $nid
   * @param bool $subscribe
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public static function updateSubscriber(
    $nid = null,
    $subscribe = true,
    $message_id = null
  ): array {
    // TODO add unsubscribe to json_data

    // valiade number:
    $nid = trim($nid);

    if (!is_numeric($nid)) {
      throw new AccessDeniedHttpException();
    }

    $output = [
      'status' => false,
      'mode' => '',
      'nid' => $nid,
      'message' => '',
      'type' => 'status', // status, warning, error
      'token' => false,
    ];

    if ($nid) {
      // Load Node
      $entity = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($nid);

      // Node exists ?
      if ($entity && $entity->bundle() === 'smmg_member') {
        // Save Subscription
        $entity->get('field_smmg_accept_newsletter')->setValue($subscribe);

        try {
          $entity->save();
          // Get Token
          $output['token'] = Helper::getToken($entity);
          $output['status'] = true;
        } catch (EntityStorageException $e) {
        }

        if ($subscribe) {
          $output['message'] = t('Successfully subscribed to newsletter.');
        } else {
          $output['message'] = t('Successfully unsubscribed from newsletter.');
        }
      }
    } else {
      $output['message'] = t('This user does not exist.');
      $output['level'] = 'error';
    }

    return $output;
  }

  /**
   * @param null $token
   * @param null $nid
   * @return JsonResponse
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws \Exception
   *
   * @route smmg_newsletter.opt_in
   */
  public static function optInNewsletter(
    $nid = null,
    $token = null
  ): JsonResponse {
    $result = [
      'status' => false,
      'mode' => '',
      'nid' => $nid,
      'message' => '',
      'type' => 'status', // status, warning, error
      'token' => false,
    ];

    // get ids for smmg_subscriber_group  and 'Newsletter'
    $term_name = 'Newsletter';
    $vid = 'smmg_subscriber_group';
    $tid_newsletter = Helper::getTermIDByName($term_name, $vid);

    // Validate input ID:
    $nid = trim($nid);
    $nid = (int) $nid;

    if ($nid) {
      // Load Node
      $node = Node::load($nid);

      // Node exists ?
      if ($node && $node->bundle() === 'smmg_member') {
        // Check Token
        $token_from_member = Helper::getToken($node);

        // If Token false, return error
        if ($token_from_member !== $token) {
          $result['type'] = 'error';
          $result['message'] = 'False Token';
        } else {
          // add Member to Group 'Newsletter'
          // get all Group IDs of Member
          $group_ids = Helper::getFieldValue(
            $node,
            'smmg_subscriber_group',
            false,
            true
          );

          // if Member is not in Grop 'Newsletter', add him
          if (!in_array($tid_newsletter, $group_ids, true)) {
            array_push($group_ids, $tid_newsletter);
            $node->set('field_smmg_subscriber_group', $group_ids);
          }

          // Save Subscription
          $node->set('field_smmg_accept_newsletter', 1);

          try {
            $node->save();
            // Get Token
            $result['token'] = Helper::getToken($node);
            $result['status'] = true;
          } catch (EntityStorageException $e) {
            $result['type'] = 'error';
            $result['message'] = 'Can\'t Save Node';
          }
        }
      }
    } else {
      $result['type'] = 'error';
      $result['message'] = 'No Node found with ID: ' . $nid;
    }
    return new JsonResponse($result);
  }

  /**
   * @param $data
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public static function newSubscriber($data): array
  {
    // Token
    $token = $data['token'];

    $output = [
      'status' => false,
      'mode' => 'save',
      'nid' => false,
      'message' => '',
      'token' => $token,
    ];

    // Newsletter
    $subscribe = $data['subscribe'];

    // Fieldset address
    $email = $data['email'];
    $gender = $data['gender'];
    $first_name = $data['first_name'];
    $last_name = $data['last_name'];
    $street_and_number = $data['street_and_number'];
    $zip_code = $data['zip_code'];
    $city = $data['city'];
    $country = $data['country'];
    $phone = $data['phone'];

    $member_nid = self::isEmailInUse($email);

    if ($member_nid) {
      $member = self::subscribe($member_nid);

      $output = [
        'status' => true,
        'mode' => 'save',
        'nid' => $member_nid,
        'token' => $member['token'],
        'message' => 'Member Update',
      ];
    } else {
      if ($first_name && $last_name) {
        $title = $first_name . ' ' . $last_name;
      } else {
        $title = $email;
      }

      try {
        // Origin
        $origin = 'Newsletter';
        $origin_tid = Helper::getOrigin($origin);

        $storage = \Drupal::entityTypeManager()->getStorage('node');
        $new_member = $storage->create([
          'type' => 'smmg_member',
          'title' => $title,
          'field_gender' => $gender,
          'field_first_name' => $first_name,
          'field_last_name' => $last_name,
          'field_phone' => $phone,
          'field_street_and_number' => $street_and_number,
          'field_zip_code' => $zip_code,
          'field_city' => $city,
          'field_country' => $country,
          'field_email' => $email,
          'field_smmg_token' => $token,
          'field_smmg_origin' => $origin_tid,

          // Newsletter
          'field_smmg_accept_newsletter' => $subscribe,
        ]);

        // Save
        try {
          $new_member->save();
        } catch (EntityStorageException $e) {
        }

        $new_member_nid = $new_member->id();

        // if OK
        if ($new_member_nid) {
          $nid = $new_member_nid;

          $message = t('Information successfully saved');
          $output['message'] = $message;
          $output['status'] = true;
          $output['nid'] = $nid;
          $output['token'] = $token;

          self::sendNotificationMail($nid, $token);
        }
      } catch (InvalidPluginDefinitionException $e) {
      } catch (PluginNotFoundException $e) {
      }
    }
    return $output;
  }

  /**
   * @param $email
   *
   * @return false or nid
   */
  static function isEmailInUse($email)
  {
    $result = false;

    if (!empty($email)) {
      try {
        $nodes = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadByProperties([
            'type' => 'smmg_member',
            'field_email' => $email,
          ]);
      } catch (InvalidPluginDefinitionException $e) {
      } catch (PluginNotFoundException $e) {
      }

      if ($node = reset($nodes)) {
        // found $node that matches the title
        $result = $node->id();
      }
    }
    return $result;
  }

  /**
   * @param bool $nid
   * @param bool $token
   * @return array
   * @throws \Exception
   */
  public static function byePage($nid, $token = false): array
  {
    if (!is_numeric($nid)) {
      throw new AccessDeniedHttpException();
    }

    if (!$token) {
      throw new AccessDeniedHttpException();
    }

    $templates = self::getTemplates();
    $template = file_get_contents($templates['bye_bye']);

    $build = [
      'description' => [
        '#type' => 'inline_template',
        '#template' => $template,
        '#attached' => ['library' => [Nesletter::module.'/smmg_newsletter.main']],
        '#context' => self::newsletterVariables($nid, $token),
      ],
    ];
    return $build;
  }

  /**
   * @param int $nid
   * @param bool $token
   * @return array
   * @throws \Exception
   */
  public static function thankYouPage($nid, $token): array
  {
    if (!is_numeric($nid)) {
      throw new AccessDeniedHttpException();
    }

    if (!$token) {
      throw new AccessDeniedHttpException();
    }
    $templates = self::getTemplates();
    $template = file_get_contents($templates['thank_you']);

    $build = [
      'description' => [
        '#type' => 'inline_template',
        '#template' => $template,
        '#attached' => ['library' => [Nesletter::module.'/smmg_newsletter.main']],
        '#context' => self::newsletterVariables($nid, $token),
      ],
    ];
    return $build;
  }

  /**
   * @param null $nid
   * @param null $token
   * @return array
   * @throws \Exception
   */
  public static function newsletterVariables($nid, $token): array
  {
    $variables = [];

    $variables['address']['gender'] = '';
    $variables['address']['first_name'] = '';
    $variables['address']['last_name'] = '';
    $variables['address']['street_and_number'] = '';
    $variables['address']['zip_code'] = '';
    $variables['address']['city'] = '';
    $variables['address']['country'] = '';
    $variables['address']['email'] = '';
    $variables['address']['phone'] = '';

    $variables['newsletter'] = false;

    $variables['id'] = $nid;
    $variables['token'] = $token;
    $variables['module'] = self::getModuleName();

    // Clean Input
    $nid = (int) trim($nid);

    // Load Terms from Taxonomy
    $gender_list = Helper::getTermsByID('gender');

    // Member & Newsletter
    if ($nid) {
      $member = Node::load($nid);

      if ($member && $member->bundle() == 'smmg_member') {
        // Check Token
        $node_token = Helper::getFieldValue($member, 'smmg_token');

        if ($token != $node_token) {
          throw new AccessDeniedHttpException();
        }

        // Address
        $variables['address']['gender'] = Helper::getFieldValue(
          $member,
          'gender',
          $gender_list
        );

        $variables['address']['first_name'] = Helper::getFieldValue(
          $member,
          'first_name'
        );
        $variables['address']['last_name'] = Helper::getFieldValue(
          $member,
          'last_name'
        );
        $variables['address']['street_and_number'] = Helper::getFieldValue(
          $member,
          'street_and_number'
        );
        $variables['address']['zip_code'] = Helper::getFieldValue(
          $member,
          'zip_code'
        );
        $variables['address']['city'] = Helper::getFieldValue($member, 'city');

        $variables['address']['country'] = Helper::getFieldValue(
          $member,
          'country',
          $gender_list
        );

        $variables['address']['email'] = Helper::getFieldValue(
          $member,
          'email'
        );
        $variables['address']['phone'] = Helper::getFieldValue(
          $member,
          'phone'
        );

        // Newsletter
        $variables['newsletter'] = Helper::getFieldValue(
          $member,
          'smmg_accept_newsletter'
        );

        // Title
        $variables['title'] =
          'Newsletter - ' .
          $variables['address']['first_name'] .
          ' ' .
          $variables['address']['last_name'];
      }
    }
    return $variables;
  }

  /**
   * @param $coupon_order_nid
   * @param null $token
   * @param string $output_mode
   * @return array|bool
   * @throws \Exception
   */
  public function sandboxEmail(
    $coupon_order_nid,
    $token = null,
    $output_mode = 'html'
  ) {
    $build = false;

    // Get Content
    $data = self::newsletterVariables($coupon_order_nid, $token);
    $data['sandbox'] = true;

    $templates = self::getTemplates();

    // HTML Email
    if ($output_mode === 'html') {
      // Build HTML Content
      $template = file_get_contents($templates['email_html']);
      $build_html = [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $data,
        ],
      ];

      $build = $build_html;
    }

    // Plaintext
    if ($output_mode === 'plain') {
      // Build Plain Text Content
      $template = file_get_contents($templates['email_plain']);

      $build_plain = [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $data,
        ],
      ];

      $build = $build_plain;
    }

    return $build;
  }

  /**
   * @param $nid
   * @param null $token
   * @param string $output_mode
   * @return array|bool
   * @throws \Exception
   */
  public function sandboxSendEmail($nid, $token = null, $output_mode = 'html')
  {
    $build = $this->sandboxEmail($nid, $token, $output_mode);

    self::sendNotificationMail($nid, $token);

    return $build;
  }

  /**
   * @return array
   */
  public static function getTemplateNames(): array
  {
    $templates = [
      'landing_page',
      'bye_bye',
      'thank_you',
      'email_html',
      'email_plain',
    ];

    return $templates;
  }

  /**
   * @return array
   */
  public static function getTemplates(): array
  {
    $module = Newsletter::module;
    $template_names = self::getTemplateNames();
    return Helper::getTemplates($module, $template_names);
  }

  public static function APINewsletter($id): JsonResponse
  {
    $Newsletter = new Newsletter($id);
    $data = $Newsletter->getData();
    return new JsonResponse($data);
  }

  public static function APINewsletters(
    $start = 0,
    $length = 0,
    $subscriber_group = false
  ): JsonResponse {
    $Newsletters = [];
    $message = [];

    // Search all Newsletters
    // Query with entity_type.manager
    $query = \Drupal::entityTypeManager()->getStorage('node');
    $query_count = $query
      ->getQuery()
      ->condition('type', Newsletter::type)
      ->sort('changed', 'ASC')
      ->count()
      ->execute();

    // Count Newsletters
    $number_of = $query_count;

    // if nothing found
    if ($number_of === 0) {
      $response = [
        'message' => 'no ' . Newsletter::name . ' found',
        'count' => 0,
      ];
      return new JsonResponse($response);
    }

    // get Nids
    if ($subscriber_group) {
      $message[] =
        'filter ' .
        Newsletter::field_subscriber_group .
        ': ' .
        (int) $subscriber_group;
      $query_result = $query
        ->getQuery()
        ->condition('type', Newsletter::type)
        ->sort('changed', 'DESC')
        ->range($start, $length)
        ->condition(
          Newsletter::field_subscriber_group,
          (int) $subscriber_group,
          'IN'
        )
        ->execute();
    } else {
      $query_result = $query
        ->getQuery()
        ->condition('type', Newsletter::type)
        ->sort('changed', 'DESC')
        ->range((int) $start, (int) $length)
        ->execute();
    }

    $message = 'v5';
    $set = count($query_result);
    $ids = [];

    // Load Data
    foreach ($query_result as $nid) {
      $newsletter = new Newsletter($nid);
      $Newsletters[] = $newsletter->getData();
      $ids[] = (int)$nid;
    }

    // build Response
    $response = [
      'message' => $message,
      'count' => (int) $number_of,
      'set' => (int) $set,
      'start' => (int) $start,
      'length' => (int) $length,
      'subscriberGroup' => (int) $subscriber_group,
      'newsletters' => $Newsletters,
      'ids' => $ids,
    ];

    // return JSON
    return new JsonResponse($response);
  }


  public static function countNewsletterInCategory($field, $tid)
  {
    $query = \Drupal::entityTypeManager()->getStorage('node');
    $query_count = $query
      ->getQuery()
      ->condition('type', Newsletter::type)
      ->condition($field, $tid)
      ->count()
      ->execute();

    return $query_count;
  }


  function APITermsCategory()
  {
    $name = 'category';
    $vocabulary = Newsletter::term_category;
    $field= Newsletter::field_subscriber_group;
    $terms = [];

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree($vocabulary);
    foreach ($nodes as $node) {
      $terms[] = array(
        'id' => (int)$node->tid,
        'name' => $node->name,
        'count' => (int)self::countNewsletterInCategory($field, $node->tid),
      );
    }

    $response = [
      'name' => 'api/terms/'.$name,
      'version' => '1.0.0',
      'terms' => $terms];

    return new JsonResponse($response);
  }

}
