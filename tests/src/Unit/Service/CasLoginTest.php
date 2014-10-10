<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Service\CasLoginTest.
 */

 namespace Drupal\Tests\cas\Unit\Service;

 use Drupal\Tests\UnitTestCase;
 use Drupal\cas\Service\CasLogin;
 use Drupal\Core\Config\ConfigFactoryInterface;
 use Drupal\cas\Exception\CasLoginException;
 use Drupal\Core\Entity\EntityManagerInterface;
 use Drupal\Core\Entity\EntityStorageException;

/**
 * CasLogin unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Service\CasLogin
 */
class CasLoginTest extends UnitTestCase {

  /**
   * The mocked Entity Manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');
  }

  /**
   * Test logging a Cas user into Drupal.
   *
   * @covers ::loginToDrupal()
   *
   * @dataProvider loginToDrupalDataProvider
   */
  public function testLoginToDrupal($account_auto_create, $account_exists) {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'user_accounts.auto_register' => $account_auto_create,
      ),
    ));

    $cas_login = $this->getMockBuilder('Drupal\cas\Service\CasLogin')
      ->setMethods(array('userLoadByName', 'userLoginFinalize'))
      ->setConstructorArgs(array($config_factory, $this->entityManager))
      ->getMock();

    if ($account_auto_create && !$account_exists) {
      $entity_storage = $this->getMock('Drupal\Core\Entity\EntityStorageInterface');
      $entity_account = $this->getMock('Drupal\Core\Entity\EntityInterface');
      $this->entityManager->expects($this->once())
        ->method('getStorage')
        ->will($this->returnValue($entity_storage));
      $entity_storage->expects($this->once())
        ->method('create')
        ->will($this->returnValue($entity_account));
    }

    // We cannot test actual login, so we just check if functions were called.
    $cas_login->expects($this->once())
      ->method('userLoadByName')
      ->will($this->returnValue($account_exists ? new \StdClass() : FALSE));
    $cas_login->expects($this->once())
      ->method('userLoginFinalize');

    $cas_login->loginToDrupal($this->randomMachineName(8));
  }

  /**
   * Provide parameters to testLoginToDrupal.
   *
   * @return array
   *   Parameters.
   *
   * @see \Drupal\Tests\cas\Unit\Service\CasLoginTest::testLoginToDrupal
   */
  public function loginToDrupalDataProvider() {
    /* There are three positive scenarios: 1. Account exists and autocreate
     * off, 2. Account exists and autocreate on, 3. Account does not exist, and
     * autocreate on.
     */
    return array(
      array(FALSE, TRUE),
      array(TRUE, TRUE),
      array(TRUE, FALSE),
    );
  }

  /**
   * Test exceptions thrown by loginToDrupal().
   *
   * @covers ::loginToDrupal()
   *
   * @dataProvider loginToDrupalExceptionDataProvider
   */
  public function testLoginToDrupalException($account_auto_create, $account_exists, $exception_type, $exception_message) {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'user_accounts.auto_register' => $account_auto_create,
      ),
    ));

    $cas_login = $this->getMockBuilder('Drupal\cas\Service\CasLogin')
      ->setMethods(array('userLoadByName', 'userLoginFinalize'))
      ->setConstructorArgs(array($config_factory, $this->entityManager))
      ->getMock();

    if ($account_auto_create && !$account_exists) {
      $entity_storage = $this->getMock('Drupal\Core\Entity\EntityStorageInterface');
      $entity_account = $this->getMock('Drupal\Core\Entity\EntityInterface');
      $this->entityManager->expects($this->once())
        ->method('getStorage')
        ->will($this->returnValue($entity_storage));
      $entity_storage->expects($this->once())
        ->method('create')
        ->will($this->throwException(new EntityStorageException()));
    }

    // We cannot test actual login, so we just check if functions were called.
    $cas_login->expects($this->once())
      ->method('userLoadByName')
      ->will($this->returnValue($account_exists ? new \StdClass() : FALSE));

    $this->setExpectedException($exception_type, $exception_message);
    $cas_login->loginToDrupal($this->randomMachineName(8));
  }

  /**
   * Provides parameters and exceptions for testLoginToDrupalException.
   *
   * @return array
   *   Parameters and exceptions
   *
   * @see \Drupal\Tests\cas\Unit\Service\CasLoginTest::testLoginToDrupalException
   */
  public function loginToDrupalExceptionDataProvider() {
    /* There are two exceptions that can be triggered: the user does not exist
     * and account autocreation is off, and user does not exist and account
     * autocreation failed.
     */
    $exception_type = '\Drupal\cas\Exception\CasLoginException';
    return array(
      array(
        FALSE,
        FALSE,
        $exception_type,
        'Cannot login, local Drupal user account does not exist.',
      ),
      array(
        TRUE,
        FALSE,
        $exception_type,
        'Error registering user: ',
      ),
    );
  }

}
