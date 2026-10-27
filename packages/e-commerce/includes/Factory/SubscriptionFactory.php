<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Commerce\Factory;

use OhMyLMS\Commerce\Data\Subscription;

/**
 * Plugin subscription factory implementation.
 */
class SubscriptionFactory {
	/**
	 * Get subscription
	 *
	 * @param bool|int|Subscription $subscription_id Subscription identifier.
	 * @return Subscription|bool
	 */
	public function get_subscription( $subscription_id = false ) {
		$subscription_id = $this->get_subscription_id( $subscription_id );
		if ( ! $subscription_id ) {
			return false;
		}

		return new Subscription( $subscription_id );
	}

	/**
	 * Get subscription id
	 *
	 * @param mixed $subscription Subscription.
	 * @return bool|int
	 */
	private function get_subscription_id( $subscription ) {
		global $post;

		if ( false === $subscription && isset( $post, $post->ID ) && 'ohmylms-subscription' === \get_post_type( $post->ID ) ) {
			return \absint( $post->ID );
		} elseif ( is_numeric( $subscription ) ) {
			return $subscription;
		} elseif ( $subscription instanceof Subscription ) {
			return $subscription->get_id();
		} elseif ( ! empty( $subscription->ID ) ) {
			return $subscription->ID;
		} else {
			return false;
		}
	}
}
