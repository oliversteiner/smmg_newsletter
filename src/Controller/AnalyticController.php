<?php

namespace Drupal\smmg_newsletter\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class AnalyticController.
 */
class AnalyticController extends ControllerBase
{

  /**
   * Hello.
   *
   * @return array
   *   Return Hello string.
   */
  public function analyticsPage(): array
  {
    $build = [
      '#type' => 'markup',
      '#markup' => '    <noscript>
      <strong>We\'re sorry but realworld-vue-ts doesn\'t work properly without JavaScript enabled. Please enable it to continue.</strong>
    </noscript>
    <div id="app"></div>',
      '#allowed_tags' => ['noscript', 'div'],

    ];

    $build['#attached']['library'][] = 'smmg_newsletter/analytics';

    return $build;
  }

}
