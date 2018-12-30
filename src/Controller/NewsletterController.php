<?php


namespace Drupal\smmg_newsletter\Controller;


use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\small_messages\Utility\Helper;
use Drupal\smmg_newsletter\Utility\NewsletterTrait;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class NewsletterController extends ControllerBase
{

    use NewsletterTrait;

    /**
     * @return mixed
     */
    public function landing_page()
    {
        $url_unsubscribe = Url::fromRoute('smmg_newsletter.unsubscribe');
        $url_subscribe = Url::fromRoute('smmg_newsletter.subscribe');


        $variables['url']['subscribe'] = $url_subscribe;
        $variables['url']['unsubscribe'] = $url_unsubscribe;

        $templates = self::getTemplates();
        $template = file_get_contents($templates['landing_page']);

        $build = [
            'description' => [
                '#type' => 'inline_template',
                '#template' => $template,
                '#attached' => ['library' => ['smmg_newsletter/smmg_newsletter.main']],
                '#context' => $variables,
            ],
        ];
        return $build;

    }

    /**
     * @param null $email
     * @return array
     */
    public static function subscribeDirect($email = NULL)
    {
        $email = trim($email);
        $token = Helper::generateToken();

        $valid_email = \Drupal::service('email.validator')
            ->isValid($email);

        if (!empty($email) && $valid_email) {


            // Subscribe direct
            $data['email'] = $email;
            $data['subscribe'] = TRUE;
            $data['token'] = $token;

            $result = self::newSubscriber($data);

            if ($result['status']) {
                $output = self::thankYouPage($result['nid'], $token);

            } else {
                $output['error'] = [
                    '#markup' => 'Something went wrong...'
                ];
            }
        } else {
            $output['error'] = [
                '#markup' => 'Invalid email'
            ];
        }
        return $output;

    }

    /**
     * @param $nid
     * @return array
     */
    static function subscribe($nid)
    {
        return self::updateSubscriber($nid, true);
    }


    /**
     * @param $nid
     * @return array
     */
    static function unSubscribe($nid)
    {
        return self::updateSubscriber($nid, false);
    }


    /**
     * @param null $nid
     * @param bool $subscribe
     * @return array
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     */
    public static function updateSubscriber($nid = NULL, $subscribe = true)
    {
        $output = [
            'status' => FALSE,
            'mode' => '',
            'nid' => $nid,
            'message' => '',
            'type' => 'status', // status, warning, error
            'token' => false,
        ];

        // valiade number:
        $nid = trim($nid);
        $nid = intval($nid);

        if ($nid !== '') {

            // Load Node
            $entity = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->load($nid);

            // Node exists ?
            if ($entity && $entity->bundle() == 'member') {


                // Save Subscription
                $entity->get('field_smmg_accept_newsletter')->setValue($subscribe);
                try {
                    $entity->save();
                } catch (EntityStorageException $e) {
                }

                // Get Token
                $output['token'] = Helper::getToken($entity);
                $output['status'] = true;

                if ($subscribe) {
                    $output['message'] = t("Successfully subscribed from newsletter");
                } else {
                    $output['message'] = t("Successfully unsubscribed from newsletter");
                }

            }
        } else {
            $output['message'] = t("This user does not exist");
            $output['level'] = 'error';
        }

        return $output;
    }

    /**
     * @param $data
     * @return array
     */
    public static function newSubscriber($data)
    {
        // Token
        $token = $data['token'];

        $output = [
            'status' => FALSE,
            'mode' => 'save',
            'nid' => FALSE,
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

                // Load List for origin
                $vid = 'smmg_origin';
                $origin_list = Helper::getTermsByName($vid);

                $storage = \Drupal::entityTypeManager()->getStorage('node');
                $new_member = $storage->create(
                    [
                        'type' => 'member',
                        'title' => $title,
                        'field_gender' => $gender,
                        'field_first_name' => $first_name,
                        'field_last_name' => $last_name,
                        'field_phone' => $phone,
                        'field_street_and_number' => $street_and_number,
                        'field_zip_code' => $zip_code,
                        'field_city' => $city,
                        'field_email' => $email,
                        'field_smmg_token' => $token,
                        'field_smmg_origin' => $origin_list['newsletter'],

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
                    $output['status'] = TRUE;
                    $output['nid'] = $nid;
                    $output['token'] = $token;


                    self::sendNotivicationMail($nid, $token);
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
                $nodes = \Drupal::entityTypeManager()->getStorage('node')
                    ->loadByProperties(
                        ['type' => 'member',
                            'field_email' => $email,]
                    );
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
     */
    public static function byePage($nid = false, $token = false)
    {
        if ($nid != false && !is_numeric($nid)) {
            throw new AccessDeniedHttpException();
        }

        if ($token == false) {
            throw new AccessDeniedHttpException();
        }

        $templates = self::getTemplates();
        $template = file_get_contents($templates['bye_bye']);

        $build = [
            'description' => [
                '#type' => 'inline_template',
                '#template' => $template,
                '#attached' => ['library' => ['smmg_newsletter/smmg_newsletter.main']],
                '#context' => self::newsletterVariables($nid, $token),
            ],
        ];
        return $build;
    }


    /**
     * @param bool $nid
     * @param bool $token
     * @return array
     */
    public static function thankYouPage($nid = false, $token = false)
    {
        if ($nid != false && !is_numeric($nid)) {
            throw new AccessDeniedHttpException();
        }

        if ($token == false) {
            throw new AccessDeniedHttpException();
        }
        $templates = self::getTemplates();
        $template = file_get_contents($templates['thank_you']);

        $build = [
            'description' => [
                '#type' => 'inline_template',
                '#template' => $template,
                '#attached' => ['library' => ['smmg_newsletter/smmg_newsletter.main']],
                '#context' => self::newsletterVariables($nid, $token),
            ],
        ];
        return $build;
    }

    /**
     * @param null $nid
     * @param null $token
     * @return array
     */
    public static function newsletterVariables($nid, $token)
    {
        $variables = [];

        $variables['address']['gender'] = '';
        $variables['address']['first_name'] = '';
        $variables['address']['last_name'] = '';
        $variables['address']['street_and_number'] = '';
        $variables['address']['zip_code'] = '';
        $variables['address']['city'] = '';
        $variables['address']['email'] = '';
        $variables['address']['phone'] = '';

        $variables['newsletter'] = false;

        $variables['id'] = $nid;
        $variables['token'] = $token;

        // Clean Input
        $nid = trim($nid);
        $nid = intval($nid);

        // Load Terms from Taxonomy
        $gender_list = Helper::getTermsByID('gender');

        // Member & Newsletter
        if ($nid) {

            $member = Node::load($nid);

            if ($member && $member->bundle() == 'member') {

                // Check Token
                $node_token = Helper::getFieldValue($member, 'smmg_token');

                if ($token != $node_token) {
                    throw new AccessDeniedHttpException();
                }

                // Address
                $variables['address']['gender'] = Helper::getFieldValue($member, 'gender', $gender_list);
                $variables['address']['first_name'] = Helper::getFieldValue($member, 'first_name');
                $variables['address']['last_name'] = Helper::getFieldValue($member, 'last_name');
                $variables['address']['street_and_number'] = Helper::getFieldValue($member, 'street_and_number');
                $variables['address']['zip_code'] = Helper::getFieldValue($member, 'zip_code');
                $variables['address']['city'] = Helper::getFieldValue($member, 'city');
                $variables['address']['email'] = Helper::getFieldValue($member, 'email');
                $variables['address']['phone'] = Helper::getFieldValue($member, 'phone');

                // Newsletter
                $variables['newsletter'] = Helper::getFieldValue($member, 'smmg_accept_newsletter');
            }
        }
        return $variables;
    }


    public function sandboxEmail($coupon_order_nid, $token = null, $output_mode = 'html')
    {
        $build = false;

        // Get Content
        $data = self::newsletterVariables($coupon_order_nid, $token);
        $data['sandbox'] = true;

        $templates = self::getTemplates();


        // HTML Email
        if ($output_mode == 'html') {

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
        if ($output_mode == 'plain') {

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


    public function sandboxSendEmail($nid, $token = null, $output_mode = 'html')
    {

        $build = $this->sandboxEmail($nid, $token, $output_mode);

        self::sendNotivicationMail($nid, $token);

        return $build;
    }


    public static function getTemplateNames()
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


    public static function getTemplates()
    {
        $module = 'smmg_newsletter';
        $template_names = self::getTemplateNames();
        $templates = Helper::getTemplates($module, $template_names);

        return $templates;
    }
}