<?php

namespace Drupal\cas;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;

class CasClient {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Routing\UrlGeneratorInterface;
   */
  protected $urlGenerator;

  /**
   * @var bool
   */
  private $configured;

  /**
   * Constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator) {
    $this->configFactory = $config_factory;
    $this->urlGenerator = $url_generator;
    $this->configured = FALSE;
  }

  /**
   * Load and return a client instance.
   */
  public function configureClient() {
    if ($this->configured === FALSE) {
      $this->includeLibrary();
      $this->initializeLibrary();

      $this->configured = TRUE;
    }
  }

  /**
   * Load the phpCAS library.
   *
   * @return bool
   *   Whether or not the library was loaded.
   */
  private function includeLibrary() {
    // @TODO: When libraries module is stable, see if we can use it
    $path = $this->configFactory->get('cas.settings')->get('library.path');

    if (empty($path)) {
      return FALSE;
    }

    // Build the name of the file to load.
    $path = rtrim($path, '/') . '/';
    $filename = $path . 'CAS.php';

    include_once $filename;

    if (!defined('PHPCAS_VERSION') || !class_exists('phpCAS')) {
      // The file could not be loaded successfully.
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Initialize the phpCAS library.
   *
   * The library is configured based on values set by the user in the
   * CAS settings.
   */
  private function initializeLibrary() {
    $config = $this->configFactory->get('cas.settings');

    \phpCAS::client(
      $config->get('server.version'),
      $config->get('server.hostname'),
      $config->get('server.port'),
      $config->get('server.path'),
      FALSE
    );

    if ($cas_cert = $config->get('server.cert')) {
      \phpCAS::setCasServerCACert($cas_cert);
    }
    else {
      \phpCAS::setNoCasServerValidation();
    }

    \phpCAS::setFixedServiceURL($this->buildServiceUrl());
    \phpCAS::setCacheTimesForAuthRecheck($config->get('gateway.check_frequency'));

    // Prevent CAS from doing a page reload (to remove the service ticket from
    // the URL) after the ticket was validated. We will do the redirect
    // to remove that ourselves.
    \phpCAS::setNoClearTicketsFromUrl();

    // TODO: Allow other modules to alter the configuration of phpCAS here.
  }

  /**
   * Return the full service URL that the CAS server redirects to.
   *
   * TODO: We'll want to take the users current path into consideration, so they
   * can be redirected back to it after ticket validation.
   */
  private function buildServiceUrl() {
    return $this->urlGenerator->generate('cas.service', array(), TRUE);
  }
}
