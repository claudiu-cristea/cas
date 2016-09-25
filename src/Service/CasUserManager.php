<?php

namespace Drupal\cas\Service;

use Drupal\cas\Event\CasPreAuthEvent;
use Drupal\cas\Event\CasUserLoadEvent;
use Drupal\externalauth\AuthmapInterface;
use Drupal\externalauth\Exception\ExternalAuthRegisterException;
use Drupal\cas\Exception\CasLoginException;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\cas\CasPropertyBag;
use Drupal\Component\Utility\Crypt;

/**
 * Class CasUserManager.
 */
class CasUserManager {

  /**
   * Used to include the externalauth service from the external_auth module.
   *
   * @var \Drupal\externalauth\ExternalAuthInterface
   */
  protected $externalAuth;

  /**
   * An authmap service object.
   *
   * @var \Drupal\externalauth\AuthmapInterface
   */
  protected $authmap;

  /**
   * Stores settings object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $settings;

  /**
   * Used to get session data.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * Used when storing CAS login data.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Used to dispatch CAS login events.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  protected $provider = 'cas';

  /**
   * CasUserManager constructor.
   *
   * @param \Drupal\externalauth\ExternalAuthInterface $external_auth
   *   The external auth interface.
   * @param \Drupal\externalauth\AuthmapInterface $authmap
   *   The authmap interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $settings
   *   The settings.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   * @param \Drupal\Core\Database\Connection $database_connection
   *   The database connection.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ExternalAuthInterface $external_auth, AuthmapInterface $authmap, ConfigFactoryInterface $settings, SessionInterface $session, Connection $database_connection, EventDispatcherInterface $event_dispatcher) {
    $this->externalAuth = $external_auth;
    $this->authmap = $authmap;
    $this->settings = $settings;
    $this->session = $session;
    $this->connection = $database_connection;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Register a local Drupal user given a CAS username.
   *
   * @param string $authname
   *   The CAS username.
   * @param array $auto_assigned_roles
   *   Array of roles to assign to this user.
   *
   * @throws CasLoginException
   *
   * @return \Drupal\user\UserInterface
   *   The user entity of the newly registered user.
   */
  public function register($authname, $auto_assigned_roles = []) {
    try {
      $user = $this->externalAuth->register($authname, $this->provider);
    }
    catch (ExternalAuthRegisterException $e) {
      throw new CasLoginException($e->getMessage());
    }

    if (!empty($auto_assigned_roles)) {
      foreach ($auto_assigned_roles as $auto_assigned_role) {
        $user->addRole($auto_assigned_role);
      }
      $user->save();
    }

    return $user;
  }

  /**
   * Attempts to log the user in to the Drupal site.
   *
   * @param CasPropertyBag $property_bag
   *   CasPropertyBag containing username and attributes from CAS.
   * @param string $ticket
   *   The service ticket.
   *
   * @throws CasLoginException
   *   Thrown if there was a problem logging in the user.
   */
  public function login(CasPropertyBag $property_bag, $ticket) {
    // Dispatch an event that allows modules to change user data we received
    // from CAS before attempting to use it to load a Drupal user.
    // Auto-registration can also be disabled for this user if their account
    // does not exist.
    $user_load_event = new CasUserLoadEvent($property_bag);
    $this->eventDispatcher->dispatch(CasHelper::EVENT_USER_LOAD, $user_load_event);

    $account = $this->externalAuth->load($property_bag->getUsername(), $this->provider);
    // No user exists.
    if ($account === FALSE) {
      // Check if we should create the user or not.
      $config = $this->settings->get('cas.settings');
      if ($config->get('user_accounts.auto_register') === TRUE) {
        if ($user_load_event->allowAutoRegister) {
          $account = $this->register($property_bag->getUsername(), $config->get('user_accounts.auto_assigned_roles'));
        }
        else {
          throw new CasLoginException("Cannot register user, an event listener denied access.");
        }
      }
      else {
        throw new CasLoginException("Cannot login, local Drupal user account does not exist.");
      }
    }

    // Dispatch an event that allows modules to prevent this user from logging
    // in and/or alter the user entity before we save it.
    $pre_auth_event = new CasPreAuthEvent($account, $property_bag);
    $this->eventDispatcher->dispatch(CasHelper::EVENT_PRE_AUTH, $pre_auth_event);

    // Save user entity since event listeners may have altered it.
    $account->save();

    if (!$pre_auth_event->allowLogin) {
      throw new CasLoginException("Cannot login, an event listener denied access.");
    }

    $this->externalAuth->userLoginFinalize($account, $property_bag->getUsername(), $this->provider);
    $this->storeLoginSessionData($this->session->getId(), $ticket);
  }

  /**
   * Store the Session ID and ticket for single-log-out purposes.
   *
   * @param string $session_id
   *   The session ID, to be used to kill the session later.
   * @param string $ticket
   *   The CAS service ticket to be used as the lookup key.
   */
  protected function storeLoginSessionData($session_id, $ticket) {
    if ($this->settings->get('cas.settings')->get('logout.enable_single_logout') === TRUE) {
      $plainsid = $session_id;
    }
    else {
      $plainsid = '';
    }
    $this->connection->insert('cas_login_data')
      ->fields(
        array('sid', 'plainsid', 'ticket'),
        array(Crypt::hashBase64($session_id), $plainsid, $ticket)
      )
      ->execute();
  }

  /**
   * Return CAS username for account, or FALSE if it doesn't have one.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account object.
   *
   * @return bool|string
   *   The CAS username if it exists, or FALSE otherwise.
   */
  public function getCasUsernameForAccount(UserInterface $account) {
    return $this->authmap->get($account->id(), 'cas');
  }

  /**
   * Return uid of account associated with passed in CAS username.
   *
   * @param string $cas_username
   *   The CAS username to lookup.
   *
   * @return bool|int
   *   The uid of the user associated with the $cas_username, FALSE otherwise.
   */
  public function getUidForCasUsername($cas_username) {
    return $this->authmap->getUid($cas_username, 'cas');
  }

  /**
   * Save an association of the passed in Drupal user account and CAS username.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account entity.
   * @param string $cas_username
   *   The CAS username.
   */
  public function setCasUsernameForAccount(UserInterface $account, $cas_username) {
    $this->authmap->save($account, 'cas', $cas_username);
  }

  /**
   * Remove the CAS username association with the provided user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account entity.
   */
  public function removeCasUsernameForAccount(UserInterface $account) {
    $this->authmap->delete($account->id());
  }

}
