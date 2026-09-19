<?php
/**
 * Plugin Name: KitMage Woo Cart Collaterals Message
 * Description: Adds rule-based custom HTML messages to the WooCommerce cart collaterals area based on cart product IDs.
 * Version: 1.0.0
 * Author: Mike@KitMage
 * URI: https://kitmage.com
 * Author URI: https://kitmage.com
 * Text Domain: woo-cart-collaterals-message
 * Requires Plugins: woocommerce
 *
 * @package WooCartCollateralsMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Cart_Collaterals_Message' ) ) {
	/**
	 * Main plugin class.
	 */
	class Woo_Cart_Collaterals_Message {
		/**
		 * Option name for storing cart message rules.
		 */
		private const OPTION_NAME = 'wccm_rules';

		/**
		 * Settings page slug.
		 */
		private const PAGE_SLUG = 'wccm-rules';

		/**
		 * Boot plugin hooks.
		 */
		public static function init(): void {
			add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
			add_action( 'admin_post_wccm_save_rules', array( __CLASS__, 'save_rules' ) );
			add_action( 'woocommerce_cart_collaterals', array( __CLASS__, 'render_cart_message' ), 5 );
		}

		/**
		 * Add the admin menu page.
		 */
		public static function add_admin_menu(): void {
			add_menu_page(
				__( 'Cart Collaterals Message Rules', 'woo-cart-collaterals-message' ),
				__( 'Cart Messages', 'woo-cart-collaterals-message' ),
				'manage_woocommerce',
				self::PAGE_SLUG,
				array( __CLASS__, 'render_admin_page' ),
				'dashicons-feedback',
				56
			);
		}

		/**
		 * Render the rule editor admin page.
		 */
		public static function render_admin_page(): void {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage these settings.', 'woo-cart-collaterals-message' ) );
			}

			$rules = self::get_rules();
			$rules[] = array(
				'product_ids' => '',
				'html'        => '',
			);
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Cart Message Rules', 'woo-cart-collaterals-message' ); ?></h1>
				<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rules saved.', 'woo-cart-collaterals-message' ); ?></p></div>
				<?php endif; ?>
				<p><?php esc_html_e( 'Create rules that show custom HTML in the WooCommerce cart collaterals area when the cart contains one or more listed product IDs.', 'woo-cart-collaterals-message' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wccm_save_rules" />
					<?php wp_nonce_field( 'wccm_save_rules' ); ?>
					<table class="widefat striped" style="max-width: 1100px;">
						<thead>
							<tr>
								<th style="width: 30%;"><?php esc_html_e( 'Product IDs', 'woo-cart-collaterals-message' ); ?></th>
								<th><?php esc_html_e( 'Custom HTML', 'woo-cart-collaterals-message' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rules as $index => $rule ) : ?>
								<tr>
									<td>
										<label class="screen-reader-text" for="wccm-product-ids-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Product IDs', 'woo-cart-collaterals-message' ); ?></label>
										<input id="wccm-product-ids-<?php echo esc_attr( (string) $index ); ?>" type="text" class="regular-text" name="rules[<?php echo esc_attr( (string) $index ); ?>][product_ids]" value="<?php echo esc_attr( $rule['product_ids'] ); ?>" placeholder="<?php esc_attr_e( '123, 456, 789', 'woo-cart-collaterals-message' ); ?>" />
										<p class="description"><?php esc_html_e( 'Comma separated product IDs. Leave a row blank to ignore it.', 'woo-cart-collaterals-message' ); ?></p>
									</td>
									<td>
										<label class="screen-reader-text" for="wccm-html-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Custom HTML', 'woo-cart-collaterals-message' ); ?></label>
										<textarea id="wccm-html-<?php echo esc_attr( (string) $index ); ?>" class="large-text code" rows="5" name="rules[<?php echo esc_attr( (string) $index ); ?>][html]" placeholder="<?php esc_attr_e( '<p>Your custom message.</p>', 'woo-cart-collaterals-message' ); ?>"><?php echo esc_textarea( $rule['html'] ); ?></textarea>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'To add more rules, save this page and a new blank row will appear.', 'woo-cart-collaterals-message' ); ?></p>
					<?php submit_button( __( 'Save Rules', 'woo-cart-collaterals-message' ) ); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Save submitted cart message rules.
		 */
		public static function save_rules(): void {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage these settings.', 'woo-cart-collaterals-message' ) );
			}

			check_admin_referer( 'wccm_save_rules' );

			$rules     = isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$sanitized = array();

			foreach ( $rules as $rule ) {
				$product_ids = isset( $rule['product_ids'] ) ? self::sanitize_product_ids( (string) $rule['product_ids'] ) : '';
				$html        = isset( $rule['html'] ) ? wp_kses_post( (string) $rule['html'] ) : '';

				if ( '' === $product_ids || '' === trim( $html ) ) {
					continue;
				}

				$sanitized[] = array(
					'product_ids' => $product_ids,
					'html'        => $html,
				);
			}

			update_option( self::OPTION_NAME, $sanitized );

			wp_safe_redirect(
				add_query_arg(
					'settings-updated',
					'true',
					admin_url( 'admin.php?page=' . self::PAGE_SLUG )
				)
			);
			exit;
		}

		/**
		 * Render matching HTML in the cart collaterals area.
		 */
		public static function render_cart_message(): void {
			if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
				return;
			}

			$cart_product_ids = self::get_cart_product_ids();

			if ( array() === $cart_product_ids ) {
				return;
			}

			foreach ( self::get_rules() as $rule ) {
				$rule_product_ids = self::product_ids_to_array( $rule['product_ids'] );

				if ( array_intersect( $rule_product_ids, $cart_product_ids ) ) {
					echo '<div class="woocommerce-cart-collaterals-message">' . wp_kses_post( $rule['html'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
		}

		/**
		 * Get saved rules.
		 *
		 * @return array<int, array{product_ids: string, html: string}>
		 */
		private static function get_rules(): array {
			$rules = get_option( self::OPTION_NAME, array() );

			return is_array( $rules ) ? $rules : array();
		}

		/**
		 * Get all product and variation IDs currently in the cart.
		 *
		 * @return array<int, int>
		 */
		private static function get_cart_product_ids(): array {
			$product_ids = array();

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( ! empty( $cart_item['product_id'] ) ) {
					$product_ids[] = absint( $cart_item['product_id'] );
				}

				if ( ! empty( $cart_item['variation_id'] ) ) {
					$product_ids[] = absint( $cart_item['variation_id'] );
				}
			}

			return array_values( array_unique( array_filter( $product_ids ) ) );
		}

		/**
		 * Sanitize a comma separated product ID list.
		 */
		private static function sanitize_product_ids( string $product_ids ): string {
			return implode( ', ', self::product_ids_to_array( $product_ids ) );
		}

		/**
		 * Convert a comma separated product ID list to positive integers.
		 *
		 * @return array<int, int>
		 */
		private static function product_ids_to_array( string $product_ids ): array {
			$ids = array_map( 'absint', preg_split( '/\s*,\s*/', $product_ids, -1, PREG_SPLIT_NO_EMPTY ) ?: array() );

			return array_values( array_unique( array_filter( $ids ) ) );
		}
	}
}

Woo_Cart_Collaterals_Message::init();
