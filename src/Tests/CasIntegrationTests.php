<?php
/**
 * @file tests
 * Tests for cas.
 *
 */
namespace Drupal\cas\Tests;
use Drupal\simpletest\WebTestBase;

/**
 * Class CasIntegrationTest
 *
 * @group cas
 * @ingroup cas
 */
class CasIntegrationTests extends WebTestBase {
  public $privileged_user;
  public $authenticated_user;
  public static $modules = ['externalauth', 'cas'];
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'CAS',
      'description' => 'CAS Integration Tests',
      'group' => t('cas'),
    );
  }

  /**
   * Setup methods for tests.q
   */
  public function setup() {
    parent::setUp();
    // Create and log in our privileged user.
    $this->privileged_user = $this->drupalCreateUser(
      [
        'administer users'
      ],
      'test_admin',
      TRUE
    );
    $this->authenticated_user = $this->drupalCreateUser(
      [],
      'test_user',
      TRUE
    );
  }

  /**
   * Test CAS Uqser Edit.
   */
  public function testCasUserEdit() {
    // Adding a cas user for the first time
    $uid = $this->privileged_user->uid;
    if ($this->privileged_user) $this->drupalLogin($this->privileged_user);
    $this->drupalGet("user/$uid/cas");
    $this->assertText('Cas User Name', 'User Name Field Exists');
    $this->assertField('cas_user_name', 'User Name');
    $edit['cas_user_name'] = 'castestuser';
    $this->drupalPostForm(NULL, $edit,  t('Save'));
  }
}