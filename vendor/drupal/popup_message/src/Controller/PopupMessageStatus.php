<?php

namespace Drupal\popup_message\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Path\PathMatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class PopupMessageStatus check popup status.
 *
 * @package Drupal\popup_message\Helper
 */
class PopupMessageStatus extends ControllerBase {

  /**
   * Provides a path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcher
   */
  protected $pathMatcher;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * PopupMessageStatus constructor.
   *
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   Path matcher services.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(PathMatcherInterface $path_matcher, ConfigFactoryInterface $config_factory) {
    $this->pathMatcher = $path_matcher;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.matcher'),
      $container->get('config.factory')
    );
  }

  /**
   * Check and send popup status to js.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Popup status in json.
   */
  public function check(Request $request) {
    $config = $this->configFactory->get('popup_message.settings');
    // Get popup message visibility settings.
    $visibility = $config->get('visibility') ? $config->get('visibility') : 0;

    // Get popup message visibility pages settings.
    $visibility_pages = $config->get('visibility_pages') ? $config->get('visibility_pages') : '';

    // Predefine value.
    $page_match = TRUE;

    // Limited visibility popup message must list at least one page.
    $status = TRUE;
    if ($visibility == 1 && empty($visibility_pages)) {
      $status = FALSE;
    }

    // Match path if necessary.
    if ($visibility_pages && $status) {
      // Convert path to lowercase. This allows comparison of the same path
      // with different case. Ex: /Page, /page, /PAGE.
      $real_path = $request->get('popup_path');
      if ($real_path == '/') {
        $real_path = $this->configFactory
          ->get('system.site')
          ->get('page.front');
      }
      else {
        $real_path = substr($real_path, 1);
      }
      $pages = mb_strtolower($visibility_pages);

      if ($visibility < 2) {
        // Convert the Drupal path to lowercase.
        $path = mb_strtolower($real_path);
        // Compare the lowercase internal and lowercase path alias (if any).
        $page_match = $this->pathMatcher->matchPath($path, $pages);
        $page_match = !($visibility xor $page_match);
      }
      else {
        $page_match = FALSE;
      }
    }

    $show_popup = (int) $page_match;

    $response = new Response();
    $response->setContent(json_encode(['status' => $show_popup]));
    $response->headers->set('Content-Type', 'application/json');

    return $response;
  }

}
