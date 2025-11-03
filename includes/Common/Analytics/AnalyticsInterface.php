<?php
namespace WPBlogMailer\Common\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface AnalyticsInterface
 *
 * Defines the contract for analytics reporting services.
 */
interface AnalyticsInterface {

	/**
	 * Get aggregated analytics data for the dashboard.
	 *
	 * @param int $days Number of days to look back.
	 * @return array
	 */
	public function get_dashboard_stats( $days = 30 );

	/**
	 * Get analytics data for a specific email or campaign.
	 *
	 * @param int $email_id The ID of the email or campaign.
	 * @return array
	 */
	public function get_email_report( $email_id );

}