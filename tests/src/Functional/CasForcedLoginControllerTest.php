<?php

namespace Drupal\Tests\cas\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Component\Utility\UrlHelper;

/**
 * Tests the CAS forced login controller.
 *
 * @group cas
 */
class CasForcedLoginControllerTest extends CasBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['cas'];

  /**
   * Tests the the forced login route that redirects users authenticate.
   *
   * @dataProvider queryStringDataProvider
   */
  public function testForcedLoginRoute(array $params = []) {
    $admin = $this->drupalCreateUser(['administer account settings']);
    $this->drupalLogin($admin);

    $edit = [
      'server[hostname]' => 'fakecasserver.localhost',
      'server[path]' => '/auth',
    ];
    $this->drupalPostForm('/admin/config/people/cas', $edit, 'Save configuration');

    $this->drupalLogout();

    $this->disableRedirects();
    $this->prepareRequest();
    $session = $this->getSession();

    // We want to test that query string parameters that are present on the
    // request to the forced login route are passed along to the service
    // URL as well, so test each of these cases individually.
    $path = $this->buildUrl('cas', ['query' => $params, 'absolute' => true]);

    $session->visit($path);

    $this->assertEquals(302, $session->getStatusCode());
    $expected_redirect_location = 'https://fakecasserver.localhost/auth/login?' . UrlHelper::buildQuery(['service' => $this->buildServiceUrlWithParams($params)]);
    $this->assertEquals($expected_redirect_location, $session->getResponseHeader('Location'));
  }

  /**
   * Data provider for testForcedLoginRoute.
   *
   * Provides various different query strings to the forced login route.
   */
  public function queryStringDataProvider() {
    return [
      [[]],
      [['returnto' => 'node/1']],
      [['foo' => 'bar', 'buzz' => 'baz']],
    ];
  }
}
