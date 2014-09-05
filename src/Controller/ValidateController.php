<?php

namespace Drupal\cas\Controller;

use Drupal\cas\CasLogin;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\cas\CasClient;

class ValidateController implements ContainerInjectionInterface {

  /**
   * @var \Drupal\cas\CasClient
   */
  protected $casClient;

  /**
   * @var \Drupal\cas\CasLogin
   */
  protected $casLogin;

  /**
   * Constructor.
   *
   * @param CasClient $cas_client
   *   The configured phpCAS client.
   * @param CasLogin $cas_login
   *   The service used to log a CAS user into Drupal.
   */
  public function __construct(CasClient $cas_client, CasLogin $cas_login) {
    $this->casClient = $cas_client;
    $this->casLogin = $cas_login;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('cas.client'), $container->get('cas.login'));
  }

  /**
   * Handles a request to validate a CAS service ticket.
   */
  public function validate() {
    $this->casClient->configureClient();
    // The force authentication method will recognize that this request
    // is for a service ticket validation based on the session data
    // previously set and the presence of the service ticket parameter.
    \phpCAS::forceAuthentication();

    // Once we reach this point, the user has been authenticated and we should
    // log them in to Drupal, and then redirect to their after-login destination
    // while also stripping the service ticket from the URL.
    try {
      return $this->casLogin->loginToDrupal();
    }
    catch (\Exception $e) {
      // TODO.
      throw $e;
    }
  }
}
