<?php

namespace Drupal\cas_user_interaction_test;

use Drupal\cas\Event\CasPreUserLoadRedirectEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class CasTestSubscriber.
 */
class CasUserInteractionTestSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CasHelper::EVENT_PRE_USER_LOAD_REDIRECT => 'onPreUserLoadRedirect',
    ];
  }

  /**
   * Redirects to a form that asks user to accept the site's 'Legal Notice'.
   *
   * @param \Drupal\cas\Event\CasPreUserLoadRedirectEvent $event
   *   The event.
   */
  public function onPreUserLoadRedirect(CasPreUserLoadRedirectEvent $event) {
    $legal_notice_changed = \Drupal::state()->get('cas_user_interaction_test.changed', FALSE);
    $local_account = \Drupal::service('externalauth.externalauth')->load($event->getCasPropertyBag()->getUsername(), 'cas');
    // Add a redirect only if a local account exists (i.e. it's login operation)
    // and the site's 'Legal Notice' has changed.
    if ($local_account && $legal_notice_changed) {
      $event->setRedirectResponse(new RedirectResponse(Url::fromRoute('cas_user_interaction_test.form')->toString()));
    }
  }

}
