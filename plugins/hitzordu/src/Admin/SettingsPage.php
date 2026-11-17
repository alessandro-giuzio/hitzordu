<?php
/**
 * Admin screen: plugin settings, built with the Settings API.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin\Admin;

use Hitzordu\Plugin\Settings;

class SettingsPage
{

  /**
   * Slug of the settings page.
   */
  const SLUG = 'hitzordu-settings';

  /**
   * Add Settings under the Hitzordu menu. Runs on admin_menu.
   */
  public function register_menu(): void
  {
    add_submenu_page(
      BookingsPage::SLUG,
      __('Hitzordu Settings', 'hitzordu'),
      __('Settings', 'hitzordu'),
      'manage_options',
      self::SLUG,
      array($this, 'render')
    );
  }

  /**
   * Register the setting, its section and its fields. Runs on admin_init.
   */
  public function register_settings(): void
  {
    register_setting(
      'hitzordu_settings',
      Settings::OPTION,
      array(
        'type' => 'array',
        'sanitize_callback' => array($this, 'validate'),
        'default' => array(),
      )
    );

    add_settings_section(
      'hitzordu_general',
      __('General', 'hitzordu'),
      array($this, 'section_text'),
      self::SLUG
    );

    add_settings_field(
      'hitzordu_notification_email',
      __('Notification email', 'hitzordu'),
      array($this, 'field_notification_email'),
      self::SLUG,
      'hitzordu_general',
      array('label_for' => 'hitzordu_notification_email')
    );

    add_settings_field(
      'hitzordu_delete_data',
      __('Uninstall', 'hitzordu'),
      array($this, 'field_delete_data'),
      self::SLUG,
      'hitzordu_general'
    );
  }

  /**
   * Text shown under the section heading.
   */
  public function section_text(): void
  {
    echo '<p>' . esc_html__('How Hitzordu notifies you and what happens to its data.', 'hitzordu') . '</p>';
  }

  /**
   * The notification email field.
   */
  public function field_notification_email(): void
  {
    printf(
      '<input type="email" id="hitzordu_notification_email" name="%1$s[notification_email]" value="%2$s" class="regular-text" placeholder="%3$s" />',
      esc_attr(Settings::OPTION),
      esc_attr(Settings::all()['notification_email']),
      esc_attr(get_option('admin_email'))
    );

    echo '<p class="description">' . esc_html__('Where new booking emails go. Leave empty to use the site admin email.', 'hitzordu') . '</p>';
  }

  /**
   * The delete-on-uninstall checkbox.
   */
  public function field_delete_data(): void
  {
    printf(
      '<label><input type="checkbox" name="%1$s[delete_data]" value="1" %2$s /> %3$s</label>',
      esc_attr(Settings::OPTION),
      checked(Settings::all()['delete_data'], true, false),
      esc_html__('Delete all bookings and settings when the plugin is deleted', 'hitzordu')
    );
  }

  /**
   * Validate and clean the submitted settings before they are saved.
   *
   * @param mixed $input The submitted values.
   */
  public function validate($input): array
  {
    $input = is_array($input) ? $input : array();
    $raw = isset($input['notification_email']) ? trim((string) $input['notification_email']) : '';

    if ('' === $raw) {
      $email = '';
    } elseif (is_email($raw)) {
      $email = sanitize_email($raw);
    } else {
      add_settings_error(
        Settings::OPTION,
        'hitzordu_bad_email',
        __('The notification email is not valid, so the previous one was kept.', 'hitzordu')
      );
      $email = Settings::all()['notification_email'];
    }

    return array(
      'notification_email' => $email,
      'delete_data' => !empty($input['delete_data']),
    );
  }

  /**
   * Print the settings form.
   */
  public function render(): void
  {
    if (!current_user_can('manage_options')) {
      return;
    }
    ?>
    <div class="wrap">
      <h1>
        <?php esc_html_e('Hitzordu Settings', 'hitzordu'); ?>
      </h1>
      <?php settings_errors(); ?>
      <form action="options.php" method="post">
        <?php
        settings_fields('hitzordu_settings');
        do_settings_sections(self::SLUG);
        submit_button();
        ?>
      </form>
    </div>
    <?php
  }
}
