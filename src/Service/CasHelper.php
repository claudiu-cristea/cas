<?php

namespace Drupal\cas\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * Class CasHelper.
 */
class CasHelper {

  /**
   * SSL configuration to use the system's CA bundle to verify CAS server.
   *
   * @var int
   */
  const CA_DEFAULT = 0;

  /**
   * SSL configuration to use provided file to verify CAS server.
   *
   * @var int
   */
  const CA_CUSTOM = 1;

  /**
   * SSL Configuration to not verify CAS server.
   *
   * @var int
   */
  const CA_NONE = 2;

  /**
   * Gateway config: never check preemptively to see if the user is logged in.
   *
   * @var int
   */
  const CHECK_NEVER = -2;

  /**
   * Gateway config: check once per session to see if the user is logged in.
   *
   * @var int
   */
  const CHECK_ONCE = -1;

  /**
   * Gateway config: check on every page load to see if the user is logged in.
   *
   * @var int
   */
  const CHECK_ALWAYS = 0;

  /**
   * Event type identifier for the CasPreUserLoadEvent.
   *
   * @var string
   */
  const EVENT_PRE_USER_LOAD = 'cas.pre_user_load';

  /**
   * Event type identifier for the CasPreRegisterEvent.
   *
   * @var string
   */
  const EVENT_PRE_REGISTER = 'cas.pre_register';

  /**
   * Event type identifier for the CasPreLoginEvent.
   *
   * @var string
   */
  const EVENT_PRE_LOGIN = 'cas.pre_login';

  /**
   * Event type identifier for pre auth events.
   *
   * @var string
   */
  const EVENT_PRE_REDIRECT = 'cas.pre_redirect';

  /**
   * Stores settings object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * Stores logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $loggerChannel;

  /**
   * Constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->settings = $config_factory->get('cas.settings');
    $this->loggerChannel = $logger_factory->get('cas');
  }

  /**
   * Construct the base URL to the CAS server.
   *
   * @return string
   *   The base URL.
   */
  public function getServerBaseUrl() {
    $url = 'https://' . $this->settings->get('server.hostname');
    $port = $this->settings->get('server.port');
    if (!empty($port) && $port != 443) {
      $url .= ':' . $this->settings->get('server.port');
    }
    $url .= $this->settings->get('server.path');
    $url = rtrim($url, '/') . '/';

    return $url;
  }

  /**
   * Log information to the logger.
   *
   * Only log supplied information to the logger if module is configured to do
   * so, otherwise do nothing.
   *
   * @param string $message
   *   The message to log.
   */
  public function log($message) {
    if ($this->settings->get('advanced.debug_log') == TRUE) {
      $this->loggerChannel->log(RfcLogLevel::DEBUG, $message);
    }
  }

}
