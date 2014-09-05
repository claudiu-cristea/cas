<?php
/**
 * @file
 * Contains Drupal\cas\CasSubscriber.
 *
 * NOTE: This is being ripped apart and functionality split between
 * several listeners and controllers and services.
 */

namespace Drupal\cas;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Condition\ConditionManager;

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
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Session handler.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $sessionManager;

  /**
   * Condition manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * Constructs a new CasSubscriber.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request.
   * @param \Drupal\Core\Session\SessionManager $route_matcher
   *   The route matcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session manager.
   * @param \Drupal\Core\Condition\ConditionManager $condition_manager
   *   The condition manager.
   */
  public function __construct(RequestStack $request_stack, RouteMatchInterface $route_matcher, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, ModuleHandlerInterface $module_handler, AccountInterface $current_user, SessionManagerInterface $session_manager, ConditionManager $condition_manager) {
    $this->requestStack = $request_stack;
    $this->routeMatcher = $route_matcher;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->sessionManager = $session_manager;
    $this->conditionManager = $condition_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('casLoad', 20);
    return $events;
  }

  /**
   * Method invoked for request event to initiate phpCAS.
   *
   * This is the main entry point for CAS functionality. Here we check
   * if the proper conditions are met to load and run phpCAS code.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function casLoad(GetResponseEvent $event) {
    $config = $this->configFactory->get('cas.settings');

    // Nothing to do if the user is already logged in.
    if ($this->currentUser->id() != 0) {
      return;
    }

    // Don't do anything if this is a request from cron, drush, crawler, etc.
    if ($this->isNotNormalRequest()) {
      return;
    }

    $force_login = $this->checkForceLogin();
    $gateway_mode = $this->gatewayModeEnabled();
    $request_method = $this->requestStack->getCurrentRequest()->server->get('REQUEST_METHOD');
    if ($force_login || ($gateway_mode && $request_method == 'GET')) {
      if (!$this->loadPhpCasLibrary()) {
        // No need to print a message, as the user will already see the failed
        // include_once calls.
        return;
      }

      // Start a drupal session.
      $this->sessionManager->start();

      // Initialize phpCAS.
      $this->initializePhpCas();

      // @TODO
    }
  }

  /**
   * Determines if the user has enabled the gateway mode feature of CAS.
   *
   * @see phpCAS::checkAuthentication()
   *
   * @return bool
   *   TRUE if we should query the CAS server to see if the user is already
   *   authenticated, FALSE otherwise.
   */
  private function gatewayModeEnabled() {
    $gateway_options = array(
      CAS_CHECK_ONCE,
      CAS_CHECK_ALWAYS,
    );

    // @TODO, incorporate the paths from settings as well.

    if (in_array($this->configFactory->get('cas.settings')->get('gateway.check_frequency'), $gateway_options)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Determine if we should require the user be authenticated.
   *
   * @return bool
   *   TRUE if we should require the user be authenticated, FALSE otherwise.
   */
  private function checkForceLogin() {
    // We have a dedicated route for forcing a login.
    if ($this->routeMatcher->getRouteName() == 'cas.login') {
      return TRUE;
    }

    $config = $this->configFactory->get('cas.settings');
    // User may have forced login enabled.
    if ($config->get('forced_login.enabled') != TRUE) {
      return FALSE;
    }

    // Check if user provided specific paths to force/not force a login.
    $condition = $this->conditionManager->createInstance('request_path');
    $condition->setConfiguration($config->get('forced_login.paths'));

    // @TODO, why doesn't negate get applied?
    if ($condition->evaluate()) {
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

  private function buildServiceUrl() {

  }
}
