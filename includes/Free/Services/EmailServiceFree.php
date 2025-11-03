<?php
namespace WPBlogMailer\Free\Services;

class EmailServiceFree extends \WPBlogMailer\Common\Services\BaseEmailService {
    
    /**
     * Free tier does no pre-send tracking.
     */
    protected function pre_send_tracking($content, $recipient_email, $tracking_data) {
        return $content; // Return content unmodified
    }

    /**
     * Free tier has no post-send tracking.
     */
    protected function track_send($result, $email_data, $tracking_data) {
        if ($result) {
            $this->logger->info('Email sent (Free Tier). To: ' . $email_data['to']);
        }
    }
}