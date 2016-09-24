<?php

namespace Drupal\Tests\cas\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the CAS forced login controller.
 *
 * @group cas
 */
class CasLoginFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['cas'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  public function testLoginLinkOnLoginForm() {
    $config = $this->config('cas.settings');
    $config
      ->set('server.version', '1.0')
      ->set('server.hostname', 'fakecasserver.localhost')
      ->set('server.port', 443)
      ->set('server.path', '/authenticate')
      ->set('server.verify', 0);

    $config
      ->set('login_link_enabled', TRUE)
      ->set('login_link_label', 'Click to login via CAS');

    $config->save();

    $this->drupalGet('/user/login');
    $this->assertSession()->linkExists('Click to login via CAS');

    $config
      ->set('login_link_enabled', FALSE);
    $config->save();

    $this->drupalGet('/user/login');
    $this->assertSession()->linkNotExists('Click to login via CAS');
  }
}
