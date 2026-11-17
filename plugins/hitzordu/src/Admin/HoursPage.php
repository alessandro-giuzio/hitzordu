<?php
/**
 * Admin screen: weekly working hours.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin\Admin;

class HoursPage
{

  /**
   * Slug of the working hours page.
   */
  const SLUG = 'hitzordu-hours';

  /**
   * Form action name, used for admin-post.php and the nonce.
   */
  const ACTION = 'hitzordu_save_hours';

  /**
   * Add Working hours under the Hitzordu menu. Runs on admin_menu.
   */
  public function register_menu(): void
  {
    add_submenu_page(
      BookingsPage::SLUG,
      __('Working Hours', 'hitzordu'),
      __('Working hours', 'hitzordu'),
      'manage_options',
      self::SLUG,
      array($this, 'render')
    );
  }

  /**
   * Saved hours as weekday => [start, end], using the first range of each day.
   */
  private function current_hours(): array
  {
    global $wpdb;

    $rows = $wpdb->get_results(
      "SELECT weekday, start_time, end_time FROM {$wpdb->prefix}hitzordu_availability_rules ORDER BY weekday, start_time"
    );

    $hours = array();

    foreach ($rows as $row) {
      $day = (int) $row->weekday;

      if (!isset($hours[$day])) {
        $hours[$day] = array(substr($row->start_time, 0, 5), substr($row->end_time, 0, 5));
      }
    }

    return $hours;
  }

  /**
   * Print the hours form.
   */
  public function render(): void
  {
    global $wp_locale;

    if (!current_user_can('manage_options')) {
      return;
    }

    $hours = $this->current_hours();

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which message to show.
    $notice = isset($_GET['hitzordu_notice']) ? sanitize_key(wp_unslash($_GET['hitzordu_notice'])) : '';
    ?>
    <div class="wrap">
      <h1>
        <?php esc_html_e('Working Hours', 'hitzordu'); ?>
      </h1>

      <?php if ('saved' === $notice): ?>
        <div class="notice notice-success is-dismissible">
          <p>
            <?php esc_html_e('Working hours saved.', 'hitzordu'); ?>
          </p>
        </div>
      <?php elseif ('invalid' === $notice): ?>
        <div class="notice notice-error">
          <p>
            <?php esc_html_e('Nothing was saved: each open day needs a start time earlier than its end time.', 'hitzordu'); ?>
          </p>
        </div>
      <?php endif; ?>

      <p>
        <?php
        /* translators: %s: the site's time zone, e.g. Europe/Madrid. */
        printf(esc_html__('Times are in %s. Changes do not affect bookings already made.', 'hitzordu'), esc_html(wp_timezone_string()));
        ?>
      </p>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>" />
        <?php wp_nonce_field(self::ACTION, 'hitzordu_hours_nonce'); ?>

        <table class="form-table" role="presentation">
          <tbody>
            <?php for ($day = 1; $day <= 7; $day++): ?>
              <?php
              $open = isset($hours[$day]);
              $start = $open ? $hours[$day][0] : '09:00';
              $end = $open ? $hours[$day][1] : '17:00';
              $field = 'hours[' . $day . ']';
              ?>
              <tr>
                <th scope="row">
                  <?php echo esc_html($wp_locale->get_weekday($day % 7)); ?>
                </th>
                <td>
                  <label>
                    <input type="checkbox" name="<?php echo esc_attr($field . '[open]'); ?>" value="1" <?php checked($open); ?> />
                    <?php esc_html_e('Open', 'hitzordu'); ?>
                  </label>
                  &nbsp;
                  <label>
                    <?php esc_html_e('from', 'hitzordu'); ?>
                    <input type="time" step="900" name="<?php echo esc_attr($field . '[start]'); ?>"
                      value="<?php echo esc_attr($start); ?>" />
                  </label>
                  <label>
                    <?php esc_html_e('to', 'hitzordu'); ?>
                    <input type="time" step="900" name="<?php echo esc_attr($field . '[end]'); ?>"
                      value="<?php echo esc_attr($end); ?>" />
                  </label>
                </td>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table>

        <?php submit_button(__('Save working hours', 'hitzordu')); ?>
      </form>
    </div>
    <?php
  }

  /**
   * Handle the form. Runs on admin_post_hitzordu_save_hours.
   */
  public function save(): void
  {
    global $wpdb;

    // Authority: may this user change settings?
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You are not allowed to do this.', 'hitzordu'), '', array('response' => 403));
    }

    // Intention: did this form, on this screen, really send this?
    if (
      !isset($_POST['hitzordu_hours_nonce']) ||
      !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hitzordu_hours_nonce'])), self::ACTION)
    ) {
      wp_die(esc_html__('The link you followed has expired. Please go back and try again.', 'hitzordu'), '', array('response' => 403));
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitised below.
    $input = isset($_POST['hours']) && is_array($_POST['hours']) ? wp_unslash($_POST['hours']) : array();
    $rules = array();

    for ($day = 1; $day <= 7; $day++) {
      $row = isset($input[$day]) && is_array($input[$day]) ? $input[$day] : array();

      if (empty($row['open'])) {
        continue;
      }

      $start = isset($row['start']) ? substr(sanitize_text_field($row['start']), 0, 5) : '';
      $end = isset($row['end']) ? substr(sanitize_text_field($row['end']), 0, 5) : '';

      // "HH:MM" strings compare correctly as text, so "09:00" < "17:00".
      if (!$this->is_time($start) || !$this->is_time($end) || $start >= $end) {
        $this->back('invalid');
      }

      $rules[] = array($day, $start . ':00', $end . ':00');
    }

    $table = $wpdb->prefix . 'hitzordu_availability_rules';

    // Replace all rows at once, so a failure halfway never leaves a mix of old and new.
    $wpdb->query('START TRANSACTION');
    $wpdb->query("DELETE FROM {$table}");

    foreach ($rules as $rule) {
      $wpdb->insert(
        $table,
        array(
          'weekday' => $rule[0],
          'start_time' => $rule[1],
          'end_time' => $rule[2],
        ),
        array('%d', '%s', '%s')
      );
    }

    $wpdb->query('COMMIT');

    $this->back('saved');
  }

  /**
   * A 24-hour time written as HH:MM.
   *
   * @param string $value The value to check.
   */
  private function is_time(string $value): bool
  {
    return 1 === preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
  }

  /**
   * Return to the hours page with a message, and stop.
   *
   * @param string $notice Which message to show.
   */
  private function back(string $notice): never
  {
    wp_safe_redirect(
      add_query_arg(
        array(
          'page' => self::SLUG,
          'hitzordu_notice' => $notice,
        ),
        admin_url('admin.php')
      )
    );
    exit;
  }
}
