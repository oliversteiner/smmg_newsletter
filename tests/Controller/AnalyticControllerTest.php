<?php

namespace Drupal\smmg_newsletter\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the smmg_newsletter module.
 */
class AnalyticControllerTest extends WebTestBase {


  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "smmg_newsletter AnalyticController's controller functionality",
      'description' => 'Test Unit for module smmg_newsletter and controller AnalyticController.',
      'group' => 'Other',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests smmg_newsletter functionality.
   */
  public function testAnalyticController() {
    // Check that the basic functions of module smmg_newsletter.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
