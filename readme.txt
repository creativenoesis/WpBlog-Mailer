=== WP Blog Mailer ===
Contributors: creativenoesis
Tags: newsletter, email, subscribers, automated, blog
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple automated newsletter system for WordPress. Send your latest blog posts to subscribers automatically.

== Description ==

WP Blog Mailer is a lightweight, easy-to-use plugin that automatically sends your latest blog posts to your subscribers. Perfect for bloggers who want to keep their audience engaged without the complexity of third-party email services.

= Free Features =

* **Automated Newsletters** - Schedule automatic newsletters with your latest posts
* **Subscriber Management** - Easy-to-use subscriber list management
* **Subscription Forms** - Add subscribe forms anywhere with `[wpbm_subscribe_form]` shortcode
* **Customizable Templates** - Basic email template customization
* **Send Log** - Track all sent emails
* **Cron Monitoring** - Monitor your automated sending jobs
* **Manual Sending** - Send newsletters on demand
* **Test Emails** - Preview newsletters before sending

= Pro Features =

Upgrade to [WP Blog Mailer Pro](https://creativenoesis.com/wp-blog-mailer/) for advanced features:

* **Advanced Analytics** - Track opens, clicks, and engagement
* **Custom Templates** - Create beautiful custom email templates
* **Subscriber Tags** - Organize subscribers and send targeted campaigns
* **Segmentation** - Send to specific subscriber groups
* **Import/Export** - CSV import and export functionality
* **Email Queue** - Reliable delivery for large subscriber lists
* **Priority Support** - Get help fast from our team

= Perfect For =

* Bloggers who want to notify subscribers of new posts
* Small businesses sharing updates
* Content creators building an audience
* Anyone wanting simple email automation

= How It Works =

1. Activate the plugin
2. Configure your sending preferences
3. Add the subscription form to your site
4. Subscribers receive automatic newsletters with your latest posts

== Installation ==

1. Upload the `wp-blog-mailer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Blog Mailer → Settings** to configure your preferences
4. Add the subscription form using the `[wpbm_subscribe_form]` shortcode

= Minimum Requirements =

* WordPress 5.8 or greater
* PHP version 7.4 or greater
* MySQL version 5.7 or greater OR MariaDB version 10.2 or greater

== Frequently Asked Questions ==

= Do I need a third-party email service? =

No! WP Blog Mailer uses WordPress's built-in wp_mail() function. However, for better deliverability with large lists, we recommend using an SMTP plugin like WP Mail SMTP.

= How often are newsletters sent? =

You can configure the sending frequency in settings. Options include daily, twice weekly, weekly, or manual sending only.

= Can I customize the email template? =

Yes! The free version includes basic template customization (colors, fonts). The Pro version offers advanced custom templates.

= Is there a subscriber limit? =

The free version has no hard limit, but performance may vary based on your server. For lists over 500 subscribers, we recommend the Pro version with email queue management.

= Can I import existing subscribers? =

CSV import is available in the Pro version. In the free version, you can add subscribers manually or via the subscription form.

= How do subscribers unsubscribe? =

Every email includes an automatic unsubscribe link. Subscribers can opt-out with one click.

= Is it GDPR compliant? =

Yes! The plugin includes:
* Clear consent during subscription
* Easy unsubscribe process
* Complete data deletion on uninstall (if configured)
* No data sent to third parties

= What happens to my data if I delete the plugin? =

You can configure whether to keep or delete all subscriber data when uninstalling the plugin.

== Screenshots ==

1. Dashboard - Overview of your newsletter stats
2. Subscriber Management - Easy list management
3. Settings - Configure your newsletter preferences
4. Send Log - Track all sent emails
5. Subscription Form - Clean, customizable form

== Changelog ==

= 1.0.0 =
* Initial release
* Automated newsletter sending
* Subscriber management
* Basic template customization
* Send log tracking
* Cron job monitoring
* Subscription form shortcode

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Blog Mailer.

== Support ==

For support, please visit our [support forum](https://wordpress.org/support/plugin/wp-blog-mailer/).

For Pro users, priority support is available at [Creative Noesis Support](https://creativenoesis.com/support/).

== Privacy Policy ==

WP Blog Mailer stores subscriber information (email addresses, names, subscription dates) in your WordPress database. No data is sent to external services unless you configure an SMTP service.

When subscribers use the unsubscribe link, they can opt to delete all their data.

If you delete the plugin and choose to remove data, all subscriber information will be permanently deleted from your database.
