<?php

namespace Drupal\popup_message\EventSubscriber;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\PathMatcher;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provide subscriber event for popup_message.
 *
 * @package Drupal\popup_message\EventSubscriber
 */
class PopupMessageSubscriber implements EventSubscriberInterface {

  /**
   * The PopupMessage config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Current Request.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $requestStack;

  /**
   * Path matcher services.
   *
   * @var \Drupal\Core\Path\PathMatcher
   */
  protected $pathMatcher;

  /**
   * User account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * PopupMessageSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Popup_message config.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Current Request.
   * @param \Drupal\Core\Path\PathMatcher $pathMatcher
   *   Path matcher services.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   */
  public function __construct(ConfigFactoryInterface $config, RequestStack $requestStack, PathMatcher $pathMatcher, AccountInterface $account, ModuleHandlerInterface $moduleHandler) {
    $this->config = $config->get('popup_message.settings');
    $this->requestStack = $requestStack->getCurrentRequest();
    $this->pathMatcher = $pathMatcher;
    $this->account = $account;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Init PopupMessage.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   PopupMessage event.
   */
  public function showPopupMessage(FilterResponseEvent $event) {
    // Check permissions to display message.
    $response = $event->getResponse();

    if (!$response instanceof AttachmentsInterface) {
      return;
    }

    // Check module has enable popup.
    $status = $this->config->get('enable');

    // Omit system path.
    $current_url = $this->requestStack->getRequestUri();
    $decline_system_path = ('/editor/*');
    $system_path = $this->pathMatcher->matchPath($current_url, $decline_system_path);

    // Check module has enable popup, permission, exclude denied url.
    // Set session with true or false.
    // If all requirements are ok session PopupMessageStatus is set to true.
    if ($status && !$system_path) {
      $permission = $this->account->hasPermission('display popup message');

      // Get status: enabled/disabled.
      // Allow other modules to modify permissions.
      $this->moduleHandler->alter('popup_message_permission', $permission);

      $message_title = Xss::filter($this->config->get('title'));
      $message_body_variable = $this->config->get('body');
      $message_body = check_markup(
        $message_body_variable['value'],
        ($message_body_variable['format'] ?? filter_default_format()),
        FALSE
      );

      $popup_message_parameters = [
        'title' => $message_title,
        'body' => $message_body,
        'check_cookie' => $this->config->get('check_cookie') ?? 0,
        'expire' => $this->config->get('expire') ?? 0,
        'width' => $this->config->get('width') ?? POPUP_MESSAGE_DEFAULT_WIDTH,
        'height' => $this->config->get('height') ?? POPUP_MESSAGE_DEFAULT_HEIGHT,
        'delay' => $this->config->get('delay') ?? 0,
        'close_delay' => $this->config->get('close_delay') ?? 0,
        'cover_opacity' => $this->config->get('cover_opacity') ?? 70,
      ];

      // Allow other modules to modify message parameters.
      $this->moduleHandler->alter('popup_message_parameters', $popup_message_parameters);

      if ($popup_message_parameters['title'] && $popup_message_parameters['body']) {
        $attachments = $response->getAttachments();
        $attachments['library'][] = 'popup_message/popup_message_style';
        $attachments['drupalSettings']['popupMessage'] = $popup_message_parameters;
        $response->setAttachments($attachments);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['showPopupMessage', 20];

    return $events;
  }

}
