<?php

namespace Drupal\cas;

class CasLogin {

  /**
   * @var \Drupal\cas\Cas
   */
  protected $cas;

  /**
   * Constructor.
   *
   * @param Cas $cas
   *   The phpCAS client library.
   */
  public function __construct(Cas $cas) {
    $this->cas = $cas;
  }

  /**
   * Attempts to log the authenticated CAS user into Drupal.
   *
   * This method should be used to login a user after they have successfully
   * authenticated with the CAS server.
   *
   * @param string $username
   *   The username to log in.
   */
  public function loginToDrupal($username) {
    $user = user_load_by_name($username);
    if (!$user) {
      // No user, need to register them if possible, otherwise set message
      // indicating they cannot log in because their account does not exist.
      drupal_set_message(t('Login to CAS successful, but the local Drupal account does not exist. Auto registration not yet implemented'), 'error');
      return FALSE;
    }
    else {
      user_login_finalize($user);

      return TRUE;
    }
  }
}
