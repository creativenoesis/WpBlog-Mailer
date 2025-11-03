<?php
/**
 * Settings Page View
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Get current settings
$settings = get_option('wpbm_settings', []);
$defaults = [
    'from_name' => get_bloginfo('name'),
    'from_email' => get_bloginfo('admin_email'),
    'subject_line' => '[{site_name}] New Posts: {date}',
    'posts_per_email' => 5,
    'post_types' => ['post'],
    'post_content_type' => 'excerpt',
    'excerpt_length' => 40,
    'schedule_frequency' => 'weekly',
    'schedule_day' => 'monday',
    'schedule_time' => '09:00',
    'double_optin' => false,
    'unsubscribe_text' => 'If you no longer wish to receive these emails, you can {unsubscribe_link}.',
    'enable_greeting' => true,
    'greeting_text' => 'Hi {first_name},',
    'enable_site_link' => true,
    'template_primary_color' => '#667eea',
    'template_bg_color' => '#f7f7f7',
    'template_text_color' => '#333333',
    'template_link_color' => '#2271b1',
    'template_heading_font' => 'Arial, sans-serif',
    'template_body_font' => 'Georgia, serif',
];
$settings = wp_parse_args($settings, $defaults);

// Get current template selection
$selected_template = get_option('wpbm_template_type', 'basic');

// Handle form submission
if (isset($_POST['wpbm_save_settings']) && check_admin_referer('wpbm_settings_nonce', 'wpbm_settings_nonce')) {
    $new_settings = [
        'from_name' => sanitize_text_field($_POST['from_name']),
        'from_email' => sanitize_email($_POST['from_email']),
        'subject_line' => sanitize_text_field($_POST['subject_line']),
        'posts_per_email' => absint($_POST['posts_per_email']),
        'post_types' => isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : ['post'],
        'post_content_type' => isset($_POST['post_content_type']) ? sanitize_text_field($_POST['post_content_type']) : 'excerpt',
        'excerpt_length' => isset($_POST['excerpt_length']) ? absint($_POST['excerpt_length']) : 40,
        'schedule_frequency' => sanitize_text_field($_POST['schedule_frequency']),
        'schedule_day' => sanitize_text_field($_POST['schedule_day']),
        'schedule_time' => sanitize_text_field($_POST['schedule_time']),
        'double_optin' => isset($_POST['double_optin']) ? 1 : 0,
        'unsubscribe_text' => wp_kses_post($_POST['unsubscribe_text']),
        'enable_greeting' => isset($_POST['enable_greeting']) ? 1 : 0,
        'greeting_text' => sanitize_text_field($_POST['greeting_text']),
        'enable_site_link' => isset($_POST['enable_site_link']) ? 1 : 0,
    ];

    // Add template customization settings
    if (isset($_POST['template_primary_color'])) {
        $new_settings['template_primary_color'] = sanitize_hex_color($_POST['template_primary_color']);
    }
    if (isset($_POST['template_bg_color'])) {
        $new_settings['template_bg_color'] = sanitize_hex_color($_POST['template_bg_color']);
    }
    if (isset($_POST['template_text_color'])) {
        $new_settings['template_text_color'] = sanitize_hex_color($_POST['template_text_color']);
    }
    if (isset($_POST['template_link_color'])) {
        $new_settings['template_link_color'] = sanitize_hex_color($_POST['template_link_color']);
    }
    if (isset($_POST['template_heading_font'])) {
        $new_settings['template_heading_font'] = sanitize_text_field($_POST['template_heading_font']);
    }
    if (isset($_POST['template_body_font'])) {
        $new_settings['template_body_font'] = sanitize_text_field($_POST['template_body_font']);
    }

    update_option('wpbm_settings', $new_settings);
    $settings = $new_settings;

    // Save template selection
    if (isset($_POST['template_type'])) {
        update_option('wpbm_template_type', sanitize_text_field($_POST['template_type']));
        $selected_template = $_POST['template_type'];
    }

    // Reschedule cron job with new settings
    $timestamp = wp_next_scheduled('wpbm_send_newsletter');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wpbm_send_newsletter');
    }

    // Schedule new cron - Use WordPress timezone for accurate scheduling
    $timezone = wp_timezone();
    $now = new DateTime('now', $timezone);

    // Parse the schedule time (HH:MM format)
    list($hour, $minute) = explode(':', $settings['schedule_time']);

    // Calculate next scheduled time based on frequency
    if ($settings['schedule_frequency'] === 'daily') {
        // For daily: schedule for today at the specified time, or tomorrow if time has passed
        $schedule_date = new DateTime('now', $timezone);
        $schedule_date->setTime((int)$hour, (int)$minute, 0);

        // If time has already passed today, schedule for tomorrow
        if ($schedule_date <= $now) {
            $schedule_date->modify('+1 day');
        }
    } elseif ($settings['schedule_frequency'] === 'weekly') {
        // For weekly: schedule for next occurrence of the specified day
        $schedule_date = new DateTime('next ' . $settings['schedule_day'], $timezone);
        $schedule_date->setTime((int)$hour, (int)$minute, 0);

        // If that day+time is today but hasn't passed yet, use today
        $test_today = new DateTime('now', $timezone);
        $test_today->setTime((int)$hour, (int)$minute, 0);
        if ($test_today->format('l') === ucfirst($settings['schedule_day']) && $test_today > $now) {
            $schedule_date = $test_today;
        }
    } else {
        // For monthly: schedule for first day of next month
        $schedule_date = new DateTime('first day of next month', $timezone);
        $schedule_date->setTime((int)$hour, (int)$minute, 0);
    }

    $schedule_timestamp = $schedule_date->getTimestamp();
    wp_schedule_event($schedule_timestamp, $settings['schedule_frequency'], 'wpbm_send_newsletter');

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'wpblogmailer') . '</p></div>';
}

$tier = wpbm_get_plan_name();
?>

<div class="wrap wpbm-settings-page">
    <h1><?php esc_html_e('WP Blog Mailer Settings', 'wpblogmailer'); ?></h1>

    <form method="post" action="" class="wpbm-settings-form">
        <?php wp_nonce_field('wpbm_settings_nonce', 'wpbm_settings_nonce'); ?>

        <div class="wpbm-settings-tabs">
            <button type="button" class="wpbm-tab-btn active" data-tab="general"><?php esc_html_e('General', 'wpblogmailer'); ?></button>
            <button type="button" class="wpbm-tab-btn" data-tab="email"><?php esc_html_e('Email Settings', 'wpblogmailer'); ?></button>
            <button type="button" class="wpbm-tab-btn" data-tab="schedule"><?php esc_html_e('Schedule', 'wpblogmailer'); ?></button>
            <?php if (wpbm_is_starter() || wpbm_is_pro()): ?>
            <button type="button" class="wpbm-tab-btn" data-tab="advanced"><?php esc_html_e('Advanced', 'wpblogmailer'); ?></button>
            <?php endif; ?>
        </div>

        <!-- General Tab -->
        <div class="wpbm-tab-content active" id="tab-general">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="from_name"><?php esc_html_e('From Name', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="from_name"
                                   id="from_name"
                                   value="<?php echo esc_attr($settings['from_name']); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description"><?php esc_html_e('The name subscribers will see emails from', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="from_email"><?php esc_html_e('From Email', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   name="from_email"
                                   id="from_email"
                                   value="<?php echo esc_attr($settings['from_email']); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description"><?php esc_html_e('The email address emails will be sent from', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="posts_per_email"><?php esc_html_e('Posts Per Email', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   name="posts_per_email"
                                   id="posts_per_email"
                                   value="<?php echo esc_attr($settings['posts_per_email']); ?>"
                                   min="1"
                                   max="20"
                                   class="small-text">
                            <p class="description"><?php esc_html_e('Number of recent posts to include in each newsletter', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Post Types', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <?php
                            $post_types = get_post_types(['public' => true], 'objects');
                            foreach ($post_types as $post_type):
                                if ($post_type->name === 'attachment') continue;
                            ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox"
                                       name="post_types[]"
                                       value="<?php echo esc_attr($post_type->name); ?>"
                                       <?php checked(in_array($post_type->name, $settings['post_types'])); ?>>
                                <?php echo esc_html($post_type->labels->name); ?>
                            </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('Select which post types to include in newsletters', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="post_content_type"><?php esc_html_e('Post Content', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <select name="post_content_type" id="post_content_type">
                                <option value="excerpt" <?php selected($settings['post_content_type'], 'excerpt'); ?>>
                                    <?php esc_html_e('Excerpt Only', 'wpblogmailer'); ?>
                                </option>
                                <option value="full" <?php selected($settings['post_content_type'], 'full'); ?>>
                                    <?php esc_html_e('Full Post Content', 'wpblogmailer'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose whether to send excerpt or full post content in emails', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr id="excerpt_length_row" style="<?php echo ($settings['post_content_type'] === 'full') ? 'display:none;' : ''; ?>">
                        <th scope="row">
                            <label for="excerpt_length"><?php esc_html_e('Excerpt Length', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   name="excerpt_length"
                                   id="excerpt_length"
                                   value="<?php echo esc_attr($settings['excerpt_length']); ?>"
                                   min="10"
                                   max="200"
                                   class="small-text">
                            <span><?php esc_html_e('words', 'wpblogmailer'); ?></span>
                            <p class="description"><?php esc_html_e('Number of words to show in excerpt', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Email Settings Tab -->
        <div class="wpbm-tab-content" id="tab-email">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="template_type"><?php esc_html_e('Email Template', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <select name="template_type" id="template_type" class="regular-text">
                                <option value="basic" <?php selected($selected_template, 'basic'); ?>>
                                    <?php esc_html_e('Basic Newsletter Template', 'wpblogmailer'); ?>
                                </option>
                                <?php if (wpbm_is_pro()): ?>
                                    <optgroup label="<?php esc_attr_e('Professional Templates', 'wpblogmailer'); ?>">
                                        <option value="library-modern" <?php selected($selected_template, 'library-modern'); ?>>
                                            <?php esc_html_e('Modern - Clean & Contemporary', 'wpblogmailer'); ?>
                                        </option>
                                        <option value="library-classic" <?php selected($selected_template, 'library-classic'); ?>>
                                            <?php esc_html_e('Classic - Traditional Blog Style', 'wpblogmailer'); ?>
                                        </option>
                                        <option value="library-minimal" <?php selected($selected_template, 'library-minimal'); ?>>
                                            <?php esc_html_e('Minimal - Simple & Elegant', 'wpblogmailer'); ?>
                                        </option>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose the template design for your newsletter emails.', 'wpblogmailer'); ?>
                                <?php if (!wpbm_is_pro()): ?>
                                    <br>
                                    <span class="wpbm-feature-badge"><?php esc_html_e('Upgrade to Pro for more templates', 'wpblogmailer'); ?></span>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="subject_line"><?php esc_html_e('Subject Line', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="subject_line"
                                   id="subject_line"
                                   value="<?php echo esc_attr($settings['subject_line']); ?>"
                                   class="large-text"
                                   required>
                            <p class="description">
                                <?php esc_html_e('Available tags:', 'wpblogmailer'); ?>
                                <code>{site_name}</code>, <code>{date}</code>, <code>{post_count}</code>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="unsubscribe_text"><?php esc_html_e('Unsubscribe Text', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <textarea name="unsubscribe_text"
                                      id="unsubscribe_text"
                                      rows="3"
                                      class="large-text"><?php echo esc_textarea($settings['unsubscribe_text']); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Text shown at bottom of emails. Use', 'wpblogmailer'); ?> <code>{unsubscribe_link}</code>
                                <?php esc_html_e('for the unsubscribe link.', 'wpblogmailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="enable_greeting"><?php esc_html_e('Subscriber Greeting', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="enable_greeting"
                                       id="enable_greeting"
                                       value="1"
                                       <?php checked($settings['enable_greeting'], 1); ?>>
                                <?php esc_html_e('Enable personalized greeting in newsletters', 'wpblogmailer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Show a greeting at the beginning of each newsletter', 'wpblogmailer'); ?>
                            </p>

                            <div style="margin-top: 15px;">
                                <label for="greeting_text">
                                    <?php esc_html_e('Greeting Text:', 'wpblogmailer'); ?>
                                </label>
                                <input type="text"
                                       name="greeting_text"
                                       id="greeting_text"
                                       class="regular-text"
                                       value="<?php echo esc_attr($settings['greeting_text']); ?>"
                                       placeholder="Hi {first_name},">
                                <p class="description">
                                    <?php esc_html_e('Use', 'wpblogmailer'); ?> <code>{first_name}</code> <?php esc_html_e('to include the subscriber\'s first name. If no name is available, it will show "there" instead.', 'wpblogmailer'); ?>
                                    <br>
                                    <?php esc_html_e('Examples: "Hi {first_name}," or "Hello {first_name}!" or "Dear {first_name}," - customize in any language!', 'wpblogmailer'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="enable_site_link"><?php esc_html_e('Site Name Link', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="enable_site_link"
                                       id="enable_site_link"
                                       value="1"
                                       <?php checked($settings['enable_site_link'], 1); ?>>
                                <?php esc_html_e('Make site name heading clickable', 'wpblogmailer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, the site name in the email header will link to your homepage', 'wpblogmailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin-top: 30px; margin-bottom: 10px;">
                                <?php esc_html_e('Template Customization', 'wpblogmailer'); ?>
                            </h3>
                            <p class="description" style="font-weight: normal;">
                                <?php esc_html_e('Customize the colors and fonts used in your email templates', 'wpblogmailer'); ?>
                            </p>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_primary_color"><?php esc_html_e('Primary Color', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="template_primary_color"
                                   id="template_primary_color"
                                   value="<?php echo esc_attr($settings['template_primary_color']); ?>"
                                   class="wpbm-color-picker"
                                   data-default-color="#667eea">
                            <p class="description"><?php esc_html_e('Used for header, buttons, and accents', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_bg_color"><?php esc_html_e('Background Color', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="template_bg_color"
                                   id="template_bg_color"
                                   value="<?php echo esc_attr($settings['template_bg_color']); ?>"
                                   class="wpbm-color-picker"
                                   data-default-color="#f7f7f7">
                            <p class="description"><?php esc_html_e('Background color for the email', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_text_color"><?php esc_html_e('Text Color', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="template_text_color"
                                   id="template_text_color"
                                   value="<?php echo esc_attr($settings['template_text_color']); ?>"
                                   class="wpbm-color-picker"
                                   data-default-color="#333333">
                            <p class="description"><?php esc_html_e('Main text color', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_link_color"><?php esc_html_e('Link Color', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="template_link_color"
                                   id="template_link_color"
                                   value="<?php echo esc_attr($settings['template_link_color']); ?>"
                                   class="wpbm-color-picker"
                                   data-default-color="#2271b1">
                            <p class="description"><?php esc_html_e('Color for links and buttons', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_heading_font"><?php esc_html_e('Heading Font', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <select name="template_heading_font" id="template_heading_font" class="regular-text">
                                <option value="Arial, sans-serif" <?php selected($settings['template_heading_font'], 'Arial, sans-serif'); ?>>Arial</option>
                                <option value="Helvetica, sans-serif" <?php selected($settings['template_heading_font'], 'Helvetica, sans-serif'); ?>>Helvetica</option>
                                <option value="'Trebuchet MS', sans-serif" <?php selected($settings['template_heading_font'], "'Trebuchet MS', sans-serif"); ?>>Trebuchet MS</option>
                                <option value="'Courier New', monospace" <?php selected($settings['template_heading_font'], "'Courier New', monospace"); ?>>Courier New</option>
                                <option value="Georgia, serif" <?php selected($settings['template_heading_font'], 'Georgia, serif'); ?>>Georgia</option>
                                <option value="'Times New Roman', serif" <?php selected($settings['template_heading_font'], "'Times New Roman', serif"); ?>>Times New Roman</option>
                            </select>
                            <p class="description"><?php esc_html_e('Font family for headings', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="template_body_font"><?php esc_html_e('Body Font', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <select name="template_body_font" id="template_body_font" class="regular-text">
                                <option value="Georgia, serif" <?php selected($settings['template_body_font'], 'Georgia, serif'); ?>>Georgia</option>
                                <option value="'Times New Roman', serif" <?php selected($settings['template_body_font'], "'Times New Roman', serif"); ?>>Times New Roman</option>
                                <option value="Arial, sans-serif" <?php selected($settings['template_body_font'], 'Arial, sans-serif'); ?>>Arial</option>
                                <option value="Helvetica, sans-serif" <?php selected($settings['template_body_font'], 'Helvetica, sans-serif'); ?>>Helvetica</option>
                                <option value="'Trebuchet MS', sans-serif" <?php selected($settings['template_body_font'], "'Trebuchet MS', sans-serif"); ?>>Trebuchet MS</option>
                                <option value="Verdana, sans-serif" <?php selected($settings['template_body_font'], 'Verdana, sans-serif'); ?>>Verdana</option>
                            </select>
                            <p class="description"><?php esc_html_e('Font family for body text', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Preview Template', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <button type="button" id="wpbm-preview-template" class="button button-secondary">
                                <?php esc_html_e('Preview Email Template', 'wpblogmailer'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('See how your email template will look with current settings', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Schedule Tab -->
        <div class="wpbm-tab-content" id="tab-schedule">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="schedule_frequency"><?php esc_html_e('Send Frequency', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <select name="schedule_frequency" id="schedule_frequency">
                                <option value="daily" <?php selected($settings['schedule_frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'wpblogmailer'); ?></option>
                                <option value="weekly" <?php selected($settings['schedule_frequency'], 'weekly'); ?>><?php esc_html_e('Weekly', 'wpblogmailer'); ?></option>
                                <option value="monthly" <?php selected($settings['schedule_frequency'], 'monthly'); ?>><?php esc_html_e('Monthly', 'wpblogmailer'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('How often to send newsletters automatically', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr class="wpbm-schedule-day-row">
                        <th scope="row">
                            <label for="schedule_day"><?php esc_html_e('Send Day', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <select name="schedule_day" id="schedule_day">
                                <option value="monday" <?php selected($settings['schedule_day'], 'monday'); ?>><?php esc_html_e('Monday', 'wpblogmailer'); ?></option>
                                <option value="tuesday" <?php selected($settings['schedule_day'], 'tuesday'); ?>><?php esc_html_e('Tuesday', 'wpblogmailer'); ?></option>
                                <option value="wednesday" <?php selected($settings['schedule_day'], 'wednesday'); ?>><?php esc_html_e('Wednesday', 'wpblogmailer'); ?></option>
                                <option value="thursday" <?php selected($settings['schedule_day'], 'thursday'); ?>><?php esc_html_e('Thursday', 'wpblogmailer'); ?></option>
                                <option value="friday" <?php selected($settings['schedule_day'], 'friday'); ?>><?php esc_html_e('Friday', 'wpblogmailer'); ?></option>
                                <option value="saturday" <?php selected($settings['schedule_day'], 'saturday'); ?>><?php esc_html_e('Saturday', 'wpblogmailer'); ?></option>
                                <option value="sunday" <?php selected($settings['schedule_day'], 'sunday'); ?>><?php esc_html_e('Sunday', 'wpblogmailer'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Day of the week to send (for weekly schedule)', 'wpblogmailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="schedule_time"><?php esc_html_e('Send Time', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <input type="time"
                                   name="schedule_time"
                                   id="schedule_time"
                                   value="<?php echo esc_attr($settings['schedule_time']); ?>">
                            <p class="description">
                                <?php esc_html_e('Time of day to send (server timezone:', 'wpblogmailer'); ?>
                                <strong><?php echo esc_html(wp_timezone_string()); ?></strong>)
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Next Scheduled Send', 'wpblogmailer'); ?></th>
                        <td>
                            <?php
                            $next_scheduled = wp_next_scheduled('wpbm_send_newsletter');
                            if ($next_scheduled):
                            ?>
                                <p><strong><?php echo esc_html(wp_date('F j, Y g:i a', $next_scheduled)); ?></strong></p>
                                <p class="description">
                                    <?php
                                    printf(
                                        esc_html__('In %s', 'wpblogmailer'),
                                        human_time_diff($next_scheduled, time())
                                    );
                                    ?>
                                </p>
                            <?php else: ?>
                                <p><?php esc_html_e('No newsletter scheduled', 'wpblogmailer'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Advanced Tab (Starter+) -->
        <?php if (wpbm_is_starter() || wpbm_is_pro()): ?>
        <div class="wpbm-tab-content" id="tab-advanced">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="double_optin"><?php esc_html_e('Double Opt-in', 'wpblogmailer'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="double_optin"
                                       id="double_optin"
                                       value="1"
                                       <?php checked($settings['double_optin'], 1); ?>>
                                <?php esc_html_e('Enable double opt-in', 'wpblogmailer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Require subscribers to confirm their email address before being added to the list (GDPR compliant)', 'wpblogmailer'); ?>
                            </p>
                            <span class="wpbm-feature-badge"><?php esc_html_e('Starter+ Feature', 'wpblogmailer'); ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" name="wpbm_save_settings" class="button button-primary button-large">
                <?php esc_html_e('Save Settings', 'wpblogmailer'); ?>
            </button>
        </p>
    </form>
</div>

<style>
.wpbm-settings-page {
    max-width: 1000px;
}

.wpbm-settings-tabs {
    background: #fff;
    border-bottom: 1px solid #c3c4c7;
    margin: 20px 0 0 0;
    padding: 0;
}

.wpbm-tab-btn {
    background: none;
    border: none;
    padding: 15px 20px;
    cursor: pointer;
    font-size: 14px;
    color: #646970;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.wpbm-tab-btn:hover {
    color: #2271b1;
}

.wpbm-tab-btn.active {
    color: #2271b1;
    border-bottom-color: #2271b1;
    font-weight: 600;
}

.wpbm-tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #c3c4c7;
    border-top: none;
}

.wpbm-tab-content.active {
    display: block;
}

.wpbm-feature-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 10px;
}

.form-table th {
    width: 220px;
}

/* Preview Modal Styles */
.wpbm-modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
}

.wpbm-modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 90%;
    max-width: 900px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: relative;
}

.wpbm-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    line-height: 20px;
    cursor: pointer;
    transition: color 0.2s;
}

.wpbm-modal-close:hover,
.wpbm-modal-close:focus {
    color: #000;
}

.wpbm-preview-container {
    margin-top: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
}

#wpbm-preview-iframe {
    width: 100%;
    height: 600px;
    border: none;
    background: #fff;
}

.wpbm-modal-content h2 {
    margin-top: 0;
    padding-right: 30px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize WordPress color pickers
    $('.wpbm-color-picker').wpColorPicker();

    // Tab switching
    $('.wpbm-tab-btn').on('click', function() {
        var tab = $(this).data('tab');

        $('.wpbm-tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.wpbm-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // Show/hide schedule day based on frequency
    function toggleScheduleDay() {
        var frequency = $('#schedule_frequency').val();
        if (frequency === 'weekly') {
            $('.wpbm-schedule-day-row').show();
        } else {
            $('.wpbm-schedule-day-row').hide();
        }
    }

    $('#schedule_frequency').on('change', toggleScheduleDay);
    toggleScheduleDay();

    // Show/hide excerpt length based on content type
    function toggleExcerptLength() {
        var contentType = $('#post_content_type').val();
        if (contentType === 'excerpt') {
            $('#excerpt_length_row').show();
        } else {
            $('#excerpt_length_row').hide();
        }
    }

    $('#post_content_type').on('change', toggleExcerptLength);
    toggleExcerptLength();

    // Preview template functionality
    $('#wpbm-preview-template').on('click', function() {
        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text('<?php esc_html_e('Generating Preview...', 'wpblogmailer'); ?>');

        // Get current form values
        var previewData = {
            action: 'wpbm_preview_template',
            nonce: '<?php echo wp_create_nonce('wpbm_preview_template'); ?>',
            template_type: $('#template_type').val(),
            template_primary_color: $('#template_primary_color').val(),
            template_bg_color: $('#template_bg_color').val(),
            template_text_color: $('#template_text_color').val(),
            template_link_color: $('#template_link_color').val(),
            template_heading_font: $('#template_heading_font').val(),
            template_body_font: $('#template_body_font').val(),
            post_content_type: $('#post_content_type').val(),
            excerpt_length: $('#excerpt_length').val()
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: previewData,
            success: function(response) {
                if (response.success) {
                    // Create modal with preview
                    var modal = $('<div id="wpbm-preview-modal" class="wpbm-modal">' +
                        '<div class="wpbm-modal-content">' +
                        '<span class="wpbm-modal-close">&times;</span>' +
                        '<h2><?php esc_html_e('Email Template Preview', 'wpblogmailer'); ?></h2>' +
                        '<div class="wpbm-preview-container">' +
                        '<iframe id="wpbm-preview-iframe" sandbox="allow-same-origin"></iframe>' +
                        '</div>' +
                        '</div>' +
                        '</div>');

                    $('body').append(modal);

                    // Load preview content into iframe
                    var iframe = document.getElementById('wpbm-preview-iframe');
                    iframe.contentWindow.document.open();
                    iframe.contentWindow.document.write(response.data.html);
                    iframe.contentWindow.document.close();

                    // Show modal
                    modal.fadeIn();

                    // Close modal on click
                    $('.wpbm-modal-close, .wpbm-modal').on('click', function(e) {
                        if (e.target === this) {
                            modal.fadeOut(function() {
                                modal.remove();
                            });
                        }
                    });
                } else {
                    alert('<?php esc_html_e('Error generating preview. Please try again.', 'wpblogmailer'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Error generating preview. Please try again.', 'wpblogmailer'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
