<?php

namespace Drupal\cas\Controller;

use Drupal\cas\Cas;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ForceLoginController implements ContainerInjectionInterface {

  /**
   * @var \Drupal\cas\Cas
   */
  protected $cas;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   *
   * @param Cas $cas
   *   The CAS service.
   */
  public function __construct(Cas $cas) {
    $this->cas = $cas;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('cas.cas'));
  }

  /**
   * Handles a page request for our forced login route.
   */
  public function forceLogin() {
    $cas_login_url = $this->cas->getLoginUrl();

    return new RedirectResponse($cas_login_url, 302);
  }
}
