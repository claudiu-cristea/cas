<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Service\CasValidatorTest.
 */

namespace Drupal\Tests\cas\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\cas\Service\CasValidator;
use GuzzleHttp\Exception\ClientException;
use Drupal\cas\CasPropertyBag;

/**
 * CasHelper unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Service\CasValidator
 */
class CasValidatorTest extends UnitTestCase {

  /**
   * The mocked http client.
   *
   * @var \Drupal\Core\Http\Client|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $httpClient;

  /**
   * The mocked CasHelper.
   *
   * @var \Drupal\cas\Service\CasHelper|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casHelper;

  /**
   * The CasValidator to test.
   *
   * @var \Drupal\cas\Service\CasValidator
   */
  protected $casValidator;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp() {
    parent::setUp();

    $this->httpClient = $this->getMockBuilder('\Drupal\Core\Http\Client')
                             ->disableOriginalConstructor()
                             ->getMock();
    $this->casHelper = $this->getMockBuilder('\Drupal\cas\Service\CasHelper')
                            ->disableOriginalConstructor()
                            ->getMock();
    $this->casValidator = new CasValidator($this->httpClient, $this->casHelper);

  }

  /**
   * Test validation of Cas tickets.
   *
   * @covers ::validateTicket
   * @covers ::validateVersion1
   * @covers ::validateVersion2
   * @covers ::verifyProxyChain
   * @covers ::parseAllowedProxyChains
   * @covers ::parseServerProxyChain
   *
   * @dataProvider validateTicketDataProvider
   */
  public function testValidateTicket($version, $ticket, $username, $response, $is_proxy, $can_be_proxied, $proxy_chains) {
    $response_object = $this->getMock('\GuzzleHttp\Message\ResponseInterface');
    $body_object = $this->getMock('\GuzzleHttp\Stream\StreamInterface');
    $this->httpClient->expects($this->once())
                     ->method('get')
                     ->will($this->returnValue($response_object));

    $response_object->expects($this->once())
                    ->method('getBody')
                    ->will($this->returnValue($body_object));

    $body_object->expects($this->once())
                ->method('__toString')
                ->will($this->returnValue($response));

    $this->casHelper->expects($this->once())
                    ->method('getCertificateAuthorityPem')
                    ->will($this->returnValue('foo'));

    $this->casHelper->expects($this->any())
                    ->method('isProxy')
                    ->will($this->returnValue($is_proxy));

    $this->casHelper->expects($this->any())
                    ->method('canBeProxied')
                    ->will($this->returnValue($can_be_proxied));

    $this->casHelper->expects($this->any())
                    ->method('getProxyChains')
                    ->will($this->returnValue($proxy_chains));

    $property_bag = $this->casValidator->validateTicket($version, $ticket, array());
    $this->assertEquals($username, $property_bag->getUsername());
  }

  /**
   * Provides parameters and return values for testValidateTicket.
   *
   * @return array
   *   Parameters and return values.
   *
   * @see \Drupal\Tests\cas\Service\CasValidatorTest::testValidateTicket
   */
  public function validateTicketDataProvider() {
    // First test case: protocol version 1.
    $user1 = $this->randomMachineName(8);
    $response1 = "yes\n$user1\n";
    $params[] = array(
      '1.0',
      $this->randomMachineName(24),
      $user1,
      $response1,
      FALSE,
      FALSE,
      '',
    );

    // Second test case: protocol version 2, no proxies.
    $user2 = $this->randomMachineName(8);
    $response2 = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:authenticationSuccess>
          <cas:user>$user2</cas:user>
        </cas:authenticationSuccess>
       </cas:serviceResponse>";
    $params[] = array(
      '2.0',
      $this->randomMachineName(24),
      $user2,
      $response2,
      FALSE,
      FALSE,
      '',
    );

    // Third test case: protocol version 2, initialize as proxy.
    $user3 = $this->randomMachineName(8);
    $pgt_iou3 = $this->randomMachineName(24);
    $response3 = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:authenticationSuccess>
           <cas:user>$user3</cas:user>
             <cas:proxyGrantingTicket>PGTIOU-$pgt_iou3
           </cas:proxyGrantingTicket>
         </cas:authenticationSuccess>
       </cas:serviceResponse>";
    $params[] = array(
      '2.0',
      $this->randomMachineName(24),
      $user3,
      $response3,
      TRUE,
      FALSE,
      '',
    );

    // Fourth test case: protocol version 2, can be proxied.
    $user4 = $this->randomMachineName(8);
    $proxy_chains = '/https:\/\/example\.com/ /https:\/\/foo\.com/' . PHP_EOL . '/https:\/\/bar\.com/';
    $response4 = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:authenticationSuccess>
           <cas:user>$user4</cas:user>
             <cas:proxies>
               <cas:proxy>https://example.com</cas:proxy>
               <cas:proxy>https://foo.com</cas:proxy>
             </cas:proxies>
         </cas:authenticationSuccess>
       </cas:serviceResponse>";
    $params[] = array(
      '2.0',
      $this->randomMachineName(24),
      $user4,
      $response4,
      FALSE,
      TRUE,
      $proxy_chains,
    );

    // Fifth test case: protocol version 2, proxy in both directions.
    $user5 = $this->randomMachineName(8);
    $pgt_iou5 = $this->randomMachineName(24);
    // Use the same proxy chains as the fourth test case.
    $response5 = "<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
        <cas:authenticationSuccess>
          <cas:user>$user5</cas:user>
          <cas:proxyGrantingTicket>PGTIOU-$pgt_iou5</cas:proxyGrantingTicket>
          <cas:proxies>
            <cas:proxy>https://https://bar.com</cas:proxy>
          </cas:proxies>
         </cas:authenticationSuccess>
      </cas:serviceResponse>";
    $params[] = array(
      '2.0',
      $this->randomMachineName(24),
      $user5,
      $response5,
      TRUE,
      TRUE,
      $proxy_chains,
    );

    return $params;
  }

  /**
   * Test validation failure conditions for the correct exceptions.
   *
   * @covers ::validateTicket
   * @covers ::validateVersion1
   * @covers ::validateVersion2
   * @covers ::verifyProxyChain
   * @covers ::parseAllowedProxyChains
   * @covers ::parseServerProxyChain
   *
   * @dataProvider validateTicketExceptionDataProvider
   */
  public function testValidateTicketException($version, $response, $is_proxy, $can_be_proxied, $proxy_chains, $exception, $exception_message, $http_client_exception) {
    $response_object = $this->getMock('\GuzzleHttp\Message\ResponseInterface');
    $body_object = $this->getMock('\GuzzleHttp\Stream\StreamInterface');

    if ($http_client_exception) {
      $request = $this->getMock('\GuzzleHttp\Message\RequestInterface');
      $this->httpClient->expects($this->once())
                       ->method('get')
                       ->will($this->throwException(new ClientException('', $request)));
    }
    else {
      $this->httpClient->expects($this->once())
                       ->method('get')
                       ->will($this->returnValue($response_object));
    }

    $response_object->expects($this->any())
                    ->method('getBody')
                    ->will($this->returnValue($body_object));

    $body_object->expects($this->any())
                ->method('__toString')
                ->will($this->returnValue($response));

    $this->casHelper->expects($this->any())
                    ->method('isProxy')
                    ->will($this->returnValue($is_proxy));

    $this->casHelper->expects($this->any())
                    ->method('canBeProxied')
                    ->will($this->returnValue($can_be_proxied));

    $this->casHelper->expects($this->any())
                    ->method('getProxyChains')
                    ->will($this->returnValue($proxy_chains));

    if (!empty($exception_message)) {
      $this->setExpectedException($exception, $exception_message);
    }
    else {
      $this->setExpectedException($exception);
    }
    $ticket = $this->randomMachineName(24);
    $user = $this->casValidator->validateTicket($version, $ticket, array());
  }

  /**
   * Provides parameters and return values for testValidateTicketException.
   *
   * @return array
   *   Parameters and return values.
   *
   * @see \Drupal\Tests\cas\Service\CasValidatorTest::testValidateTicketException
   */
  public function validateTicketExceptionDataProvider() {
    /* There are nine different exception messages that can occur. We test for
     * each one. Currently, they are all of type 'CasValidateException', so we
     * set that up front. If that changes in the future, we can rework this bit
     * without changing the function signature.
     */
    $exception_type = '\Drupal\cas\Exception\CasValidateException';

    /* The first exception is actually a 'recasting' of an http client
     * exception. We're not in the business of checking their exception text,
     * so simply tell the client to throw an exception, and don't worry about
     * the message given.
     */
    $params[] = array('2.0', '', FALSE, FALSE, '', $exception_type, '', TRUE);

    /* Protocol version 1 can throw two exceptions: 'no' text is found, or
     * 'yes' text is not found (in that order).
     */
    $params[] = array(
      '1.0',
      "no\n\n",
      FALSE,
      FALSE,
      '',
      $exception_type,
      'Ticket did not pass validation.',
      FALSE,
    );
    $params[] = array(
      '1.0',
      "Foo\nBar?\n",
      FALSE,
      FALSE,
      '',
      $exception_type,
      'Malformed response from CAS server.',
      FALSE,
    );

    // Protocol version 2: Malformed XML.
    $params[] = array(
      '2.0',
      "<> </ </> <<",
      FALSE,
      FALSE,
      '',
      $exception_type,
      'XML from CAS server is not valid.',
      FALSE,
    );

    // Protocol version 2: Authentication failure.
    $ticket = $this->randomMachineName(24);
    $params[] = array(
      '2.0',
      "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:authenticationFailure code=\"INVALID_TICKET\">
           Ticket $ticket not recognized
         </cas:authenticationFailure>
       </cas:serviceResponse>",
      FALSE,
      FALSE,
      '',
      $exception_type,
      "Error Code INVALID_TICKET: Ticket $ticket not recognized",
      FALSE,
    );

    // Protocol version 2: Neither authentication failure nor authentication
    // succes found.
    $params[] = array(
      '2.0',
      "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:authentication>
           Username
         </cas:authentication>
       </cas:serviceResponse>",
      FALSE,
      FALSE,
      '',
      $exception_type,
      "XML from CAS server is not valid.",
      FALSE,
    );

    // Protocol version 2: No user specified in authenticationSuccess.
    $params[] = array(
      '2.0',
      "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:authenticationSuccess>
           Username
         </cas:authenticationSuccess>
       </cas:serviceResponse>",
      FALSE,
      FALSE,
      '',
      $exception_type,
      "No user found in ticket validation response.",
      FALSE,
    );

    // Protocol version 2: Proxy chain mismatch.
    $proxy_chains = '/https:\/\/example\.com/ /https:\/\/foo\.com/' . PHP_EOL . '/https:\/\/bar\.com/';
    $params[] = array(
      '2.0',
      "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:authenticationSuccess>
           <cas:user>username</cas:user>
             <cas:proxies>
               <cas:proxy>https://example.com</cas:proxy>
               <cas:proxy>https://bar.com</cas:proxy>
             </cas:proxies>
         </cas:authenticationSuccess>
       </cas:serviceResponse>",
      FALSE,
      TRUE,
      $proxy_chains,
      $exception_type,
      "Proxy chain did not match allowed list.",
      FALSE,
    );

    // Protocol version 2: Proxy chain mismatch with non-regex proxy chain.
    $proxy_chains = 'https://bar.com /https:\/\/foo\.com/' . PHP_EOL . '/https:\/\/bar\.com/';
    $params[] = array(
      '2.0',
      "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:authenticationSuccess>
           <cas:user>username</cas:user>
             <cas:proxies>
               <cas:proxy>https://example.com</cas:proxy>
               <cas:proxy>https://bar.com</cas:proxy>
             </cas:proxies>
         </cas:authenticationSuccess>
       </cas:serviceResponse>",
      FALSE,
      TRUE,
      $proxy_chains,
      $exception_type,
      "Proxy chain did not match allowed list.",
      FALSE,
    );

    // Protocol version 2: No PGTIOU provided when initialized as proxy.
    $params[] = array(
      '2.0',
      "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:authenticationSuccess>
          <cas:user>username</cas:user>
        </cas:authenticationSuccess>
       </cas:serviceResponse>",
      TRUE,
      FALSE,
      '',
      $exception_type,
      "Proxy initialized, but no PGTIOU provided in response.",
      FALSE,
    );

    // Unknown protocol version.
    $params[] = array(
      'foobarbaz',
      "<text>",
      FALSE,
      FALSE,
      '',
      $exception_type,
      "Unknown CAS protocol version specified.",
      FALSE,
    );

    return $params;
  }

  /**
   * Test parsing out CAS attributes from response.
   *
   * @covers ::validateVersion2
   * @covers ::parseAttributes
   */
  public function testParseAttributes() {
    $ticket = $this->randomMachineName(8);
    $version = '2.0';
    $service_params = array();
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:authenticationSuccess>
          <cas:user>username</cas:user>
          <cas:attributes>
            <cas:email>foo@example.com</cas:email>
            <cas:memberof>cn=foo,o=example</cas:memberof>
            <cas:memberof>cn=bar,o=example</cas:memberof>
          </cas:attributes>
        </cas:authenticationSuccess>
       </cas:serviceResponse>";
    $response_object = $this->getMock('\GuzzleHttp\Message\ResponseInterface');
    $body_object = $this->getMock('\GuzzleHttp\Stream\StreamInterface');
    $this->httpClient->expects($this->once())
                     ->method('get')
                     ->will($this->returnValue($response_object));

    $response_object->expects($this->once())
                    ->method('getBody')
                    ->will($this->returnValue($body_object));

    $body_object->expects($this->once())
                ->method('__toString')
                ->will($this->returnValue($response));
    $expected_bag = new CasPropertyBag('username');
    $expected_bag->setAttributes(array(
      'email' => array('foo@example.com'),
      'memberof' => array('cn=foo,o=example', 'cn=bar,o=example'),
    ));
    $actual_bag = $this->casValidator->validateTicket($version, $ticket, $service_params);
    $this->assertEquals($expected_bag, $actual_bag);
  }

}
