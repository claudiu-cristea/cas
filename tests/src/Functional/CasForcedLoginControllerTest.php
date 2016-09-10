<?php

namespace Drupal\Tests\cas\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Component\Utility\UrlHelper;

/**
 * Tests the CAS forced login controller.
 *
 * @group cas
 */
class CasForceLoginControllerTest extends BrowserTestBase {

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

  private function disableRedirects()
  {
    $this->getSession()->getDriver()->getClient()->followRedirects(FALSE);
  }

  /**
   * Tests the the forced login route that redirects users authenticate.
   *
   * Our data provider may provide us with array of query string parameters
   * that are present on the forced login route. These parameters should
   * become part of the service URL that is passed to the CAS server on the
   * redirect.
   *
   * @dataProvider forceLoginRouteQueryStringProvider
   */
  public function testForceLoginRoute($queryParams) {
    $config = $this->config('cas.settings');
    $config
      ->set('server.version', '1.0')
      ->set('server.hostname', 'fakecasserver.localhost')
      ->set('server.port', 443)
      ->set('server.path', '/authenticate')
      ->set('server.verify', 0);
    $config->save();

    $forcedLoginPath = '/cas';
    $expectedServiceUrl = $this->baseUrl . '/casservice';
    if (!empty($queryParams)) {
      $encodedQueryParams = UrlHelper::buildQuery($queryParams);
      $forcedLoginPath .= '?' . $encodedQueryParams;
      $expectedServiceUrl .= '?' . $encodedQueryParams;
    }

    $this->disableRedirects();
    $this->prepareRequest();

    $session = $this->getSession();
    $session->visit($forcedLoginPath);

    $expectedRedirectLocation = 'https://fakecasserver.localhost/authenticate/login?' . UrlHelper::buildQuery(['service' => $expectedServiceUrl]);

    $this->assertEquals(302, $session->getStatusCode());
    $this->assertEquals($expectedRedirectLocation, $session->getResponseHeader('Location'));
  }

  /**
   * Data provider for testForceLoginRoute.
   */
  public function forceLoginRouteQueryStringProvider()
  {
    // Provide a varying set of query string paramaters which will be applied
    // to the CAS login path during the test.
    return [
      [[]],
      [['returnto' => 'node/1']],
      [['buzz' => 'bazz', 'foo' => 'bar']]
    ];
  }
}
