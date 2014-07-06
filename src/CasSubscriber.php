<?php
/**
 * @file
 * Contains Drupal\cas\CasSubscriber.
 */

namespace Drupal\cas;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a CasSubscriber.
 */
class CasSubscriber implements EventSubscriberInterface {

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Route matcher object.
   *
   * @var Drupal\Core\Routing\RouteMatchInterface
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
   * The patch matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Constructs a new CasSubscriber.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_matcher
   *   The route matcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   */
  public function __construct(Request $request, RouteMatchInterface $route_matcher, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, ModuleHandlerInterface $module_handler, AliasManagerInterface $alias_manager, AccountInterface $current_user, PathMatcherInterface $path_matcher) {
    $this->request = $request;
    $this->routeMatcher = $route_matcher;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->aliasManager = $alias_manager;
    $this->currentUser = $current_user;
    $this->pathMatcher = $path_matcher;
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
   * @param Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function casLoad(GetResponseEvent $event) {
    // Nothing to do if the user is already logged in.
    if ($this->currentUser->id() != 0) {
      return;
    }

    // Don't do anything if this is a request from cron, drush, crawler, etc.
    if ($this->isNotNormalRequest()) {
      return;
    }

    // User can exclude certain paths from being CAS enabled.
    if ($this->isExcludedPath()) {
      return;
    }

    $force_authentication = $this->forceLogin();
    $gateway_mode = $this->gatewayModeEnabled();
    $request_method = $this->request->server->get('REQUEST_METHOD');
    if ($force_authentication || ($gateway_mode && $request_method == 'GET')) {
      $this->loginCheck($force_authentication);
    }
  }

  /**
   * Checks to see if the user needs to be logged in.
   *
   * @param bool $force_authentication
   *   If TRUE, require that the user be authenticated with the CAS server
   *   before proceeding. Otherwise, check with the CAS server to see if the
   *   user is already logged in.
   */
  private function loginCheck($force_authentication = TRUE) {
    if (!$this->phpcas_load()) {
      // No need to print a message, as the user will already see the failed
      // include_once calls.
      return;
    }

    // Start a drupal session.
    drupal_session_start();

    // For CAS 3 logoutRequests.
    $this->single_sign_out_save_ticket();

    // Initialize phpCAS.
    $this->phpcas_init();

    // Try phpCAS authentication test.
    if ($force_authentication) {
      phpCAS::forceAuthentication();
    }
    else {
      $logged_in = phpCAS::checkAuthentication();

      // Done if there is no valid CAS user.
      if (!$logged_in) {
        return;
      }
    }

    // Build the cas_user object and allow modules to alter it.
    $cas_user = new stdClass();
    $cas_user->name = phpCAS::getUser();
    $cas_user->login = TRUE;
    $cas_user->register = $this->configFactory->get('cas.settings')->get('user_register');
    $cas_user->attributes = $this->phpcas_attributes();
    $this->moduleHandler->alter('cas_user', $cas_user);

    // Bail out if a module denied login access for this user or unset the user
    // name.
    if (empty($cas_user->login) || empty($cas_user->name)) {
      // Only set a warning if we forced login.
      if ($force_authentication) {
        drupal_set_message(t('The user account %name is not available on this site.', array('%name' => $cas_user->name)), 'error');
      }
      return;
    }

    // Proceed with the login process, using the altered CAS username.
    $cas_name = $cas_user->name;

    // Check for a blocked user.
    $blocked = FALSE;
    if ($this->external_user_is_blocked($cas_name)) {
      $blocked = 'The username %cas_name has been blocked.';
    }

    if ($blocked) {
      // Only display error messages if the user intended to log in.
      if ($force_authentication) {
        $this->loggerFactory->get('cas')->log(WATCHDOG_WARNING, $blocked, array('%cas_name' => $cas_name));
        drupal_set_message(t($blocked, array('%cas_name' => $cas_name)), 'error');
      }
      return;
    }

    $account = $this->user_load_by_name($cas_name);

    // Automatic user registration.
    if (!$account && $cas_user->register) {
      // No account could be found and auto registration is enabled, so attempt
      // to register a new user.
      $account = $this->userRegister($cas_name);
      if (!$account) {
        // The account could not be created, set a message.
        if ($force_authentication) {
          drupal_set_message(t('A new account could be created for %cas_name. The username is already in use on this site.', array('%cas_name' => $cas_name)), 'error');
        }
        return;
      }
    }

    // Final check to make sure we have a good user.
    if ($account && $account->uid > 0) {
      // Save the altered CAS name for future use.
      $_SESSION['cas_name'] = $cas_name;

      $cas_first_login = !$account->login;

      // Save single sign out information.
      if (!empty($_SESSION['cas_ticket'])) {
        $this->single_sign_out_save_token($account);
      }
    }

    // NOT DONE YET!
  }

  /**
   * Register a CAS user with some default values.
   *
   * @param string $cas_name
   *   The name of the CAS user.
   * @param array $options
   *   An associative array of options, with the following elements:
   *    - 'edit': An array of fields and values for the new user. If omitted,
   *      reasonable defaults are use.
   *    - 'invoke_cas_user_presave': Defaults to FALSE. Whether or not to invoke
   *      hook_cas_user_presave() on the newly created account.
   *
   * @return \Drupal\user\Entity\User
   *   The user object of the created user, or FALSE if the user cannot be
   *   created.
   */
  private function userRegister($cas_name, $options = array()) {
    // First check to see if user name is available.
    if ((bool) db_select('users')->fields('users', array('name'))->condition('name', db_like($cas_name), 'LIKE')->range(0, 1)->execute()->fetchField()) {
      return FALSE;
    }

    $account = new \Drupal\user\Entity\User();
    $account->setUsername($cas_name);
    $account->setPassword(user_password());
    $email = $this->configFactory->get('cas.settings')->get('domain') ? $cas_name . '@' . $this->configFactory->get('cas.settings')->get('domain') : '';
    $account->setEmail($email);
    $account->activate();

    $roles = $this->roles();
    foreach ($roles as $rid) {
      $account->addRole($rid);
    }

    // Save the account.
    $account->save();
    $this->loggerFactory->get("user")->log(WATCHDOG_NOTICE, 'new user: %n (CAS)', array('%n' => $cas_name));

    if (!empty($options['invoke_cas_user_presave'])) {
      // Populate $edit with some basic properties.
      $edit = array(
        'cas_user' => array(
          'name' => $cas_name,
        ),
      );

      // Allow other modules to make their own custom changes.
      $this->cas_user_module_invoke('presave', $edit, $account);

      $account->save();

    }

    return user_load($account->id());
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
    if (in_array($this->configFactory->get('cas.settings')->get('check_frequency'), $gateway_options)) {
      return TRUE;
    }

    return FASLE;
  }

  /**
   * Determine if we should require the user be authenticated.
   *
   * @return bool
   *   TRUE if we should require the user be authenticated, FALSE otherwise.
   */
  private function forceLogin() {
    // We have a dedicated route for forcing a login.
    if ($this->routeMatcher->getRouteName() == 'cas.login') {
      return TRUE;
    }

    // Set default behavior for how forced login pages should be handled.
    // The user either wants to force by default, and exclude specific pages,
    // or exclude by default, and force specific pages.
    $force_login = ($this->configFactory->get('cas.settings')->get('access') === CAS_REQUIRE_ALL_EXCEPT) ? TRUE : FALSE;

    // See if current path matches the list provided in settings. If so,
    // we reverse the default behavior set above.
    $aliased_path = $this->getCurrentPathAlias();
    if ($pages = $this->configFactory->get('cas.settings')->get('pages')) {
      if ($this->pathMatcher->matchPath($aliased_path, $pages)) {
        $force_login = !$force_login;
      }
    }

    return $force_login;
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
    if (stristr($this->request->server->get('SCRIPT_FILENAME'), 'xmlrpc.php')) {
      return TRUE;
    }
    if (stristr($this->request->server->get('SCRIPT_FILENAME'), 'cron.php')) {
      return TRUE;
    }
    if (function_exists('drush_verify_cli') && drush_verify_cli()) {
      return TRUE;
    }

    if ($this->request->server->get('HTTP_USER_AGENT')) {
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
        if (stripos($this->request->server->get('HTTP_USER_AGENT'), $c) !== FALSE) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Checks if the current path is excluded from any CAS authentication.
   */
  public function isExcludedPath() {
    $aliased_path = $this->getCurrentPathAlias();

    // Check against the list of exclude paths.
    $excluded_pages = $this->configFactory->get('cas.settings')->get('exclude');
    if ($this->pathMatcher->matchPath($aliased_path, $excluded_pages)) {
      return FALSE;
    }
  }

  /**
   * Determine the current path alias.
   *
   * At the time of writing (pre D8 beta), I couldn't find the "recommended"
   * approach to get this. However, the RequestPath condition plugin, which
   * is used by the Block module to provide path exclusions, needs this
   * functionality as well. We can check back on the final approach taken there.
   *
   * @return string
   *   The current path as an alias.
   */
  private function getCurrentPathAlias() {
    $current_path = current_path();
    return $this->aliasManager->getAliasByPath($current_path);
  }

}
