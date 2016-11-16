<?php

namespace Drupal\cas\Event;

use Drupal\cas\CasRedirectData;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class CasPreRedirectEvent.
 *
 * Allows modules to interact just before the cas module decides to do a
 * redirection for authenication check.
 */
class CasPreRedirectEvent extends Event {

  /**
   * Data used to decide on final redirection.
   *
   * @var CasRedirectData
   */
  protected $casRedirectData;

  /**
   * CasPreRedirectEvent constructor.
   *
   * @param \Drupal\cas\CasRedirectData $cas_redirect_data
   *   The redirect data object.
   */
  public function __construct(CasRedirectData $cas_redirect_data) {
    $this->casRedirectData = $cas_redirect_data;
  }

  /**
   * Getter for $casRedirectData.
   *
   * @return \Drupal\cas\CasRedirectData
   *   The redirect data object.
   */
  public function getCasRedirectData() {
    return $this->casRedirectData;
  }

}
