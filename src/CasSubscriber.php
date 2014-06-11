<?php 
/**
 * @file
 * Contains Drupal\cas\CasSubscriber.
 */
namespace Drupal\cas;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a CasSubscriber.
 */
class CasSubscriber implements EventSubscriberInterface {

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
   * Constructs a new CasSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, ModuleHandlerInterface $module_handler, AliasManagerInterface $alias_manager, AccountInterface $current_user) {
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->aliasManager = $alias_manager;
    $this->currentUser = $current_user;
  }

  /**
   * // only if KernelEvents::REQUEST
   * @see Symfony\Component\HttpKernel\KernelEvents for details.
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function casLoad(GetResponseEvent $event) {
    if ($this->currentUser->id() == 0) {
      $force_authentication = $this->force_login();
      $check_authentication = $this->allow_check_for_login();
      $request_type = $_SERVER['REQUEST_METHOD'];
      $conditions = $force_authentication || ($check_authentication && $request_type == 'GET');
      if ($conditions) {
        $this->login_check($force_authentication);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('casLoad', 20);
    return $events;
  }

  /**
   * Checks to see if the user needs to be logged in.
   *
   * @param $force_authentication
   *   If TRUE, require that the user be authenticated with the CAS server
   *   before proceeding. Otherwise, check with the CAS server to see if the
   *   user is already logged in.
   */
  private function login_check($force_authentication = TRUE) {
    if ($this->currentUser->id()) {
      // User already logged in.
      return;
    }

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
    $cas_user = new stdClass;
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
      $account = $this->user_register($cas_name);
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
   * @param $cas_name
   *   The name of the CAS user.
   * @param $options
   *   An associative array of options, with the following elements:
   *    - 'edit': An array of fields and values for the new user. If omitted,
   *      reasonable defaults are use.
   *    - 'invoke_cas_user_presave': Defaults to FALSE. Whether or not to invoke
   *      hook_cas_user_presave() on the newly created account.
   *
   * @return
   *   The user object of the created user, or FALSE if the user cannot be
   *   created.
   */
  private function user_register($cas_name, $options = array()) {
    // First check to see if user name is available.
    if ((bool) db_select('users')->fields('users', array('name'))->condition('name', db_like($cas_name), 'LIKE')->range(0, 1)->execute()->fetchField()) {
      return FALSE;
    }

    $account = new \Drupal\user\Entity\User;
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
   * Determine if we should automatically check if the user is authenticated.
   * This implements part of the CAS gateway feature.
   * @see phpCAS::checkAuthentication()
   *
   * @return
   *   TRUE if we should query the CAS server to see if the user is already
   *   authenticated, FALSE otherwise.
   */
  private function allow_check_for_login() {
    if ($this->configFactory->get('cas.settings')->get('check_frequency') == -2) {
      // The user has disabled the feature.
      return FALSE;
    }
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
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
        if (stripos($_SERVER['HTTP_USER_AGENT'], $c) !== FALSE) {
          return FALSE;
        }
      }
    }

    // Do not force login for XMLRPC, Cron, or Drush.
    if (stristr($_SERVER['SCRIPT_FILENAME'], 'xmlrpc.php')) {
      return FALSE;
    }
    if (stristr($_SERVER['SCRIPT_FILENAME'], 'cron.php')) {
      return FALSE;
    }
    if (stristr($_SERVER['SCRIPT_FILENAME'], 'drush')) {
      return FALSE;
    }
    if (!empty($_SERVER['argv'][0]) && stristr($_SERVER['argv'][0], 'drush')) {
      return FALSE;
    }

    // Test against exclude pages.
    if ($pages = $this->configFactory->get('cas.settings')->get('exclude')) {
      $path = $this->aliasManager->getPathAlias($_GET['q']);
      if (drupal_match_path($path, $pages)) {
        return FALSE;
      }
    }
    return TRUE;
  }


  /**
   * Determine if we should require the user be authenticated.
   *
   * @return
   *   TRUE if we should require the user be authenticated, FALSE otherwise.
   */
  private function force_login() {
    // The 'cas' page is a shortcut to force authentication.
    if (arg(0) == 'cas') {
      return TRUE;
    }

    // Do not force login for XMLRPC, Cron, or Drush.
    if (stristr($_SERVER['SCRIPT_FILENAME'], 'xmlrpc.php')) {
      return FALSE;
    }
    if (stristr($_SERVER['SCRIPT_FILENAME'], 'cron.php')) {
      return FALSE;
    }
    if (function_exists('drush_verify_cli') && drush_verify_cli()) {
      return FALSE;
    }

    // Excluded pages do not need login.
    $pages = $this->configFactory->get('cas.settings')->get('exclude');
    $path = $this->aliasManager->getPathAlias($_GET['q']);
    if (drupal_match_path($path, $pages)) {
      return FALSE;
    }

    // Set the default behavior.
    $force_login = $this->configFactory->get('cas.settings')->get('access');

    // If we match the specified paths, reverse the behavior.
    if ($pages = $this->configFactory->get('cas.settings')->get('pages')) {
      $path = $this->aliasManager->getPathAlias($_GET['q']);
      if (drupal_match_path($path, $pages)) {
        $force_login = !$force_login;
      }
    }
    return $force_login;
  }
}
?>
