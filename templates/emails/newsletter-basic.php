<?php
/**
 * Basic Newsletter Email Template
 *
 * Available variables:
 * @var array $posts Array of WP_Post objects
 * @var string $heading Email heading/subject
 * @var object $subscriber Subscriber object with email, first_name, last_name, etc.
 * @var string $unsubscribe_url Generated unsubscribe link
 * @var string $site_name Blog name
 * @var string $site_url Site home URL
 * @var string $post_content_type Type of content (excerpt/full)
 * @var int $excerpt_length Length of excerpt in words
 * @var bool $enable_greeting Whether to show greeting
 * @var string $greeting_text Customizable greeting text with {first_name} placeholder
 * @var bool $enable_site_link Whether site name should be a link
 * @var string $primary_color Primary color for headers and buttons
 * @var string $bg_color Background color
 * @var string $text_color Main text color
 * @var string $link_color Link color
 * @var string $heading_font Font for headings
 * @var string $body_font Font for body text
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo esc_html($heading); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: <?php echo isset($body_font) ? esc_attr($body_font) : 'Georgia, serif'; ?>;
            background-color: <?php echo isset($bg_color) ? esc_attr($bg_color) : '#f7f7f7'; ?>;
            line-height: 1.6;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: <?php echo isset($primary_color) ? esc_attr($primary_color) : '#667eea'; ?>;
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            font-family: <?php echo isset($heading_font) ? esc_attr($heading_font) : 'Arial, sans-serif'; ?>;
        }
        .email-body {
            padding: 30px;
        }
        .greeting {
            font-size: 16px;
            color: <?php echo isset($text_color) ? esc_attr($text_color) : '#333333'; ?>;
            margin-bottom: 20px;
        }
        .post-item {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        .post-item:last-child {
            border-bottom: none;
        }
        .post-title {
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 10px 0;
            line-height: 1.3;
            font-family: <?php echo isset($heading_font) ? esc_attr($heading_font) : 'Arial, sans-serif'; ?>;
        }
        .post-title a {
            color: <?php echo isset($link_color) ? esc_attr($link_color) : '#2271b1'; ?>;
            text-decoration: none;
        }
        .post-title a:hover {
            text-decoration: underline;
        }
        .post-meta {
            font-size: 13px;
            color: #666666;
            margin-bottom: 15px;
        }
        .post-excerpt {
            font-size: 15px;
            color: <?php echo isset($text_color) ? esc_attr($text_color) : '#333333'; ?>;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .read-more {
            display: inline-block;
            padding: 10px 20px;
            background-color: <?php echo isset($link_color) ? esc_attr($link_color) : '#2271b1'; ?>;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        .read-more:hover {
            opacity: 0.9;
        }
        .email-footer {
            background-color: #f8f8f8;
            padding: 30px;
            text-align: center;
            font-size: 13px;
            color: #666666;
        }
        .email-footer a {
            color: <?php echo isset($link_color) ? esc_attr($link_color) : '#2271b1'; ?>;
            text-decoration: none;
        }
        .email-footer a:hover {
            text-decoration: underline;
        }
        .unsubscribe {
            margin-top: 20px;
            font-size: 12px;
            color: #999999;
        }
        /* Responsive images */
        .post-excerpt img,
        .post-item img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 15px 0;
        }
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            .email-header {
                padding: 30px 20px;
            }
            .email-header h1 {
                font-size: 24px;
            }
            .email-body {
                padding: 20px;
            }
            .post-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <?php if (!empty($enable_site_link)): ?>
                <h1><a href="<?php echo esc_url($site_url); ?>" style="color: #ffffff; text-decoration: none;"><?php echo esc_html($site_name); ?></a></h1>
            <?php else: ?>
                <h1><?php echo esc_html($site_name); ?></h1>
            <?php endif; ?>
            <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">
                <?php echo esc_html($heading); ?>
            </p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <?php if (!empty($enable_greeting) && !empty($greeting_text)): ?>
                <div class="greeting">
                    <?php
                    // Replace {first_name} placeholder with actual name or 'there' if no name
                    $name = !empty($subscriber->first_name) ? $subscriber->first_name : 'there';
                    echo esc_html(str_replace('{first_name}', $name, $greeting_text));
                    ?>
                </div>
            <?php endif; ?>

            <p style="font-size: 15px; color: #444444; margin-bottom: 30px;">
                <?php
                printf(
                    esc_html__('Here are the latest posts from %s:', 'wpblogmailer'),
                    esc_html($site_name)
                );
                ?>
            </p>

            <?php if (!empty($posts) && is_array($posts)): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-item">
                        <h2 class="post-title">
                            <a href="<?php echo esc_url(get_permalink($post->ID)); ?>">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                        </h2>

                        <div class="post-meta">
                            <?php
                            $author = get_the_author_meta('display_name', $post->post_author);
                            $date = get_the_date('F j, Y', $post);
                            printf(
                                esc_html__('By %1$s on %2$s', 'wpblogmailer'),
                                esc_html($author),
                                esc_html($date)
                            );
                            ?>
                        </div>

                        <div class="post-excerpt">
                            <?php
                            if ($post_content_type === 'full') {
                                // Show full post content
                                $content = $post->post_content;

                                // Strip WordPress block comments
                                $content = preg_replace('/<!--\s*\/?wp:.*?-->/', '', $content);

                                // Keep basic HTML but strip dangerous tags
                                $content = wp_kses_post($content);

                                // Make images responsive with inline styles for email compatibility
                                $content = preg_replace_callback(
                                    '/<img([^>]*)>/i',
                                    function($matches) {
                                        $attributes = $matches[1];
                                        // Remove width/height attributes
                                        $attributes = preg_replace('/\s*width=(["\'])[^"\']*\1/i', '', $attributes);
                                        $attributes = preg_replace('/\s*height=(["\'])[^"\']*\1/i', '', $attributes);
                                        // Add inline responsive styles
                                        if (!preg_match('/style=/i', $attributes)) {
                                            $attributes .= ' style="max-width: 100%; height: auto; display: block;"';
                                        }
                                        return '<img' . $attributes . '>';
                                    },
                                    $content
                                );

                                echo $content;
                            } else {
                                // Show excerpt
                                $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;

                                // Strip WordPress block comments (<!-- wp:gallery -->, etc.)
                                $excerpt = preg_replace('/<!--\s*\/?wp:.*?-->/', '', $excerpt);

                                // Strip all HTML tags for clean text excerpt
                                $excerpt = wp_strip_all_tags($excerpt);

                                // Trim to desired length
                                $excerpt = wp_trim_words($excerpt, $excerpt_length, '...');

                                echo esc_html($excerpt);
                            }
                            ?>
                        </div>

                        <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="read-more">
                            <?php esc_html_e('Read More', 'wpblogmailer'); ?> &rarr;
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="font-size: 15px; color: #666666;">
                    <?php esc_html_e('No new posts to display at this time.', 'wpblogmailer'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p style="margin: 0 0 10px 0;">
                &copy; <?php echo date('Y'); ?>
                <a href="<?php echo esc_url($site_url); ?>">
                    <?php echo esc_html($site_name); ?>
                </a>
            </p>

            <p style="margin: 10px 0;">
                <?php esc_html_e('You received this email because you subscribed to our newsletter.', 'wpblogmailer'); ?>
            </p>

            <div class="unsubscribe">
                <a href="<?php echo esc_url($unsubscribe_url); ?>">
                    <?php esc_html_e('Unsubscribe from this list', 'wpblogmailer'); ?>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
