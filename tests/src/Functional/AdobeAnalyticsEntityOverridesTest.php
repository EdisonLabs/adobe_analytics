<?php

namespace Drupal\Tests\adobe_analytics\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests entity overrides.
 *
 * @group adobe_analytics
 */
class AdobeAnalyticsEntityOverridesTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $minkDefaultDriverClass = DrupalSelenium2Driver::class;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['adobe_analytics', 'node'];

  /**
   * The admin user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * Implementation of setUp().
   */
  public function setUp(): void {
    parent::setUp();

    // Create an admin user with all the permissions needed to run tests.
    $this->adminUser = $this->drupalCreateUser([
      'administer adobe analytics configuration',
      'access administration pages',
      'access content',
      'administer nodes',
      'bypass node access',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->setUpArticleContentType();
  }

  /**
   * Create the article content type and add an Adobe Analytics field to it.
   */
  protected function setUpArticleContentType() {
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    FieldStorageConfig::create([
      'field_name' => 'field_adobe_analytics',
      'entity_type' => 'node',
      'type' => 'adobe_analytics',
      'settings' => [],
      'cardinality' => 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => 'field_adobe_analytics',
      'label' => 'Adobe Analytics',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
      'settings' => [],
      'description' => '',
    ]);
    $field_config->save();

    \Drupal::service('entity_display.repository')
    ->getFormDisplay('node', 'article', 'default')
      ->setComponent('field_adobe_analytics', [
        'type' => 'adobe_analytics',
        'settings' => [],
      ])
      ->save();

    \Drupal::service('entity_display.repository')
    ->getViewDisplay('node', 'article', 'default')
      ->setComponent('field_adobe_analytics', [
        'type' => 'adobe_analytics',
        'settings' => [],
      ])
      ->save();
  }

  /**
   * Creates a node and tests the Adobe Analytics field.
   */
  public function testEntityOverrides() {
    // By default, global variables and snippet are shown.
    \Drupal::configFactory()->getEditable('adobe_analytics.settings')
      ->set('codesnippet', 'var globalSnippet = "baz";')
      ->set('extra_variables', [
        [
          'name' => 'extraVariableName',
          'value' => 'extraVariableValue',
        ],
      ])
      ->save();

    $edit = [
      'title[0][value]' => $this->randomMachineName(),
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');

    $this->assertSession()->responseContains('globalSnippet');
    $this->assertSession()->responseContains('extraVariableName');

    // Hide global variables and snippet, plus add custom JavaScript.
    $this->drupalGet('node/1/edit');
    $this->click('#edit-field-adobe-analytics-0-adobe-analytics > summary');
    $edit = [
      'field_adobe_analytics[0][adobe_analytics][include_custom_variables]' => FALSE,
      'field_adobe_analytics[0][adobe_analytics][include_main_codesnippet]' => FALSE,
      'field_adobe_analytics[0][adobe_analytics][codesnippet]' => 'var customVar = "customValue";',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseNotContains('globalSnippet');
    $this->assertSession()->responseNotContains('extraVariableName');
    $this->assertSession()->responseContains('customVar');
  }

}
