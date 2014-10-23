<?php

namespace Drupal\cas\Service;

use Drupal\cas\Exception\CasLoginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Database\Connection;

class CasLogin {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $settings;

  /**
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $sessionManager;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a CasLogin object.
   *
   * @param ConfigFactoryInterface $settings
   *   The config factory object
   */
  public function __construct(ConfigFactoryInterface $settings, EntityManagerInterface $entity_manager, SessionManagerInterface $session_manager, Connection $database_connection) {
    $this->settings = $settings;
    $this->entityManager = $entity_manager;
    $this->sessionManager = $session_manager;
    $this->connection = $database_connection;
  }

  /**
   * Attempts to log the authenticated CAS user into Drupal.
   *
   * This method should be used to login a user after they have successfully
   * authenticated with the CAS server.
   *
   * @param string $username
   *   The username of the account to log in.
   *
   * @throws CasLoginException
   */
  public function loginToDrupal($username, $ticket) {
    $account = $this->userLoadByName($username);
    if (!$account) {
      $config = $this->settings->get('cas.settings');
      if ($config->get('user_accounts.auto_register') === TRUE) {
        $account = $this->registerUser($username);
      }
      else {
        throw new CasLoginException("Cannot login, local Drupal user account does not exist.");
      }
    }

    $this->userLoginFinalize($account);
    $this->storeLoginSessionData($this->sessionManager->getId(), $ticket);
  }

  /**
   * Register a CAS user.
   *
   * @param string $username
   *   Register a new account with the provided username.
   *
   * @throws CasLoginException
   */
  private function registerUser($username) {
    try {
      $user_storage = $this->entityManager->getStorage('user');
      $account = $user_storage->create(array(
        'name' => $username,
        'status' => 1,
      ));
      $account->enforceIsNew();
      $account->save();
      return $account;
    }
    catch (EntityStorageException $e) {
      throw new CasLoginException("Error registering user: " . $e->getMessage());
    }
  }

  /**
   * Encapsulate user_load_by_name. 
   *
   * See https://www.drupal.org/node/2157657
   *
   * @param string $username
   *   The username to lookup a User entity by.
   *
   * @return object|bool
   *   A loaded $user object or FALSE on failure.
   *
   * @codeCoverageIgnore
   */
  protected function userLoadByName($username) {
    return user_load_by_name($username);
  }

  /**
   * Encapsulate user_login_finalize.
   *
   * See https://www.drupal.org/node/2157657
   *
   * @codeCoverageIgnore
   */
  protected function userLoginFinalize($account) {
    user_login_finalize($account);
  }

  /**
   * Store the Session ID and ticket for single-log-out purposes.
   *
   * @param string $session_id
   *   The hashed session ID, to be used to kill the session later.
   * @param string $ticket
   *   The CAS service ticket to be used as the lookup key.
   *
   * @codeCoverageIgnore
   */
  protected function storeLoginSessionData($session_id, $ticket) {
    $this->connection->insert('cas_login_data')
      ->fields(
        array('sid', 'ticket'),
        array($session_id, $ticket)
      )
      ->execute();
  }
}
