<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Service\CasHelperTest
 */

 namespace Drupal\Tests\cas\Unit\Service;

 use Drupal\Tests\UnitTestCase;
 use Drupal\cas\Service\CasHelper;
 use Drupal\Core\Routing\UrlGeneratorInterface;
 use Drupal\Component\Utility\UrlHelper;
 use Drupal\Core\Database\Connection;
 use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * CasHelper unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Service\CasHelper
 */
class CasHelperTest extends UnitTestCase {

  /**
   * The mocked database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $connection;

  /**
   * The mocked Url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct()
   */
  protected function setUp() {
    parent::setUp();


    $this->urlGenerator = $this->getMock('\Drupal\Core\Routing\UrlGeneratorInterface');
    $this->connection = $this->getMockBuilder('\Drupal\Core\Database\Connection')
                             ->disableOriginalConstructor()
                             ->getMock();
  }

  /**
   * Test constructing the login URL.
   *
   * @covers ::getServerLoginUrl()
   *
   * @dataProvider getServerLoginUrlDataProvider
   */
  public function testGetServerLoginUrl($service_params, $gateway, $result) {

    $config_factory = $this->getConfigFactoryStub(array('cas.settings' => array(
      'server.hostname' => 'example.com',
      'server.port' => 443,
      'server.path' => '/cas',
    )));
    $cas_helper = new CasHelper($config_factory, $this->urlGenerator, $this->connection);

    if (!empty($service_params)) {
      $params = '';
      foreach ($service_params as $key => $value) {
        $params .= '&' . $key . '=' . urlencode($value);
      }
      $params = '?' . substr($params, 1);
      $return_value = 'https://example.com/client' . $params;
    }
    else {
      $return_value = 'https://example.com/client';
    }
    $this->urlGenerator->expects($this->once())
      ->method('generate')
      ->will($this->returnValue($return_value));
    $login_url = $cas_helper->getServerLoginUrl($service_params, $gateway);
    $this->assertEquals($result, $login_url);
  }

  /**
   * Provides parameters and expected return values for testGetServerLoginUrl.
   *
   * @return array
   *   The list of parameters and return values.
   *
   * @see \Drupal\Tests\cas\Unit\CasHelperTest::testGetServerLoginUrl()
   */
  public function getServerLoginUrlDataProvider() {
    return array(
      array(array(), FALSE, 'https://example.com:443/cas/login?service=https%3A//example.com/client'),
      array(array('returnto' => 'node/1'), FALSE, 'https://example.com:443/cas/login?service=https%3A//example.com/client%3Freturnto%3Dnode%252F1'),
      array(array(), TRUE, 'https://example.com:443/cas/login?gateway=1&service=https%3A//example.com/client'),
      array(array('returnto' => 'node/1'), TRUE, 'https://example.com:443/cas/login?gateway=1&service=https%3A//example.com/client%3Freturnto%3Dnode%252F1'),
    );
  }

  /**
   * Test constructing the CAS Server base url.
   *
   * @covers ::getServerBaseUrl()
   */
  public function testGetServerBaseUrl() {
    $config_factory = $this->getConfigFactoryStub(array('cas.settings' => array(
      'server.hostname' => 'example.com',
      'server.port' => 443,
      'server.path' => '/cas',
    )));
    $cas_helper = new CasHelper($config_factory, $this->urlGenerator, $this->connection);


    $this->assertEquals('https://example.com:443/cas/', $cas_helper->getServerBaseUrl());
  }

  /**
   * Test constructing the CAS Server validation url.
   *
   * @covers ::getServerValidateUrl()
   *
   * @dataProvider getServerValidateUrlDataProvider
   */
  public function testGetServerValidateUrl($ticket, $service_params, $return, $is_proxy, $can_be_proxied, $protocol) {
    $config_factory = $this->getConfigFactoryStub(array('cas.settings' => array(
      'server.hostname' => 'example.com',
      'server.port' => 443,
      'server.path' => '/cas',
      'server.version' => $protocol,
      'proxy.initialize' => $is_proxy,
      'proxy.can_be_proxied' => $can_be_proxied,
    )));
    if (!empty($service_params)) {
      $params = '';
      foreach ($service_params as $key => $value) {
        $params .= '&' . $key . '=' . urlencode($value);
      }
      $params = '?' . substr($params, 1);
      $return_value = 'https://example.com/client' . $params;
    }
    else {
      $return_value = 'https://example.com/client';
    }

    $this->urlGenerator->expects($this->once())
      ->method('generate')
      ->will($this->returnValue($return_value));
    $this->urlGenerator->expects($this->any())
      ->method('generateFromRoute')
      ->will($this->returnValue('https://example.com/casproxycallback'));
    $cas_helper = new CasHelper($config_factory, $this->urlGenerator, $this->connection);
    $this->assertEquals($return, $cas_helper->getServerValidateUrl($ticket, $service_params));

  }

  /**
   * Provides parameters and return values for testGetServerValidateUrl.
   *
   * @return array
   *   The list of parameters and return values.
   *
   * @see \Drupal\Tests\cas\Unit\CasHelperTest::testGetServerValidateUrl()
   */
  public function getServerValidateUrlDataProvider() {
    /*
     * There are ten possible permutations here: protocol version 1.0 does not
     * support proxying, so we check with and without additional parameters in
     * the service URL. Protocol 2.0 supports proxying, so there are 2^3 = 8
     * permutations to check here: with and without additional parameters,
     * whether or not to initialize as a proxy, and whether or not the client
     * can be proxied.
     */
    for ($i = 0; $i < 10; $i++) {
      $ticket[$i] = $this->randomMachineName(24);
    }
    return array(
      array($ticket[0], array(), 'https://example.com:443/cas/validate?service=https%3A//example.com/client&ticket=' . $ticket[0], FALSE, FALSE, '1.0'),

      array($ticket[1], array('returnto' => 'node/1'), 'https://example.com:443/cas/validate?service=https%3A//example.com/client%3Freturnto%3Dnode%252F1&ticket=' . $ticket[1], FALSE, FALSE, '1.0'),

      array($ticket[2], array(), 'https://example.com:443/cas/serviceValidate?service=https%3A//example.com/client&ticket=' . $ticket[2], FALSE, FALSE, '2.0'),

      array($ticket[3], array('returnto' => 'node/1'), 'https://example.com:443/cas/serviceValidate?service=https%3A//example.com/client%3Freturnto%3Dnode%252F1&ticket=' . $ticket[3], FALSE, FALSE, '2.0'),

      array($ticket[4], array(), 'https://example.com:443/cas/proxyValidate?service=https%3A//example.com/client&ticket=' . $ticket[4], FALSE, TRUE, '2.0'),

      array($ticket[5], array('returnto' => 'node/1'), 'https://example.com:443/cas/proxyValidate?service=https%3A//example.com/client%3Freturnto%3Dnode%252F1&ticket=' . $ticket[5], FALSE, TRUE, '2.0'),

      array($ticket[6], array(), 'https://example.com:443/cas/serviceValidate?service=https%3A//example.com/client&ticket=' . $ticket[6] . '&pgtUrl=https%3A//example.com/casproxycallback', TRUE, FALSE, '2.0'),

      array($ticket[7], array('returnto' => 'node/1'), 'https://example.com:443/cas/serviceValidate?service=https%3A//example.com/client%3Freturnto%3Dnode%252F1&ticket=' . $ticket[7] . '&pgtUrl=https%3A//example.com/casproxycallback', TRUE, FALSE, '2.0'),

      array($ticket[8], array(), 'https://example.com:443/cas/proxyValidate?service=https%3A//example.com/client&ticket=' . $ticket[8] . '&pgtUrl=https%3A//example.com/casproxycallback', TRUE, TRUE, '2.0'),

      array($ticket[9], array('returnto' => 'node/1'), 'https://example.com:443/cas/proxyValidate?service=https%3A//example.com/client%3Freturnto%3Dnode%252F1&ticket=' . $ticket[9] . '&pgtUrl=https%3A//example.com/casproxycallback', TRUE, TRUE, '2.0'),
    );
  }

  /**
   * Test setting the PGT in the session.
   *
   * @covers ::storePGTSession()
   *
   * @dataProvider storePGTSessionDataProvider
   */
  public function testStorePGTSession($pgt_iou, $pgt) {
    $map = array(array($pgt_iou, $pgt));
    $cas_helper = $this->getMockBuilder('Drupal\cas\Service\CasHelper')
      ->disableOriginalConstructor()
      ->setMethods(array('lookupPgtByPgtIou', 'deletePgtMappingByPgtIou'))
      ->getMock();
    $cas_helper->expects($this->once())
      ->method('lookupPgtByPgtIou')
      ->will($this->returnValueMap($map));

    $cas_helper->storePGTSession($pgt_iou);
    $this->assertEquals($pgt, $_SESSION['cas_pgt']);
  }

  /**
   * Provides parameters and return values for testStorePGTSession
   *
   * @return array
   *   Parameters and return values.
   *
   * @see \Drupal\Tests\cas\Unit\Service\CasHelper::testStorePGTSession()
   */
  public function storePGTSessionDataProvider() {
    return array(
      array($this->randomMachineName(24), $this->randomMachineName(48)),
    );
  }

}
