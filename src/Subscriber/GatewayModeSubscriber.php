<?php
/**
 * @file
 * Contains Drupal\cas\Subscriber\GatewayModeSubscriber.
 */

namespace Drupal\cas\Subscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Condition\ConditionManager;
use Drupal\cas\Cas;

/**
 * Provides a GatewayModeSubscriber.
 */
class GatewayModeSubscriber implements EventSubscriberInterface {

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Route matcher object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Condition manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * @var \Drupal\cas\Cas
   */
  protected $cas;

  /**
   * Constructs a new GatewayModeSusbcriber.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request.
   * @param \Drupal\Core\Session\SessionManager $route_matcher
   *   The route matcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Condition\ConditionManager $condition_manager
   *   The condition manager.
   * @param \Drupal\cas\Cas $cas
   *   The CAS service.
   */
  public function __construct(RequestStack $request_stack, RouteMatchInterface $route_matcher, ConfigFactoryInterface $config_factory, AccountInterface $current_user, ConditionManager $condition_manager, Cas $cas) {
    $this->requestStack = $request_stack;
    $this->routeMatcher = $route_matcher;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->conditionManager = $condition_manager;
    $this->cas = $cas;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('gatewayDetect', 20);
    return $events;
  }

  /**
   * The entry point for our subscriber to implement the gateway feature of CAS.
   *
   * @param GetResponseEvent $event
   *   The response event from the kernel.
   */
  public function gatewayDetect(GetResponseEvent $event) {
    // Nothing to do if the user is already logged in.
    if ($this->currentUser->id() != 0) {
      return NULL;
    }

    // Don't do anything if this is a request from cron, drush, crawler, etc.
    if ($this->isNotNormalRequest()) {
      return NULL;
    }

    $gateway_options = array(
      CAS_CHECK_ONCE,
      CAS_CHECK_ALWAYS,
    );

    // @TODO, incorporate the paths from settings as well.
    $config = $this->configFactory->get('cas.settings');
    if (in_array($config->get('gateway.check_frequency'), $gateway_options)) {
      $cas_login_url = $this->cas->getLoginUrl(TRUE);
      return new RedirectResponse($cas_login_url, 302);
    }
  }

  /**
   * Check is the current request is a normal web request from a user.
   *
   * We don't want to perform any CAS redirects for things like cron
   * and drush.
   *
   * @return bool
   *   Whether or not this is a normal request.
   */
  public function isNotNormalRequest() {
    $current_request = $this->requestStack->getCurrentRequest();
    if (stristr($current_request->server->get('SCRIPT_FILENAME'), 'xmlrpc.php')) {
      return TRUE;
    }
    if (stristr($current_request->server->get('SCRIPT_FILENAME'), 'cron.php')) {
      return TRUE;
    }
    if (function_exists('drush_verify_cli') && drush_verify_cli()) {
      return TRUE;
    }

    if ($current_request->server->get('HTTP_USER_AGENT')) {
      $crawlers = array(
        'Google',
        'msnbot',
        'Rambler',
        'Yahoo',
        'AbachoBOT',
        'accoona',
        'AcoiRobot',
        'ASPSeek',
        'CrocCrawler',
        'Dumbot',
        'FAST-WebCrawler',
        'GeonaBot',
        'Gigabot',
        'Lycos',
        'MSRBOT',
        'Scooter',
        'AltaVista',
        'IDBot',
        'eStyle',
        'Scrubby',
        'gsa-crawler',
      );
      // Return on the first find.
      foreach ($crawlers as $c) {
        if (stripos($current_request->server->get('HTTP_USER_AGENT'), $c) !== FALSE) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }
}
