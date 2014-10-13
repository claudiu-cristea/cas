<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Controller\ServiceControllerTest.
 */

namespace Drupal\Tests\cas\Unit\Controller;

use Drupal\Tests\UnitTestCase;
use Drupal\cas\Controller\ServiceController;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\cas\Service\CasHelper;
use Drupal\cas\Service\CasLogin;
use Drupal\cas\Exception\CasValidateException;
use Drupal\cas\Exception\CasLoginException;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\cas\Service\CasValidator;
use Drupal\cas\Service\CasLogout;

/**
 * ServiceController unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Controller\ServiceController
 */
class ServiceControllerTest extends UnitTestCase {

  /**
   * The mocked CasHelper.
   *
   * @var \Drupal\cas\Service\CasHelper|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casHelper;

  /**
   * The mocked Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $requestStack;

  /**
   * The mocked CasValidator.
   *
   * @var \Drupal\cas\Service\CasValidator|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casValidator;

  /**
   * The mocked CasLogin.
   *
   * @var \Drupal\cas\Service\CasLogin|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casLogin;

  /**
   * The mocked CasLogout.
   *
   * @var \Drupal\cas\Service\CasLogout|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casLogout;

  /**
   * The mocked Url Generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->casHelper = $this->getMockBuilder('\Drupal\cas\Service\CasHelper')
      ->disableOriginalConstructor()
      ->getMock();
    $this->casValidator = $this->getMockBuilder('\Drupal\cas\Service\CasValidator')
      ->disableOriginalConstructor()
      ->getMock();
    $this->casLogin = $this->getMockBuilder('\Drupal\cas\Service\CasLogin')
      ->disableOriginalConstructor()
      ->getMock();
    $this->casLogout = $this->getMockBuilder('\Drupal\cas\Service\CasLogout')
      ->disableOriginalConstructor()
      ->getMock();
    $this->requestStack = $this->getMock('\Symfony\Component\HttpFoundation\RequestStack');
    $this->urlGenerator = $this->getMock('\Drupal\Core\Routing\UrlGeneratorInterface');
  }

  /**
   * Test the handling of CAS-related requests.
   *
   * @covers ::handle()
   *
   * @dataProvider handleDataProvider
   */
  public function testHandle($handler, $is_proxy, $return_to) {
    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $request_bag = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $query = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->query = $query;
    $request_object->request = $request_bag;
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));
    $service_controller = $this->getMockBuilder('\Drupal\cas\Controller\ServiceController')
      ->setConstructorArgs(array(
        $this->casHelper,
        $this->casValidator,
        $this->casLogin,
        $this->casLogout,
        $this->requestStack,
        $this->urlGenerator,
      ))
      ->setMethods(array('setMessage'))
      ->getMock();

    switch ($handler) {
      case 'single-log-out':
        $request_bag->expects($this->once())
          ->method('has')
          ->with($this->equalTo('logoutRequest'))
          ->will($this->returnValue(TRUE));
        $request_bag->expects($this->once())
          ->method('get')
          ->with($this->equalTo('logoutRequest'))
          ->will($this->returnValue('foo'));

        $this->casLogout->expects($this->once())
          ->method('handleSlo')
          ->with($this->equalTo('foo'));
        $expected_response = new Response('', 200);
        break;

      case 'no-ticket':
        if ($return_to) {
          $get_query_map = array(
            array('ticket', NULL, FALSE, FALSE),
            array('returnto', NULL, FALSE, 'bar'),
          );
          $query->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap($get_query_map));
          $query->expects($this->once())
            ->method('has')
            ->with($this->equalTo('returnto'))
            ->will($this->returnValue(TRUE));
          $query->expects($this->once())
            ->method('set')
            ->with($this->equalTo('destination'), $this->equalTo('bar'));
          $query->expects($this->once())
            ->method('remove')
            ->with($this->equalTo('returnto'));
          $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('<front>'))
            ->will($this->returnValue('https://example.com/bar'));
          $expected_response = new RedirectResponse('https://example.com/bar');
        }
        else {
          $query->expects($this->once())
            ->method('has')
            ->with($this->equalTo('returnto'))
            ->will($this->returnValue(FALSE));
          $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('<front>'))
            ->will($this->returnValue('https://example.com/front'));
          $expected_response = new RedirectResponse('https://example.com/front');
        }
        break;

      case 'login':
        $get_query_map = array(
          array('ticket', NULL, FALSE, TRUE),
          array('returnto', NULL, FALSE, 'bar'),
        );
        $query->expects($this->any())
          ->method('get')
          ->will($this->returnValueMap($get_query_map));
        $this->casHelper->expects($this->once())
          ->method('getCasProtocolVersion')
          ->will($this->returnValue('2.0'));
        $ticket = $this->randomMachineName(24);
        $query->expects($this->once())
          ->method('all')
          ->will($this->returnValue(array(
            'ticket' => $ticket,
          )));
        $pgt = $this->randomMachineName(24);
        $user = $this->randomMachineName(8);
        $this->casValidator->expects($this->once())
          ->method('validateTicket')
          ->with($this->equalTo('2.0'), $this->equalTo($ticket), $this->equalTo(array()))
          ->will($this->returnValue(array(
            'username' => $user,
            'pgt' => $pgt,
          )));
        $this->casLogin->expects($this->once())
          ->method('loginToDrupal')
          ->with($this->equalTo($user), $this->equalTo($ticket));
        $this->casHelper->expects($this->once())
          ->method('isProxy')
          ->will($this->returnValue($is_proxy));
        $service_controller->expects($this->once())
          ->method('setMessage')
          ->with($this->equalTo('You have been logged in.'));
        if ($is_proxy) {
          $this->casHelper->expects($this->once())
            ->method('storePGTSession')
            ->with($this->equalTo($pgt));
        }
        if ($return_to) {
          $query->expects($this->once())
            ->method('has')
            ->with($this->equalTo('returnto'))
            ->will($this->returnValue(TRUE));
          $query->expects($this->once())
            ->method('set')
            ->with($this->equalTo('destination'), $this->equalTo('bar'));
          $query->expects($this->once())
            ->method('remove')
            ->with($this->equalTo('returnto'));
          $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('<front>'))
            ->will($this->returnValue('https://example.com/bar'));
          $expected_response = new RedirectResponse('https://example.com/bar');
        }
        else {
          $query->expects($this->once())
            ->method('has')
            ->with($this->equalTo('returnto'))
            ->will($this->returnValue(FALSE));
          $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('<front>'))
            ->will($this->returnValue('https://example.com/front'));
          $expected_response = new RedirectResponse('https://example.com/front');
        }

        break;
    }

    $response = $service_controller->handle();
    $this->assertEquals($expected_response, $response);
  }

  /**
   * Provides parameters and return values for testHandle().
   *
   * @return array
   *   Parameters and return values.
   *
   * @see \Drupal\Tests\cas\Unit\Controller\ServiceControllerTest\testHandle
   */
  public function handleDataProvider() {
    // Test case 1: handle a single-log-out request.
    $params[] = array('single-log-out', FALSE, FALSE);

    // Test case 2: No ticket provided, no returnto parameter.
    $params[] = array('no-ticket', FALSE, FALSE);

    // Test case 3: No ticket provided, returnto parameter present.
    $params[] = array('no-ticket', FALSE, TRUE);

    // Test case 4: Ticket provided, no proxy, no returnto parameter.
    $params[] = array('login', FALSE, FALSE);

    // Test case 5: Ticket provided, proxy, no returnto parameter.
    $params[] = array('login', TRUE, FALSE);

    // Test case 6: Ticket provided, no proxy, returnto parameter present.
    $params[] = array('login', FALSE, TRUE);

    // Test case 7: Ticket provided, proxy, returnto parameter present.
    $params[] = array('login', TRUE, TRUE);

    return $params;
  }

  /**
   * Tests the error handling of the ServiceController handle function.
   *
   * @covers ::handle()
   *
   * @dataProvider handleFailureDataProvider
   */
  public function testHandleFailure($handler, $return_to) {
    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $request_bag = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $query = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->query = $query;
    $request_object->request = $request_bag;
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));
    $service_controller = $this->getMockBuilder('\Drupal\cas\Controller\ServiceController')
      ->setConstructorArgs(array(
        $this->casHelper,
        $this->casValidator,
        $this->casLogin,
        $this->casLogout,
        $this->requestStack,
        $this->urlGenerator,
      ))
      ->setMethods(array('setMessage'))
      ->getMock();
    $get_query_map = array(
      array('ticket', NULL, FALSE, TRUE),
      array('returnto', NULL, FALSE, 'bar'),
    );
    $query->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($get_query_map));
    $this->casHelper->expects($this->once())
      ->method('getCasProtocolVersion')
      ->will($this->returnValue('2.0'));
    $ticket = $this->randomMachineName(24);
    $query->expects($this->once())
      ->method('all')
      ->will($this->returnValue(array(
        'ticket' => $ticket,
      )));
    switch ($handler) {
      case 'validation':
        $this->casValidator->expects($this->once())
          ->method('validateTicket')
          ->will($this->throwException(new CasValidateException()));
        $service_controller->expects($this->once())
          ->method('setMessage')
          ->with(
            $this->equalTo('There was a problem validating your login, please contact a site administrator.'),
            $this->equalTo('error'));
        $this->urlGenerator->expects($this->once())
          ->method('generate')
          ->with($this->equalTo('<front>'))
          ->will($this->returnValue('https://example.com/front'));
        $expected_response = new RedirectResponse('https://example.com/front');
        break;

      case 'login':
        $pgt = $this->randomMachineName(24);
        $user = $this->randomMachineName(8);
        $this->casValidator->expects($this->once())
          ->method('validateTicket')
          ->with($this->equalTo('2.0'), $this->equalTo($ticket), $this->equalTo(array()))
          ->will($this->returnValue(array(
            'username' => $user,
            'pgt' => $pgt,
          )));
        $this->casLogin->expects($this->once())
          ->method('loginToDrupal')
          ->with($this->equalTo($user), $this->equalTo($ticket))
          ->will($this->throwException(new CasLoginException()));
        $service_controller->expects($this->once())
          ->method('setMessage')
          ->with(
            $this->equalTo('There was a problem logging in, please contact a site administrator.'),
            $this->equalTo('error'));
        if ($return_to) {
          $query->expects($this->once())
            ->method('has')
            ->with($this->equalTo('returnto'))
            ->will($this->returnValue(TRUE));
          $query->expects($this->once())
            ->method('set')
            ->with($this->equalTo('destination'), $this->equalTo('bar'));
          $query->expects($this->once())
            ->method('remove')
            ->with($this->equalTo('returnto'));
          $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('<front>'))
            ->will($this->returnValue('https://example.com/bar'));
          $expected_response = new RedirectResponse('https://example.com/bar');
        }
        else {
          $query->expects($this->once())
            ->method('has')
            ->with($this->equalTo('returnto'))
            ->will($this->returnValue(FALSE));
          $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('<front>'))
            ->will($this->returnValue('https://example.com/front'));
          $expected_response = new RedirectResponse('https://example.com/front');
        }
        break;
    }

    $response = $service_controller->handle();
    $this->assertEquals($expected_response, $response);
  }

  /**
   * Provides parameters and failures to testHandleFailure().
   *
   * @return array
   *   Parameters and failure expectations.
   *
   * @see \Drupal\Tests\cas\Unit\Controller\ServiceControllerTest\testHandleFailure
   */
  public function handleFailureDataProvider() {
    // Error case 1: Ticket validation error, no returnto parameter.
    $params[] = array('validation', FALSE);

    // Error case 2: Ticket validation error, returnto parameter.
    // Currently, this error behaves the same way as case 1.
    $params[] = array('validation', TRUE);

    // Error case 3: User login error, no returnto parameter.
    $params[] = array('login', FALSE);

    // Error case 4: User login error, returnto parameter.
    $params[] = array('login', TRUE);

    return $params;
  }
}
