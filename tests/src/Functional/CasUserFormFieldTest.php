<?php

namespace Drupal\Tests\cas\Functional;

/**
 * Tests the behavior of the CAS username form field on the user form.
 *
 * @group cas
 */
class CasUserFormFieldTest extends CasBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['cas'];

  /**
   * Tests the the forced login route that redirects users authenticate.
   */
  public function testUserForm() {
    // First test that a normal user has no access to edit their CAS username.
    $test_user_1 = $this->drupalCreateUser([], 'test_user_1');
    $this->drupalLogin($test_user_1);

    $this->drupalGet('/user/' . $test_user_1->id() . '/edit');

    $page = $this->getSession()->getPage();
    $this->assertNull($page->findField('cas_username'), 'CAS username field was found on page when user should not have access.');

    $this->drupalLogout();
    $admin_user = $this->drupalCreateUser(['administer users'], 'test_admin');
    $this->drupalLogin($admin_user);

    $this->drupalGet('/user/' . $test_user_1->id() . '/edit');

    $cas_username_field = $this->getSession()->getPage()->findField('cas_username');
    $this->assertNotNull($cas_username_field, 'CAS username field should exist on user form.');

    // Set the CAS username for this user.
    $edit = [
      'cas_username' => 'test_user_1_cas',
    ];
    $this->drupalPostForm('/user/' . $test_user_1->id() . '/edit', $edit, 'Save');

    // Check that field is still filled in with the CAS username.
    $cas_username_field = $this->getSession()->getPage()->findField('cas_username');
    $this->assertNotNull($cas_username_field, 'CAS username field should exist on user form.');
    $this->assertEquals('test_user_1_cas', $cas_username_field->getValue());

    // Verify data was stored in authmap properly as well.
    $authmap = $this->container->get('externalauth.authmap');
    $this->assertEquals('test_user_1_cas', $authmap->get($test_user_1->id(), 'cas'));

    // Register a new user, attempting to use the same CAS username.
    $new_user_data = [
      'mail' => 'test_user_2@sample.com',
      'name' => 'test_user_2',
      'pass[pass1]' => 'test_user_2',
      'pass[pass2]' => 'test_user_2',
      'cas_username' => 'test_user_1_cas',
    ];
    $this->drupalPostForm('/admin/people/create', $new_user_data, 'Create new account');
    $output = $this->getSession()->getPage()->getContent();

    $validation_error_message = 'The specified CAS username is already in use by another user.';
    $this->assertContains($validation_error_message, $output, 'Expected validation error not found on page.');

    // Submit with proper CAS username, and verify user was created and has the
    // proper CAS username associated.
    $new_user_data['cas_username'] = 'test_user_2_cas';
    $this->drupalPostForm('/admin/people/create', $new_user_data, 'Create new account');
    $output = $this->getSession()->getPage()->getContent();
    $this->assertNotContains($validation_error_message, $output, 'Validation error should not be found.');

    $test_user_2 = $this->container->get('entity_type.manager')->getStorage('user')->loadByProperties(['name' => 'test_user_2']);
    $test_user_2 = reset($test_user_2);
    $this->assertNotNull($test_user_2);
    $authmap = $this->container->get('externalauth.authmap');
    $this->assertEquals($test_user_2->id(), $authmap->getUid('test_user_2_cas', 'cas'));

    // Should be able to clear out the CAS username to remove the authmap entry.
    $edit = ['cas_username' => ''];
    $this->drupalPostForm('/user/' . $test_user_2->id() . '/edit', $edit, 'Save');
    $authmap = $this->container->get('externalauth.authmap');
    $this->assertFalse($authmap->get($test_user_2->id(), 'cas'));
    // Visit the edit page for this user to ensure CAS username field empty.
    $this->drupalGet('/user/' . $test_user_2->id() . '/edit');
    $this->assertEmpty($this->getSession()->getPage()->findField('cas_username')->getValue());
  }

}
