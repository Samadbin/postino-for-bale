<?php
/**
 * Plugin Name: Postino for Bale
 * Description: نوشته‌های وردپرس را با متن دلخواه و تصویر شاخص، از طریق صف زمان‌بندی‌شده در کانال بله منتشر کنید. 
 * Version: 1.0.2
 * Author: Omid Samadbin
 * Plugin URI:  https://titrano.ir
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: postino-for-bale
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

final class BWAP_Bale_WP_Auto_Publisher {
    const OPTION = 'bwap_settings';
    const QUEUE_OPTION = 'bwap_queue';
    const LAST_ERROR_OPTION = 'bwap_last_error';
    const SENT_META = '_bwap_bale_sent_at';
    const CRON_HOOK = 'bwap_process_queue';

    public function __construct() {
        add_filter('cron_schedules', [$this, 'add_cron_interval']);
        add_action('transition_post_status', [$this, 'on_post_published'], 10, 3);
        add_action(self::CRON_HOOK, [$this, 'process_queue']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'render_admin_notice']);
        add_action('admin_post_bwap_test_connection', [$this, 'handle_test_connection']);
        add_action('admin_post_bwap_queue_existing', [$this, 'handle_queue_existing']);
        add_action('admin_post_bwap_clear_queue', [$this, 'handle_clear_queue']);
    }

    public static function activate() {
        if (get_option(self::OPTION) === false) {
            add_option(self::OPTION, [
                'token' => '',
                'channel' => '',
                'delay' => 60,
                'include_image' => 1,
                'template' => "✅ {post_title}\n\n{post_excerpt}\n{categories}\n\n🔵 لینک کوتاه:\n{short_link}",
            ]);
        }
    }

    public function add_cron_interval($schedules) {
        $schedules['bwap_every_minute'] = [
            'interval' => 60,
            'display' => 'هر دقیقه (انتشار بله)',
        ];
        return $schedules;
    }

    private function settings() {
        $defaults = [
            'token' => '',
            'channel' => '',
            'delay' => 60,
            'include_image' => 1,
            'template' => "✅ {post_title}\n\n{post_excerpt}\n{categories}\n\n🔵 لینک کوتاه:\n{short_link}",
        ];
        return wp_parse_args(get_option(self::OPTION, []), $defaults);
    }

    public function register_settings() {
        register_setting('bwap_settings_group', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [],
        ]);
    }

    public function sanitize_settings($input) {
        $old = $this->settings();
        $input = is_array($input) ? $input : [];
        $token = isset($input['token']) ? trim(sanitize_text_field($input['token'])) : '';
        if ($token === '') {
            $token = $old['token'];
        }
        $channel = isset($input['channel']) ? trim(sanitize_text_field($input['channel'])) : '';
        $delay = isset($input['delay']) ? absint($input['delay']) : 60;
        $delay = max(60, min(86400, $delay));
        $template = isset($input['template']) ? sanitize_textarea_field($input['template']) : $old['template'];
        return [
            'token' => $token,
            'channel' => $channel,
            'delay' => $delay,
            'include_image' => !empty($input['include_image']) ? 1 : 0,
            'template' => $template,
        ];
    }

    public function admin_menu() {
        add_options_page(
            'Postino for Bale',
            'Postino for Bale',
            'manage_options',
            'bwap-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = $this->settings();
        $queue = get_option(self::QUEUE_OPTION, []);
        $queue_count = is_array($queue) ? count($queue) : 0;
        $last_error = get_option(self::LAST_ERROR_OPTION, '');
        ?>
        <div class="wrap" dir="rtl">
            <h1>Postino for Bale</h1>
            <p>ربات باید در کانال شما عضو و دارای دسترسی مدیر برای ارسال پیام باشد. توکن را محرمانه نگه دارید.</p>

            <form method="post" action="options.php">
                <?php settings_fields('bwap_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bwap-token">توکن ربات بله</label></th>
                        <td>
                            <input id="bwap-token" type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION); ?>[token]" value="" placeholder="<?php echo $s['token'] ? 'توکن ذخیره شده؛ برای حفظ آن خالی بگذارید' : 'توکن ربات را وارد کنید'; ?>" />
                            <p class="description">برای حفظ توکن فعلی، این فیلد را خالی بگذارید.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bwap-channel">شناسه کانال</label></th>
                        <td>
                            <input id="bwap-channel" type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[channel]" value="<?php echo esc_attr($s['channel']); ?>" placeholder="مثلاً @YourChannel یا شناسه عددی کانال" />
                            <p class="description">فرمت قابل قبول به تنظیمات ربات بله بستگی دارد؛ مطمئن شوید ربات اجازه ارسال در کانال را دارد.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bwap-delay">فاصله ارسال (ثانیه)</label></th>
                        <td>
                            <input id="bwap-delay" type="number" min="60" max="86400" step="1" name="<?php echo esc_attr(self::OPTION); ?>[delay]" value="<?php echo esc_attr((string) $s['delay']); ?>" />
                            <p class="description">حداقل ۶۰ ثانیه. زمان‌بندی وردپرس ممکن است با توجه به بازدید سایت کمی دیرتر اجرا شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">تصویر شاخص</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[include_image]" value="1" <?php checked($s['include_image'], 1); ?> /> ارسال تصویر شاخص همراه مطلب</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bwap-template">قالب پیام</label></th>
                        <td>
                            <textarea id="bwap-template" class="large-text code" rows="8" name="<?php echo esc_attr(self::OPTION); ?>[template]"><?php echo esc_textarea($s['template']); ?></textarea>
                            <p class="description">متغیرها: <code>{post_title}</code>، <code>{post_excerpt}</code>، <code>{categories}</code>، <code>{short_link}</code>، <code>{post_url}</code>، <code>{site_name}</code>، <code>{date}</code></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <hr />
            <h2>ابزارها</h2>
            <p><strong>مطالب در صف ارسال:</strong> <?php echo esc_html((string) $queue_count); ?></p>
            <?php if ($last_error) : ?>
                <div class="notice notice-error inline"><p><strong>آخرین خطا:</strong> <?php echo esc_html($last_error); ?></p></div>
            <?php endif; ?>
            <p>ابتدا تنظیمات را ذخیره کنید، سپس اتصال را آزمایش کنید. برای ارسال مطالب قبلی، آن‌ها به ترتیب قدیمی‌تر به جدیدتر وارد صف می‌شوند.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="bwap_test_connection" />
                    <?php wp_nonce_field('bwap_test_connection'); ?>
                    <?php submit_button('ارسال پیام آزمایشی', 'secondary', 'submit', false); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="bwap_queue_existing" />
                    <?php wp_nonce_field('bwap_queue_existing'); ?>
                    <?php submit_button('افزودن مطالب قبلی به صف', 'primary', 'submit', false); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('صف ارسال پاک شود؟ مطالب ارسال‌شده پاک نمی‌شوند.');">
                    <input type="hidden" name="action" value="bwap_clear_queue" />
                    <?php wp_nonce_field('bwap_clear_queue'); ?>
                    <?php submit_button('پاک کردن صف', 'delete', 'submit', false); ?>
                </form>
            </div>
            <p class="description">نکته: اجرای زمان‌بندی وردپرس با WP-Cron انجام می‌شود؛ در سایت‌های کم‌بازدید ممکن است ارسال دقیقاً در زمان تعیین‌شده انجام نشود.</p>
            <p class="description"> </p>
            <p class="description">برنامه‌نویس: Omid Samadbin</p>
            <p class="description">تماس: <a href="mailto:baleplugin@titrano.ir">baleplugin@titrano.ir</a></p>
        </div>
        <?php
    }

    public function handle_test_connection() {
        $this->authorize_admin_action('bwap_test_connection');
        $s = $this->settings();
        if (empty($s['token']) || empty($s['channel'])) {
            $this->redirect_with_notice('لطفاً ابتدا توکن ربات و شناسه کانال را در تنظیمات وارد و ذخیره کنید.', 'error');
        }
        $result = $this->api_request('sendMessage', [
            'chat_id' => $s['channel'],
            'text' => '✅ پیام آزمایشی از سایت «' . get_bloginfo('name') . '»',
        ]);
        if (is_wp_error($result)) {
            update_option(self::LAST_ERROR_OPTION, $result->get_error_message(), false);
            $this->redirect_with_notice('ارسال پیام آزمایشی ناموفق بود: ' . $result->get_error_message(), 'error');
        }
        delete_option(self::LAST_ERROR_OPTION);
        $this->redirect_with_notice('پیام آزمایشی ارسال شد. کانال را بررسی کنید.', 'success');
    }

    public function handle_queue_existing() {
        $this->authorize_admin_action('bwap_queue_existing');
        $ids = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        $added = 0;
        foreach ($ids as $post_id) {
            if ($this->enqueue_post((int) $post_id)) {
                $added++;
            }
        }
        $this->redirect_with_notice(sprintf('%d مطلب جدید به صف ارسال اضافه شد.', $added), 'success');
    }

    public function handle_clear_queue() {
        $this->authorize_admin_action('bwap_clear_queue');
        update_option(self::QUEUE_OPTION, [], false);
        wp_clear_scheduled_hook(self::CRON_HOOK);
        $this->redirect_with_notice('صف ارسال پاک شد.', 'success');
    }

    private function authorize_admin_action($nonce_action) {
        if (!current_user_can('manage_options')) {
            wp_die('اجازه دسترسی ندارید.');
        }
        check_admin_referer($nonce_action);
    }

    private function redirect_with_notice($message, $type = 'success') {
        set_transient('bwap_admin_notice_' . get_current_user_id(), [
            'message' => $message,
            'type' => $type,
        ], 60);
        wp_safe_redirect(admin_url('options-general.php?page=bwap-settings'));
        exit;
    }


    public function render_admin_notice() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        $key = 'bwap_admin_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (!$notice || !is_array($notice)) {
            return;
        }
        delete_transient($key);
        $class = isset($notice['type']) && $notice['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';
        $message = isset($notice['message']) ? $notice['message'] : '';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }

    public function on_post_published($new_status, $old_status, $post) {
        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return;
        }
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->enqueue_post((int) $post->ID);
        }
    }

    private function ensure_worker() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'bwap_every_minute', self::CRON_HOOK);
        }
    }

    private function enqueue_post($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
            return false;
        }
        if (get_post_meta($post_id, self::SENT_META, true)) {
            return false;
        }
        $queue = get_option(self::QUEUE_OPTION, []);
        $queue = is_array($queue) ? $queue : [];
        foreach ($queue as $item) {
            if (isset($item['post_id']) && (int) $item['post_id'] === (int) $post_id) {
                return false;
            }
        }
        $s = $this->settings();
        $delay = max(60, (int) $s['delay']);
        $now = time();
        if ($queue) {
            $last = end($queue);
            $due_at = max($now + $delay, (int) ($last['due_at'] ?? $now) + $delay);
        } else {
            $due_at = $now + $delay;
        }
        $queue[] = [
            'post_id' => (int) $post_id,
            'due_at' => $due_at,
            'attempts' => 0,
        ];
        update_option(self::QUEUE_OPTION, $queue, false);
        $this->ensure_worker();
        return true;
    }

    public function process_queue() {
        $queue = get_option(self::QUEUE_OPTION, []);
        $queue = is_array($queue) ? array_values($queue) : [];
        if (!$queue) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            return;
        }
        $settings = $this->settings();
        if (empty($settings['token']) || empty($settings['channel'])) {
            update_option(self::LAST_ERROR_OPTION, 'برای شروع انتشار، توکن ربات و شناسه کانال را در تنظیمات وارد کنید.', false);
            $this->ensure_worker();
            return;
        }
        $first = $queue[0];
        if ((int) ($first['due_at'] ?? 0) > time()) {
            return;
        }
        array_shift($queue);
        update_option(self::QUEUE_OPTION, $queue, false);

        $post_id = (int) ($first['post_id'] ?? 0);
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || get_post_meta($post_id, self::SENT_META, true)) {
            $this->finish_queue_cycle();
            return;
        }

        $result = $this->send_post($post);
        if (is_wp_error($result)) {
            $attempts = (int) ($first['attempts'] ?? 0) + 1;
            update_option(self::LAST_ERROR_OPTION, 'مطلب #' . $post_id . ': ' . $result->get_error_message(), false);
            if ($attempts < 3) {
                $s = $this->settings();
                $delay = max(60, (int) $s['delay']);
                $retry = [
                    'post_id' => $post_id,
                    'due_at' => time() + $delay,
                    'attempts' => $attempts,
                ];
                array_unshift($queue, $retry);
                $previous_due = $retry['due_at'];
                foreach ($queue as $index => &$item) {
                    if ($index === 0) {
                        continue;
                    }
                    $minimum_due = $previous_due + $delay;
                    if ((int) ($item['due_at'] ?? 0) < $minimum_due) {
                        $item['due_at'] = $minimum_due;
                    }
                    $previous_due = (int) $item['due_at'];
                }
                unset($item);
                update_option(self::QUEUE_OPTION, $queue, false);
            }
        } else {
            update_post_meta($post_id, self::SENT_META, current_time('mysql'));
            delete_option(self::LAST_ERROR_OPTION);
        }
        $this->finish_queue_cycle();
    }

    private function finish_queue_cycle() {
        $queue = get_option(self::QUEUE_OPTION, []);
        if (is_array($queue) && count($queue) > 0) {
            $this->ensure_worker();
        } else {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    private function send_post($post) {
        $s = $this->settings();
        if (empty($s['token']) || empty($s['channel'])) {
            return new WP_Error('bwap_missing_settings', 'توکن ربات یا شناسه کانال تنظیم نشده است.');
        }
        $text = $this->render_template($post, $s['template']);
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => 'مشاهده مطلب', 'url' => get_permalink($post)],
            ]],
        ];
        $image_url = '';
        if (!empty($s['include_image'])) {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            if ($thumbnail_id) {
                $image_url = (string) wp_get_attachment_image_url($thumbnail_id, 'large');
            }
        }

        if ($image_url && $this->text_length($text) <= 1000) {
            $result = $this->api_request('sendPhoto', [
                'chat_id' => $s['channel'],
                'photo' => $image_url,
                'caption' => $text,
                'reply_markup' => wp_json_encode($keyboard),
            ]);
            if (!is_wp_error($result)) {
                return true;
            }
            // If sending the image fails, fall back to sending the text and keep the post from being lost.
        } elseif ($image_url) {
            $image_result = $this->api_request('sendPhoto', [
                'chat_id' => $s['channel'],
                'photo' => $image_url,
            ]);
            if (is_wp_error($image_result)) {
                // Fall back to text-only delivery below.
            }
        }

        $result = $this->api_request('sendMessage', [
            'chat_id' => $s['channel'],
            'text' => $this->truncate_text($text, 4000),
            'reply_markup' => wp_json_encode($keyboard),
        ]);
        return is_wp_error($result) ? $result : true;
    }

    private function render_template($post, $template) {
        $title = get_the_title($post);
        $excerpt = trim(wp_strip_all_tags(get_the_excerpt($post)));
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 45, '…');
        }
        $categories = get_the_category($post->ID);
        $hashtags = [];
        foreach ($categories as $category) {
            $name = trim($category->name);
            $tag = preg_replace('/[^\p{L}\p{N}_]+/u', '_', $name);
            $tag = trim((string) $tag, '_');
            if ($tag !== '') {
                $hashtags[] = '#' . $tag;
            }
        }
        $short_link = wp_get_shortlink($post->ID);
        if (!$short_link) {
            $short_link = get_permalink($post);
        }
        $replacements = [
            '{post_title}' => $title,
            '{post_excerpt}' => $excerpt,
            '{categories}' => implode(' ', array_unique($hashtags)),
            '{short_link}' => $short_link,
            '{post_url}' => get_permalink($post),
            '{site_name}' => get_bloginfo('name'),
            '{date}' => get_the_date('', $post),
        ];
        $text = strtr((string) $template, $replacements);
        return trim(wp_strip_all_tags($text));
    }

    private function text_length($text) {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    private function truncate_text($text, $limit) {
        if ($this->text_length($text) <= $limit) {
            return $text;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit - 1, 'UTF-8') . '…';
        }
        return substr($text, 0, $limit - 3) . '...';
    }

    private function api_request($method, $payload) {
        $s = $this->settings();
        if (empty($s['token'])) {
            return new WP_Error('bwap_missing_token', 'توکن ربات بله وارد نشده است.');
        }
        $url = 'https://tapi.bale.ai/bot' . rawurlencode($s['token']) . '/' . rawurlencode($method);
        $response = wp_remote_post($url, [
            'timeout' => 25,
            'headers' => ['Accept' => 'application/json'],
            'body' => $payload,
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('bwap_http_error', 'خطا در ارتباط با API بله: ' . $response->get_error_message());
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['ok'])) {
            $description = is_array($data) && !empty($data['description']) ? sanitize_text_field($data['description']) : 'پاسخ نامعتبر از API بله (HTTP ' . $status . ').';
            return new WP_Error('bwap_api_error', $description);
        }
        return $data;
    }
}

register_activation_hook(__FILE__, ['BWAP_Bale_WP_Auto_Publisher', 'activate']);
new BWAP_Bale_WP_Auto_Publisher();
