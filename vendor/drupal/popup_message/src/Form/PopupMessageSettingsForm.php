<?php

namespace Drupal\popup_message\Form;

define('POPUP_MESSAGE_CSS_NAME', 'popup.css');

use Drupal\Core\Asset\CssCollectionOptimizer;
use Drupal\Core\Asset\JsCollectionOptimizer;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;

/**
 * Provide form for settings popup_message module.
 *
 * @package Drupal\popup_message\Form
 */
class PopupMessageSettingsForm extends ConfigFormBase {

  /**
   * CssCollectionOptimizer service.
   *
   * @var \Drupal\Core\Asset\CssCollectionOptimizer
   */
  protected $cssOptimizer;

  /**
   * JsCollectionOptimizer service.
   *
   * @var \Drupal\Core\Asset\JsCollectionOptimizer
   */
  protected $jsOptimizer;

  /**
   * Drupal\Core\Entity\EntityRepositoryInterface service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * PopupMessageSettingsForm constructor.
   *
   * @param \Drupal\Core\Asset\CssCollectionOptimizer $cssOptimizer
   *   Load service css collection optimizer.
   * @param \Drupal\Core\Asset\JsCollectionOptimizer $jsOptimizer
   *   Load service js collection optimizer.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   Entity repository.
   */
  public function __construct(CssCollectionOptimizer $cssOptimizer, JsCollectionOptimizer $jsOptimizer, EntityRepositoryInterface $entityRepository) {
    $this->cssOptimizer = $cssOptimizer;
    $this->jsOptimizer = $jsOptimizer;
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('asset.css.collection_optimizer'),
      $container->get('asset.js.collection_optimizer'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'popup_message_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('popup_message.settings');

    $form['popup_message_enable'] = [
      '#type' => 'radios',
      '#title' => $this->t('Enable Popup'),
      '#default_value' => $config->get('enable') ? $config->get('enable') : 0,
      '#options' => [
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ],
    ];

    $form['popup_message_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Popup message settings'),
      '#collapsed' => FALSE,
      '#collapsible' => TRUE,
    ];

    $form['popup_message_fieldset']['popup_message_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message title'),
      '#required' => TRUE,
      '#default_value' => $config->get('title'),
    ];

    $popup_message_body = $config->get('body');

    $form['popup_message_fieldset']['popup_message_body'] = [
      '#type' => 'text_format',
      '#base_type' => 'textarea',
      '#title' => $this->t('Message body'),
      '#default_value' => $popup_message_body['value'] ?? NULL,
      '#format' => $popup_message_body['format'] ?? NULL,
    ];

    $form['popup_message_fieldset']['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#expanded' => FALSE,
    ];

    $form['popup_message_fieldset']['cookie_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Cookie settings'),
      '#expanded' => FALSE,
    ];

    $form['popup_message_fieldset']['settings']['popup_message_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Window width'),
      '#required' => TRUE,
      '#default_value' => $config->get('width') ?? POPUP_MESSAGE_DEFAULT_WIDTH,
    ];

    $form['popup_message_fieldset']['settings']['popup_message_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Window height'),
      '#required' => TRUE,
      '#default_value' => $config->get('height') ?? POPUP_MESSAGE_DEFAULT_HEIGHT,
    ];

    $form['popup_message_fieldset']['cookie_settings']['popup_message_check_cookie'] = [
      '#type' => 'radios',
      '#title' => $this->t('Check cookie'),
      '#description' => $this->t('If enabled message will be displayed only once per browser session'),
      '#default_value' => $config->get('check_cookie') ? $config->get('check_cookie') : 0,
      '#options' => [
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ],
    ];

    $form['popup_message_fieldset']['cookie_settings']['popup_message_expire'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie lifetime in days'),
      '#description' => $this->t('Define lifetime of the cookie in days. Message will not reappear until the expiration time is exceeded. If 0, popup will reappear if browser has been closed.'),
      '#default_value' => $config->get('expire') ? $config->get('expire') : 0,
    ];

    $form['popup_message_fieldset']['settings']['popup_message_delay'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delay'),
      '#description' => $this->t('Message will show after this number of seconds. Set to 0 to show instantly.'),
      '#default_value' => $config->get('delay') ? $config->get('delay') : 0,
    ];

    $form['popup_message_fieldset']['settings']['popup_message_close_delay'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delay before auto close'),
      '#description' => $this->t('Message will close after this number of seconds. Set to 0 to disable it.'),
      '#default_value' => $config->get('close_delay') ? $config->get('close_delay') : 0,
    ];

    // Styles.
    // Find styles in module directory.
    $directory = drupal_get_path('module', 'popup_message') . '/styles';
    $subdirectories = scandir($directory);
    $styles = [];

    foreach ($subdirectories as $subdir) {
      if (is_dir($directory . '/' . $subdir)) {
        if (file_exists($directory . '/' . $subdir . '/' . POPUP_MESSAGE_CSS_NAME)) {
          $lib_path = $subdir . '/' . POPUP_MESSAGE_CSS_NAME;
          $styles[$lib_path] = $subdir;
        }
      }
    }

    $form['popup_message_fieldset']['settings']['popup_message_cover_opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Background opacity (%)'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 5,
      '#default_value' => $config->get('cover_opacity') ?? 70,
      '#description' => $this->t('Allows to set a custom background opacity value in percentage (0-100%).'),
    ];

    $form['popup_message_fieldset']['settings']['popup_message_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Popup style'),
      '#default_value' => empty($config->get('style')) ? 0 : $config->get('style'),
      '#options' => $styles,
      '#description' => $this->t('To add custom styles create directory and file "modules/popup_message/popup_message_styles/custom_style/popup.css" and set in this file custom CSS code.'),
    ];

    $form['popup_message_fieldset']['visibility']['path'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Pages'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#group' => 'visibility',
      '#weight' => 0,
    ];

    $options = [
      $this->t('All pages except those listed'),
      $this->t('Only the listed pages'),
    ];

    $description = $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.",
      [
        '%blog' => 'blog',
        '%blog-wildcard' => 'blog/*',
        '%front' => '<front>',
      ]
    );

    $title = $this->t('Pages');

    $form['popup_message_fieldset']['visibility']['path']['popup_message_visibility'] = [
      '#type' => 'radios',
      '#title' => $this->t('Show block on specific pages'),
      '#options' => $options,
      '#default_value' => $config->get('visibility') ? $config->get('visibility') : 0,
    ];

    $form['popup_message_fieldset']['visibility']['path']['popup_message_visibility_pages'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('visibility_pages') ? $config->get('visibility_pages') : '',
      '#description' => $description,
      '#title' => '<span class="element-invisible">' . $title . '</span>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('popup_message.settings');
    $flush_cache = !(($config->get('style') == $form_state->getValue(
      'popup_message_enable'
    )));
    $flush_cache_css = !(($config->get('style') == $form_state->getValue(
      'popup_message_style'
    )));
    $flush_cache_js = !(($config->get('style') == $form_state->getValue(
        'popup_message_check_cookie'
    )));

    $text = $form_state->getValue('popup_message_body')['value'];
    $uuids = $this->extractFilesUuid($text);
    $this->recordFileUsage($uuids);

    $config->set('enable', $form_state->getValue('popup_message_enable'))
      ->set('title', $form_state->getValue('popup_message_title'))
      ->set('body', $form_state->getValue('popup_message_body'))
      ->set('height', $form_state->getValue('popup_message_height'))
      ->set('width', $form_state->getValue('popup_message_width'))
      ->set('check_cookie', $form_state->getValue('popup_message_check_cookie'))
      ->set('expire', $form_state->getValue('popup_message_expire'))
      ->set('delay', $form_state->getValue('popup_message_delay'))
      ->set('close_delay', $form_state->getValue('popup_message_close_delay'))
      ->set('cover_opacity', $form_state->getValue('popup_message_cover_opacity'))
      ->set('style', $form_state->getValue('popup_message_style'))
      ->set('visibility', $form_state->getValue('popup_message_visibility'))
      ->set('visibility_pages', $form_state->getValue('popup_message_visibility_pages'))
      ->save();

    if ($flush_cache) {
      drupal_flush_all_caches();
    }

    if ($flush_cache_css) {
      $this->cssOptimizer->deleteAll();
    }
    if ($flush_cache_js) {
      $this->jsOptimizer->deleteAll();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'popup_message.settings',
    ];
  }

  /**
   * Parse an HTML snippet for any linked file with data-entity-uuid attributes.
   *
   * @param string $text
   *   The partial (X)HTML snippet to load. Invalid markup will be corrected on
   *   import.
   *
   * @return array
   *   An array of all found UUIDs.
   */
  protected function extractFilesUuid($text) {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $uuids = [];
    foreach ($xpath->query('//*[@data-entity-type="file" and @data-entity-uuid]') as $file) {
      $uuids[] = $file->getAttribute('data-entity-uuid');
    }

    return $uuids;
  }

  /**
   * Records file usage of files referenced by formatted text fields.
   *
   * Every referenced file that does not yet have the FILE_STATUS_PERMANENT
   * state, will be given that state.
   *
   * @param array $uuids
   *   An array of file entity UUIDs.
   */
  protected function recordFileUsage(array $uuids) {
    try {
      foreach ($uuids as $uuid) {
        if ($file = $this->entityRepository->loadEntityByUuid('file', $uuid)) {
          if ($file->status !== FILE_STATUS_PERMANENT) {
            $file->status = FILE_STATUS_PERMANENT;
            $file->save();
          }
        }
      }
    }
    catch (EntityStorageException $exception) {
      $this->logger('popup_message')->warning($exception->getMessage());
    }
  }

}
