<?php

namespace Drupal\Tests\cas\Functional;

use Drupal\cas\CasPropertyBag;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests CAS admin settings form.
 *
 * @group cas
 */
class CasAdminSettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['cas'];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Disable strict schema cheking.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser(['administer account settings']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests Standard installation profile.
   */
  public function testCasAutoAssignedRoles() {
    $role_id = $this->drupalCreateRole([]);
    $role_id_2 = $this->drupalCreateRole([]);
    $edit = [
      'user_accounts[auto_register]' => TRUE,
      'user_accounts[auto_assigned_roles_enable]' => TRUE,
      'user_accounts[auto_assigned_roles][]' => [$role_id, $role_id_2],
      'user_accounts[email_hostname]' => 'sample.com',
    ];
    $this->drupalPostForm('/admin/config/people/cas', $edit, 'Save configuration');

    $this->assertEquals([$role_id, $role_id_2], $this->config('cas.settings')->get('user_accounts.auto_assigned_roles'));

    $cas_property_bag = new CasPropertyBag('test_cas_user_name');
    \Drupal::service('cas.user_manager')->login($cas_property_bag, 'fake_ticket_string');
    $user = user_load_by_name('test_cas_user_name');
    $this->assertTrue($user->hasRole($role_id), 'The user has the auto assigned role: ' . $role_id);
    $this->assertTrue($user->hasRole($role_id_2), 'The user has the auto assigned role: ' . $role_id_2);

    Role::load($role_id_2)->delete();

    $this->assertEquals([$role_id], $this->config('cas.settings')->get('user_accounts.auto_assigned_roles'));
  }

  /**
   * Tests that access to the password reset form is disabled.
   *
   * @dataProvider restrictedPasswordEnabledProvider
   */
  public function testPasswordResetBehavior($restricted_password_enabled) {
    $edit = [
      'user_accounts[restrict_password_management]' => $restricted_password_enabled,
      'user_accounts[email_hostname]' => 'sample.com',
    ];
    $this->drupalPostForm('/admin/config/people/cas', $edit, 'Save configuration');

    // The menu router info needs to be rebuilt after saving this form so the
    // CAS menu alter runs again.
    $this->container->get('router.builder')->rebuild();

    $this->drupalLogout();
    $this->drupalGet('user/password');
    if ($restricted_password_enabled) {
      $this->assertSession()->pageTextContains(t('Access denied'));
      $this->assertSession()->pageTextNotContains(t('Reset your password'));
    }
    else {
      $this->assertSession()->pageTextNotContains(t('Access denied'));
      $this->assertSession()->pageTextContains(t('Reset your password'));
    }
  }

  /**
   * Data provider for testPasswordResetBehavior.
   */
  public function restrictedPasswordEnabledProvider() {
    return [[FALSE], [TRUE]];
  }

}
