<?php
/**
 * @file
 * Contains Drupal\cas\Subscriber\CasSubscriber.
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
use Drupal\cas\Service\CasHelper;

/**
 * Provides a CasSubscriber.
 */
class CasSubscriber implements EventSubscriberInterface {

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
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Constructs a new CasSubscriber.
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
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS Helper service.
   */
  public function __construct(RequestStack $request_stack, RouteMatchInterface $route_matcher, ConfigFactoryInterface $config_factory, AccountInterface $current_user, ConditionManager $condition_manager, CasHelper $cas_helper) {
    $this->requestStack = $request_stack;
    $this->routeMatcher = $route_matcher;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->conditionManager = $condition_manager;
    $this->casHelper = $cas_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('handle', 20);
    return $events;
  }

  /**
   * The entry point for our subscriber.
   *
   * @param GetResponseEvent $event
   *   The response event from the kernel.
   */
  public function handle(GetResponseEvent $event) {
    // Nothing to do if the user is already logged in.
    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    // Don't do anything if the current route is the service route.
    if ($this->isIgnoreableRoute()) {
      return;
    }

    // Don't do anything if this is a request from cron, drush, crawler, etc.
    if ($this->isNotNormalRequest()) {
      return;
    }

    // Check to see if we should require a forced login. It will set a response
    // on the event if so.
    if ($this->handleForcedPath($event)) {
      return;
    }

    // Check to see if we should intitiate a gateway auth check. It will set a
    // response on the event if so.
    $this->handleGateway($event);
  }

  /**
   * Check if a forced login path is configured, and force login if so.
   *
   * @return bool
   *   TRUE if we are forcing the login, FALSE otherwise
   */
  private function handleForcedPath(GetResponseEvent $event) {
    $config = $this->configFactory->get('cas.settings');
    if ($config->get('forced_login.enabled') != TRUE) {
      return FALSE;
    }

    // Check if user provided specific paths to force/not force a login.
    $condition = $this->conditionManager->createInstance('request_path');
    $condition->setConfiguration($config->get('forced_login.paths'));

    if ($this->conditionManager->execute($condition)) {
      $cas_login_url = $this->casHelper->getServerLoginUrl(array(
        'returnto' => $this->requestStack->getCurrentRequest()->attributes->get('_system_path'),
      ));
      $event->setResponse(new RedirectResponse($cas_login_url));
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check if we should implement the CAS gateway feature.
   *
   * @return bool
   *   TRUE if gateway mode was implemented, FALSE otherwise.
   *
   * @TODO We need to handle the "check once" vs "check always".
   */
  private function handleGateway(GetResponseEvent $event) {
    // These options enable gateway mode.
    $gateway_enabled_options = array(
      CAS_CHECK_ONCE,
      CAS_CHECK_ALWAYS,
    );

    $config = $this->configFactory->get('cas.settings');
    $check_frequency = $config->get('gateway.check_frequency');
    if (!in_array($check_frequency, $gateway_enabled_options)) {
      return FALSE;
    }

    // User can indicate specific paths to enable (or disable) gateway mode.
    $condition = $this->conditionManager->createInstance('request_path');
    $condition->setConfiguration($config->get('gateway.paths'));

    if ($this->conditionManager->execute($condition)) {
      $cas_login_url = $this->casHelper->getServerLoginUrl(array(
        'returnto' => $this->requestStack->getCurrentRequest()->attributes->get('_system_path'),
      ), TRUE);
      $event->setResponse(new RedirectResponse($cas_login_url));
      return TRUE;
    }

    return FALSE;
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
  private function isNotNormalRequest() {
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

  /**
   * Checks current request route against a list of routes we want to ignore.
   *
   * @return bool
   *   TRUE if we should ignore this request, FALSE otherwise.
   */
  private function isIgnoreableRoute() {
    $routes_to_ignore = array(
      'cas.service',
    );

    $current_route = $this->routeMatcher->getRouteName();
    if (in_array($current_route, $routes_to_ignore)) {
      return TRUE;
    }

    return FALSE;
  }
}
