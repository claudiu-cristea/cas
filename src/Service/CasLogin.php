<?php

namespace Drupal\cas\Service;

use Drupal\cas\Exception\CasLoginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageException;

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
   * Constructs a CasLogin object.
   *
   * @param ConfigFactoryInterface $settings
   *   The config factory object
   */
  public function __construct(ConfigFactoryInterface $settings, EntityManagerInterface $entity_manager) {
    $this->settings = $settings;
    $this->entityManager = $entity_manager;
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
   * @TODO We need the user's session ID to store along with the service
   * ticket for single-sign-out. But it doens't look like we can get
   * session until session managers's save method is called from the
   * AuthenticationSubscriber which happens at the end of a request.
   * See http://drupal.stackexchange.com/questions/131063
   * See https://www.drupal.org/node/2348249
   */
  public function loginToDrupal($username) {
    $account = user_load_by_name($username);
    if (!$account) {
      $config = $this->settings->get('cas.settings');
      if ($config->get('user_accounts.auto_register') === TRUE) {
        $account = $this->registerUser($username);
      }
      else {
        throw new CasLoginException("Cannot login, local Drupal user account does not exist.");
      }
    }

    user_login_finalize($account);
  }

  /**
   * Register a CAS user.
   *
   * @param string $username
   *   Register a new account with the provided username.
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
}
