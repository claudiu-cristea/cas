<?php

namespace Drupal\cas\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Drupal\Component\Utility\UrlHelper;

class CasHelper {

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param UrlGeneratorInterface $url_generator
   *   The URL generator.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator) {
    $this->configFactory = $config_factory;
    $this->urlGenerator = $url_generator;

    $this->settings = $config_factory->get('cas.settings');
  }

  /**
   * Return the login URL to the CAS server.
   *
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   * @param bool $gateway
   *   TRUE if this should be a gateway request.
   *
   * @return string
   *   The fully constructed server login URL.
   */
  public function getServerLoginUrl($service_params = array(), $gateway = FALSE) {
    $login_url = $this->getServerBaseUrl() . 'login';

    $params = array();
    if ($gateway) {
      $params['gateway'] = TRUE;
    }
    $params['service'] = $this->getCasServiceUrl($service_params);

    return $login_url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Return the validation URL used to validate the provided ticket.
   *
   * @param string $ticket
   *   The ticket to validate.
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   *
   * @return string
   *   The fully constructed validation URL.
   */
  public function getServerValidateUrl($ticket, $service_params = array()) {
    $validate_url = $this->getServerBaseUrl();
    $path = '';
    switch ($this->getCasProtocolVersion()) {
      case "1.0":
        $path = 'validate';
        break;

      case "2.0":
        $path = 'serviceValidate';
        break;
    }
    $validate_url .= $path;

    $params = array();
    $params['service'] = $this->getCasServiceUrl($service_params);
    $params['ticket'] = $ticket;

    return $validate_url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Return the version of the CAS server protocol.
   *
   * @return mixed|null
   *   The version.
   */
  public function getCasProtocolVersion() {
    return $this->settings->get('server.version');
  }

  /**
   * Return the service URL.
   *
   * @param array $service_params
   *   An array of query string parameters to append to the service URL.
   *
   * @return string
   *   The fully constructed service URL to use for CAS server.
   */
  private function getCasServiceUrl($service_params = array()) {
    return $this->urlGenerator->generate('cas.validate', $service_params, TRUE);
  }

  /**
   * Construct the base URL to the CAS server.
   *
   * @return string
   *   The base URL.
   */
  private function getServerBaseUrl() {
    // TODO, make sure we always end with a slash.
    $server_path = $this->settings->get('server.path');
    $server_base = $this->settings->get('server.hostname');
    return sprintf('https://%s%s', $server_base, $server_path);
  }
}
