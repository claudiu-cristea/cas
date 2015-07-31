<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Service\CasProxyHelperTest.
 */

namespace Drupal\Tests\cas\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\cas\Service\CasProxyHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;

/**
 * CasHelper unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Service\CasProxyHelper
 */
class CasProxyHelperTest extends UnitTestCase {

  /**
   * The mocked http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The mocked CasHelper.
   *
   * @var \Drupal\cas\Service\CasHelper|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casHelper;

  /**
   * The casProxyHelper to test.
   *
   * @var \Drupal\cas\Service\CasProxyHelper|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casProxyHelper;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp() {
    parent::setUp();

    $this->httpClient = new Client();
    $this->casHelper = $this->getMockBuilder('\Drupal\cas\Service\CasHelper')
                            ->disableOriginalConstructor()
                            ->getMock();
    $this->casProxyHelper = new CasProxyHelper($this->httpClient, $this->casHelper);

  }

  /**
   * Test proxy authentication to a service.
   *
   * @covers ::proxyAuthenticate
   * @covers ::getServerProxyURL
   * @covers ::parseProxyTicket
   *
   * @dataProvider proxyAuthenticateDataProvider
   */
  public function testProxyAuthenticate($target_service, $cookie_domain, $already_proxied) {
    // Set up the fake pgt in the session.
    $_SESSION['cas_pgt'] = $this->randomMachineName(24);

    // Set up properties so the http client callback knows about them.
    $cookie_value = $this->randomMachineName(24);

    if ($already_proxied) {
      // Set up the fake session data.
      $_SESSION['cas_proxy_helper'][$target_service][] = array(
        'Name' => 'SESSION',
        'Value' => $cookie_value,
        'Domain' => $cookie_domain,
      );
      $jar = $this->casProxyHelper->proxyAuthenticate($target_service);
      $cookie_array = $jar->toArray();
      $this->assertEquals('SESSION', $cookie_array[0]['Name']);
      $this->assertEquals($cookie_value, $cookie_array[0]['Value']);
      $this->assertEquals($cookie_domain, $cookie_array[0]['Domain']);
    }
    else {
      // The casHelper expects to be called for a few things.
      $this->casHelper->expects($this->once())
                      ->method('getServerBaseUrl')
                      ->will($this->returnValue('https://example.com/cas/'));
      $this->casHelper->expects($this->once())
                      ->method('isProxy')
                      ->will($this->returnValue(TRUE));
      $proxy_ticket = $this->randomMachineName(24);
      $xml_response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
           <cas:proxySuccess>
             <cas:proxyTicket>PT-$proxy_ticket</cas:proxyTicket>
            </cas:proxySuccess>
         </cas:serviceResponse>";
      $stream = Stream::factory($xml_response);
      $text = "HTTP/1.0 200 OK
      Content-type: text/html
      Set-Cookie: SESSION=" . $cookie_value;
      $mock = new Mock([new Response(200, array(), $stream), $text]);
      $this->httpClient->getEmitter()->attach($mock);
      $jar = $this->casProxyHelper->proxyAuthenticate($target_service);
      $this->assertEquals('SESSION', $_SESSION['cas_proxy_helper'][$target_service][0]['Name']);
      $this->assertEquals($cookie_value, $_SESSION['cas_proxy_helper'][$target_service][0]['Value']);
      $this->assertEquals($cookie_domain, $_SESSION['cas_proxy_helper'][$target_service][0]['Domain']);
      $cookie_array = $jar->toArray();
      $this->assertEquals('SESSION', $cookie_array[0]['Name']);
      $this->assertEquals($cookie_value, $cookie_array[0]['Value']);
      $this->assertEquals($cookie_domain, $cookie_array[0]['Domain']);
    }
  }

  /**
   * Provides parameters and return value for testProxyAuthenticate.
   *
   * @return array
   *   Parameters and return values.
   *
   * @see \Drupal\Tests\cas\Unit\Service\CasProxyHelperTest::testProxyAuthenticate
   */
  public function proxyAuthenticateDataProvider() {
    /* There are two scenarios that return successfully that we test here.
     * First, proxying a new service that was not previously proxied. Second,
     * a second request for a service that has already been proxied.
     */
    return array(
      array('https://example.com', 'example.com', FALSE),
      array('https://example.com', 'example.com', TRUE),
    );
  }

  /**
   * Test the possible exceptions from proxy authentication.
   *
   * @covers ::proxyAuthenticate
   * @covers ::getServerProxyURL
   * @covers ::parseProxyTicket
   *
   * @dataProvider proxyAuthenticateExceptionDataProvider
   */
  public function testProxyAuthenticateException($is_proxy, $pgt_set, $target_service, $response, $client_exception, $exception_type, $exception_message) {
    if ($pgt_set) {
      // Set up the fake pgt in the session.
      $_SESSION['cas_pgt'] = $this->randomMachineName(24);
    }
    // Set up properties so the http client callback knows about them.
    $cookie_value = $this->randomMachineName(24);

    $this->casHelper->expects($this->any())
                    ->method('getServerBaseUrl')
                    ->will($this->returnValue('https://example.com/cas/'));
    $this->casHelper->expects($this->any())
                    ->method('isProxy')
                    ->will($this->returnValue($is_proxy));

    $stream = Stream::factory($response);
    if ($client_exception == 'server') {
      $code = 404;
    }
    else {
      $code = 200;
    }
    if ($client_exception == 'client') {
      $text = "HTTP/1.0 404 Not Found";
    }
    else {
      $text = "HTTP/1.0 200 OK
        Content-type: text/html
        Set-Cookie: SESSION=" . $cookie_value;
    }
    $mock = new Mock([new Response($code, array(), $stream), $text]);
    $this->httpClient->getEmitter()->attach($mock);
    $this->setExpectedException($exception_type, $exception_message);
    $jar = $this->casProxyHelper->proxyAuthenticate($target_service);

  }

  /**
   * Provides parameters and exceptions for testProxyAuthenticateException.
   *
   * @return array
   *   Parameters and exceptions.
   *
   * @see \Drupal\Tests\cas\Unit\Service\CasProxyHelperTest::testProxyAuthenticateException
   */
  public function proxyAuthenticateExceptionDataProvider() {
    $target_service = 'https://example.com';
    $exception_type = '\Drupal\cas\Exception\CasProxyException';
    // Exception case 1: not configured as proxy.
    $params[] = array(FALSE, TRUE, $target_service, '', FALSE, $exception_type,
      'Session state not sufficient for proxying.');

    // Exception case 2: session pgt not set.
    $params[] = array(TRUE, FALSE, $target_service, '',  FALSE, $exception_type,
      'Session state not sufficient for proxying.');

    // Exception case 3: http client exception from proxy app.
    $proxy_ticket = $this->randomMachineName(24);
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:proxySuccess>
          <cas:proxyTicket>PT-$proxy_ticket</cas:proxyTicket>
        </cas:proxySuccess>
      </cas:serviceResponse>";

    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      'client',
      $exception_type,
      '',
    );

    // Exception case 4: http client exception from CAS Server.
    $proxy_ticket = $this->randomMachineName(24);
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:proxySuccess>
          <cas:proxyTicket>PT-$proxy_ticket</cas:proxyTicket>
        </cas:proxySuccess>
      </cas:serviceResponse>";

    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      'server',
      $exception_type,
      '',
    );

    // Exception case 5: non-XML response from CAS server.
    $response = "<> </> </ <..";
    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      FALSE,
      $exception_type,
      'CAS Server returned non-XML response.',
    );

    // Exception case 6: CAS Server rejected ticket.
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:proxyFailure code=\"INVALID_REQUEST\">
           'pgt' and 'targetService' parameters are both required
         </cas:proxyFailure>
       </cas:serviceResponse>";
    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      FALSE,
      $exception_type,
      'CAS Server rejected proxy request.',
    );

    // Exception case 7: Neither proxyFailure nor proxySuccess specified.
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:proxy code=\"INVALID_REQUEST\">
         </cas:proxy>
       </cas:serviceResponse>";
    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      FALSE,
      $exception_type,
      'CAS Server returned malformed response.',
    );

    // Exception case 8: Malformed ticket.
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:proxySuccess>
        </cas:proxySuccess>
       </cas:serviceResponse>";
    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      FALSE,
      $exception_type,
      'CAS Server provided invalid or malformed ticket.',
    );

    return $params;
  }

}
