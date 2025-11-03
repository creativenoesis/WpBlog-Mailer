<?php
/**
 * Predefined Templates Service
 * Provides predefined email templates for custom emails
 *
 * @package WPBlogMailer
 * @subpackage Common\Services
 * @since 2.1.0
 */

namespace WPBlogMailer\Common\Services;

if (!defined('ABSPATH')) exit;

class PredefinedTemplatesService {

    /**
     * Get all predefined templates
     *
     * @return array Array of template definitions
     */
    public function get_templates() {
        return array(
            'blank' => array(
                'name' => __('Blank', 'wpblogmailer'),
                'description' => __('Start with a blank template', 'wpblogmailer'),
                'content' => ''
            ),
            'simple' => array(
                'name' => __('Simple Announcement', 'wpblogmailer'),
                'description' => __('Clean and simple announcement template', 'wpblogmailer'),
                'content' => $this->get_simple_template()
            ),
            'newsletter' => array(
                'name' => __('Newsletter', 'wpblogmailer'),
                'description' => __('Professional newsletter layout', 'wpblogmailer'),
                'content' => $this->get_newsletter_template()
            ),
            'promotion' => array(
                'name' => __('Promotion', 'wpblogmailer'),
                'description' => __('Eye-catching promotional template', 'wpblogmailer'),
                'content' => $this->get_promotion_template()
            ),
            'update' => array(
                'name' => __('Product Update', 'wpblogmailer'),
                'description' => __('Announce product updates or new features', 'wpblogmailer'),
                'content' => $this->get_update_template()
            ),
        );
    }

    /**
     * Get simple announcement template
     */
    private function get_simple_template() {
        return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;">
    <h1 style="color: #333333; font-size: 28px; margin-bottom: 20px;">Your Announcement Title</h1>

    <p style="color: #666666; font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
        Write your announcement here. This template is perfect for simple updates, news, or general announcements to your subscribers.
    </p>

    <p style="color: #666666; font-size: 16px; line-height: 1.6; margin-bottom: 30px;">
        Add more paragraphs as needed. Keep your message clear and concise.
    </p>

    <div style="text-align: center;">
        <a href="https://your-website.com" style="display: inline-block; background-color: #0073aa; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">Learn More</a>
    </div>
</div>';
    }

    /**
     * Get newsletter template
     */
    private function get_newsletter_template() {
        return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f4f4f4; padding: 20px;">
    <div style="background-color: #ffffff; padding: 30px; border-radius: 8px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #333333; font-size: 32px; margin: 0;">Newsletter Title</h1>
            <p style="color: #999999; font-size: 14px; margin-top: 10px;">Your monthly update - [Current Date]</p>
        </div>

        <div style="border-bottom: 3px solid #0073aa; margin-bottom: 30px;"></div>

        <h2 style="color: #0073aa; font-size: 24px; margin-bottom: 15px;">Featured Article</h2>
        <p style="color: #666666; font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
            Introduce your main content here. This could be your latest blog post, announcement, or featured story.
        </p>

        <a href="#" style="display: inline-block; background-color: #0073aa; color: #ffffff; padding: 10px 25px; text-decoration: none; border-radius: 4px; margin-bottom: 30px;">Read More</a>

        <h2 style="color: #333333; font-size: 20px; margin-bottom: 15px;">Latest Updates</h2>
        <ul style="color: #666666; font-size: 15px; line-height: 1.8;">
            <li>Update item 1 - Add your latest news here</li>
            <li>Update item 2 - Keep your readers informed</li>
            <li>Update item 3 - Share valuable insights</li>
        </ul>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eeeeee; text-align: center;">
            <p style="color: #999999; font-size: 14px;">Thank you for being a valued subscriber!</p>
        </div>
    </div>
</div>';
    }

    /**
     * Get promotion template
     */
    private function get_promotion_template() {
        return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; border-radius: 10px;">
    <div style="text-align: center; color: #ffffff; margin-bottom: 30px;">
        <h1 style="font-size: 36px; margin: 0 0 10px 0; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">Special Offer!</h1>
        <p style="font-size: 18px; margin: 0; opacity: 0.9;">Limited Time Only</p>
    </div>

    <div style="background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="color: #333333; font-size: 24px; margin-bottom: 15px; text-align: center;">Get [X]% Off</h2>

        <p style="color: #666666; font-size: 16px; line-height: 1.6; text-align: center; margin-bottom: 25px;">
            Don\'t miss this exclusive offer for our subscribers! Save big on your next purchase.
        </p>

        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; text-align: center; margin-bottom: 25px;">
            <p style="color: #999999; font-size: 14px; margin: 0 0 10px 0;">USE CODE</p>
            <p style="color: #667eea; font-size: 32px; font-weight: bold; margin: 0; letter-spacing: 2px;">SAVE20</p>
        </div>

        <div style="text-align: center;">
            <a href="https://your-website.com/shop" style="display: inline-block; background-color: #667eea; color: #ffffff; padding: 15px 40px; text-decoration: none; border-radius: 50px; font-size: 18px; font-weight: bold; box-shadow: 0 4px 10px rgba(102, 126, 234, 0.4);">Shop Now</a>
        </div>

        <p style="color: #999999; font-size: 12px; text-align: center; margin-top: 20px;">
            *Offer expires in 48 hours. Terms and conditions apply.
        </p>
    </div>
</div>';
    }

    /**
     * Get product update template
     */
    private function get_update_template() {
        return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;">
    <div style="background-color: #0073aa; color: #ffffff; padding: 30px; border-radius: 8px 8px 0 0;">
        <h1 style="font-size: 28px; margin: 0;">🚀 New Feature Alert!</h1>
    </div>

    <div style="padding: 30px; background-color: #f8f9fa; border-radius: 0 0 8px 8px;">
        <h2 style="color: #333333; font-size: 24px; margin-bottom: 15px;">We\'ve Added Something Exciting</h2>

        <p style="color: #666666; font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
            We\'re thrilled to announce a new feature that will make your experience even better!
        </p>

        <div style="background-color: #ffffff; padding: 20px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
            <h3 style="color: #0073aa; font-size: 18px; margin: 0 0 10px 0;">What\'s New:</h3>
            <ul style="color: #666666; font-size: 15px; line-height: 1.8; margin: 0; padding-left: 20px;">
                <li>Feature highlight 1</li>
                <li>Feature highlight 2</li>
                <li>Feature highlight 3</li>
            </ul>
        </div>

        <p style="color: #666666; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
            This update is available to you right now. Click below to check it out!
        </p>

        <div style="text-align: center;">
            <a href="https://your-website.com" style="display: inline-block; background-color: #0073aa; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">Try It Now</a>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dddddd;">
            <p style="color: #999999; font-size: 14px; text-align: center; margin: 0;">
                Questions? <a href="mailto:support@your-website.com" style="color: #0073aa; text-decoration: none;">Contact our support team</a>
            </p>
        </div>
    </div>
</div>';
    }
}
