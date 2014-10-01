<?php

namespace Drupal\cas\Service;

class CasLogin {

  /**
   * Attempts to log the authenticated CAS user into Drupal.
   *
   * This method should be used to login a user after they have successfully
   * authenticated with the CAS server.
   *
   * @param string $username
   *   The username to log in.
   *
   * @TODO We need the user's session ID to store along with the service
   * ticket for single-sign-out. But it doens't look like we can get
   * session until session managers's save method is called from the
   * AuthenticationSubscriber which happens at the end of a request.
   * See http://drupal.stackexchange.com/questions/131063
   * See https://www.drupal.org/node/2348249
   */
  public function loginToDrupal($username) {
    global $user;

    $account = user_load_by_name($username);
    if (!$account) {
      // No user, need to register them if possible, otherwise set message
      // indicating they cannot log in because their account does not exist.
      drupal_set_message(t('Login to CAS successful, but the local Drupal account does not exist. Auto registration not yet implemented'), 'error');
      return FALSE;
    }
    else {
      user_login_finalize($account);
      return TRUE;
    }
  }
}
