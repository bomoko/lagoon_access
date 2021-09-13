<?php

namespace Drupal\ops_if\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Drupal\Core\Session\AccountProxy;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\ops_if\EventSubscriber
 */
class OpsIfEventSubscriber implements EventSubscriberInterface {

  const OPS_TRIGGER_ROUTES = ['user.page', 'user.login'];

  /**
   * The account proxy service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;


  /**
   * Constructs a new ProtectedPagesSubscriber.
   *
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The account proxy service.
   */
  public function __construct(AccountProxy $currentUser) {
    $this->currentUser = $currentUser;
  }


  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'pageResponse',
    ];
  }

  /**
   * React to a page response
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Config crud event.
   */
  public function pageResponse(FilterResponseEvent $event) {
    if ($this->currentUser->hasPermission('access protected lagoon routes') &&
      in_array(\Drupal::routeMatch()
        ->getRouteName(), self::OPS_TRIGGER_ROUTES)) {

      \Drupal::messenger()->addStatus('Here we can send up the IP ...');
    }
    else {
      \Drupal::messenger()
        ->addStatus('User has not the permission or route is wrong');
    }
  }

}
