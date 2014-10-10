<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Controller\ProxyCallbackControllerTest.
 */

namespace Drupal\Tests\cas\Unit\Controller;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * ProxyCallbackController unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Controller\ProxyCallbackController
 */
class ProxyCallbackControllerTest extends UnitTestCase {

  /**
   * The mocked database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $connection;

  /**
   * The mocked Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->requestStack = $this->getMock('\Symfony\Component\HttpFoundation\RequestStack');
    $this->connection = $this->getMockBuilder('\Drupal\Core\Database\Connection')
                             ->disableOriginalConstructor()
                             ->getMock();
  }

  /**
   * Test the proxy callback.
   *
   * @covers ::callback()
   *
   * @dataProvider callbackDataProvider
   */
  public function testCallback($pgt_iou, $pgt_id, $request_exception) {
    $proxy_callback_controller = $this->getMockBuilder('\Drupal\cas\Controller\ProxyCallbackController')
      ->setConstructorArgs(array($this->connection, $this->requestStack))
      ->setMethods(array('storePgtMapping'))
      ->getMock();
    $request = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $query = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request->query = $query;
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request));

    if (!$request_exception) {
      $query->expects($this->any())
        ->method('get')
        ->will($this->onConsecutiveCalls($pgt_id, $pgt_iou));
      $expected_response = new Response('OK', 200);
    }
    else {
      $query->expects($this->any())
        ->method('get')
        ->will($this->returnValue(FALSE));
      $expected_response = new Response('Missing necessary parameters', 400);
    }

    $response = $proxy_callback_controller->callback();
    $this->assertEquals($expected_response->getStatusCode(), $response->getStatusCode());
  }

  /**
   * Provide parameters and return expectations for testCallback.
   *
   * @return array
   *   Parameters and return values.
   *
   * @see \Drupal\Tests\cas\Unit\Controller\ProxyCallbackControllerTest::testCallback
   */
  public function callbackDataProvider() {
    // Two scenarios: success and failure.
    return array(
      array($this->randomMachineName(24), $this->randomMachineName(24), FALSE),
      array($this->randomMachineName(24), $this->randomMachineName(24), TRUE),
    );
  }

}
