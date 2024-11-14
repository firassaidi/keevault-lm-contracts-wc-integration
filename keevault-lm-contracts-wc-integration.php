<?php
/*
Plugin Name: Keevault License Manager - Contracts WooCommerce Integration
Description: Creates Keevault contracts when WooCommerce products are purchased.
Version: 1.0
Author: Firas Saidi
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Keevault_LM_Contracts_WC_Integration {

	public function __construct() {
		// Initialize Keevault Settings menu and fields
		add_action( 'admin_menu', [ $this, 'add_keevault_menu' ] );
		add_action( 'admin_init', [ $this, 'keevault_settings_init' ] );

		// Product-specific Keevault settings
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_keevault_product_settings' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_keevault_product_settings' ] );

		// Add settings for product variations
		add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'add_keevault_variation_settings' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'save_keevault_variation_settings' ], 10, 2 );

		// Order Hook for license creation
		add_action( 'woocommerce_order_status_processing', [ $this, 'create_contract_on_order' ] );
		add_action( 'woocommerce_order_status_completed', [ $this, 'create_contract_on_order' ] );
	}

	public function add_keevault_menu(): void {
		add_menu_page(
			'Keevault Settings',
			'Keevault',
			'manage_options',
			'keevault_lm_contracts_',
			[ $this, 'keevault_settings_page' ],
			'dashicons-admin-network'
		);
	}

	public function keevault_settings_init(): void {
		register_setting( 'keevault_lm_contracts_settings', 'keevault_lm_contracts_api_key' );
		register_setting( 'keevault_lm_contracts_settings', 'keevault_lm_contracts_api_url' );

		add_settings_section( 'keevault_lm_contracts_global_section', esc_html__( 'Keevault API Configuration', 'woocommerce' ), null, 'keevault_lm_contracts_settings' );

		add_settings_field( 'keevault_lm_contracts_api_key', esc_html__( 'API Key', 'woocommerce' ), function () {
			$keevault_lm_contracts_api_key = get_option( 'keevault_lm_contracts_api_key' );
			echo "<input type='text' name='keevault_lm_contracts_api_key' value='{$keevault_lm_contracts_api_key}' />";
		}, 'keevault_lm_contracts_settings', 'keevault_lm_contracts_global_section' );

		add_settings_field( 'keevault_lm_contracts_api_url', esc_html__( 'API URL', 'woocommerce' ), function () {
			$keevault_lm_contracts_api_url = get_option( 'keevault_lm_contracts_api_url' );
			echo "<input type='text' name='keevault_lm_contracts_api_url' value='{$keevault_lm_contracts_api_url}' />";
		}, 'keevault_lm_contracts_settings', 'keevault_lm_contracts_global_section' );
	}

	public function keevault_settings_page(): void {
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'keevault_lm_contracts_settings' );
			do_settings_sections( 'keevault_lm_contracts_settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	public function add_keevault_product_settings(): void {
		echo '<div class="options_group">';

		woocommerce_wp_text_input( [
			'id'          => '_keevault_product_id',
			'label'       => esc_html__( 'Keevault Product ID', 'woocommerce' ),
			'desc_tip'    => 'true',
			'description' => esc_html__( 'Keevault Product ID for license creation.', 'woocommerce' ),
		] );

		woocommerce_wp_text_input( [
			'id'                => '_keevault_license_quantity',
			'label'             => esc_html__( 'License Keys Quantity', 'woocommerce' ),
			'desc_tip'          => 'true',
			'description'       => esc_html__( 'Number of license keys to create per purchase.', 'woocommerce' ),
			'type'              => 'number',
			'custom_attributes' => [ 'min' => '1' ]
		] );

		echo '</div>';
	}

	public function save_keevault_product_settings( $post_id ): void {
		$keevault_product_id       = isset( $_POST['_keevault_product_id'] ) ? sanitize_text_field( $_POST['_keevault_product_id'] ) : '';
		$keevault_license_quantity = isset( $_POST['_keevault_license_quantity'] ) ? intval( $_POST['_keevault_license_quantity'] ) : 1;

		update_post_meta( $post_id, '_keevault_product_id', $keevault_product_id );
		update_post_meta( $post_id, '_keevault_license_quantity', $keevault_license_quantity );
	}

	public function add_keevault_variation_settings( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input( [
			'id'          => '_keevault_product_id_' . $variation->ID,
			'label'       => esc_html__( 'Keevault Product ID', 'woocommerce' ),
			'description' => esc_html__( 'Keevault Product ID for this variation.', 'woocommerce' ),
			'value'       => get_post_meta( $variation->ID, '_keevault_product_id', true )
		] );

		woocommerce_wp_text_input( [
			'id'                => '_keevault_license_quantity_' . $variation->ID,
			'label'             => esc_html__( 'License Keys Quantity', 'woocommerce' ),
			'description'       => esc_html__( 'Number of license keys to create for this variation.', 'woocommerce' ),
			'type'              => 'number',
			'custom_attributes' => [ 'min' => '1' ],
			'value'             => get_post_meta( $variation->ID, '_keevault_license_quantity', true )
		] );
	}

	public function save_keevault_variation_settings( $variation_id, $i ): void {
		$keevault_product_id       = isset( $_POST[ '_keevault_product_id_' . $variation_id ] ) ? sanitize_text_field( $_POST[ '_keevault_product_id_' . $variation_id ] ) : '';
		$keevault_license_quantity = isset( $_POST[ '_keevault_license_quantity_' . $variation_id ] ) ? intval( $_POST[ '_keevault_license_quantity_' . $variation_id ] ) : 1;

		update_post_meta( $variation_id, '_keevault_product_id', $keevault_product_id );
		update_post_meta( $variation_id, '_keevault_license_quantity', $keevault_license_quantity );
	}

	public function create_contract_on_order( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( in_array( $order->get_status(), [ 'processing', 'completed' ] ) ) {
			$api_key = get_option( 'keevault_lm_contracts_api_key' );
			$api_url = get_option( 'keevault_lm_contracts_api_url' );

			foreach ( $order->get_items() as $item ) {
				$product_id   = $item->get_product_id();
				$variation_id = $item->get_variation_id();

				// Get Keevault details for either simple or variation product
				$keevault_product_id = $variation_id ? get_post_meta( $variation_id, '_keevault_product_id', true ) : get_post_meta( $product_id, '_keevault_product_id', true );
				$license_quantity    = $variation_id ? get_post_meta( $variation_id, '_keevault_license_quantity', true ) : get_post_meta( $product_id, '_keevault_license_quantity', true );

				if ( $keevault_product_id && $license_quantity ) {
					for ( $i = 1; $i <= $item->get_quantity(); $i ++ ) {
						if ( ! $this->contracts_created( $order_id, $item->get_id(), $i ) ) {
							$this->create_keevault_contract( $api_key, $api_url, $keevault_product_id, $license_quantity, $order, $item->get_id(), $i );
						}
					}
				}
			}
		}
	}

	private function create_keevault_contract( $api_key, $api_url, $product_id, $license_quantity, $order, $item_id, $item_number ): void {
		$endpoint = '/api/v1/create-contract';
		$body     = [
			'api_key'               => $api_key,
			'product_id'            => $product_id,
			'license_keys_quantity' => $license_quantity,

			'contract_key'         => $this->uuid(),
			'contract_name'        => 'Order #' . $order->get_id(),
			'contract_information' => 'Order #' . $order->get_id(),
			'status'               => 'active',
			'can_get_info'         => '1',
			'can_generate'         => '1',
			'can_destroy'          => '1',
			'can_destroy_all'      => '1'
		];

		$retry_limit = 0;

		do {
			$response      = wp_remote_post( $api_url . $endpoint, [
				'method' => 'POST',
				'body'   => $body,
			] );
			$response_body = null;

			if ( ! is_wp_error( $response ) ) {
				$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
			}

			$retry_limit ++;
		} while ( ( is_wp_error( $response ) || isset( $response_body['response']['errors']['contract_key'] ) ) && $retry_limit < 15 );

		if ( ! is_wp_error( $response ) && isset( $response_body['response']['code'] ) && $response_body['response']['code'] == 841 ) {
			global $wpdb;

			$response_body['response']['contract']['order_id']    = $order->get_id();
			$response_body['response']['contract']['item_id']     = $item_id;
			$response_body['response']['contract']['item_number'] = $item_number;
			unset( $response_body['response']['contract']['license_keys_count'] );

			$wpdb->insert( $wpdb->prefix . 'keevault_contracts', $response_body['response']['contract'] );
		}
	}

	private function contracts_created( $order_id, $item_id, $item_number ): bool {
		global $wpdb;

		return $wpdb->get_var( "SELECT COUNT(*) FROM " . $wpdb->prefix . "keevault_contracts WHERE order_id = $order_id AND item_id = $item_id AND item_number = $item_number" ) >= 1;
	}

	private function uuid(): string {
		$data = random_bytes( 16 );

		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	// Register activation hook for creating the database table
	public static function activate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'keevault_contracts';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		            order_id INT UNSIGNED NOT NULL,
		            item_id INT UNSIGNED NOT NULL,
		            item_number INT UNSIGNED NOT NULL,
		            name VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
		            information TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
		            contract_key VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
		            license_keys_quantity INT UNSIGNED NOT NULL,
		            product_id BIGINT UNSIGNED NOT NULL,
		            can_get_info TINYINT(1) NOT NULL DEFAULT '1',
		            can_generate TINYINT(1) NOT NULL DEFAULT '1',
		            can_destroy TINYINT(1) NOT NULL DEFAULT '1',
		            can_destroy_all TINYINT(1) NOT NULL DEFAULT '0',
		            status VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
		            created_at TIMESTAMP NULL DEFAULT NULL,
		            updated_at TIMESTAMP NULL DEFAULT NULL,
		            PRIMARY KEY (id)
        	) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}

new Keevault_LM_Contracts_WC_Integration();

// Register the activation hook to create the database table
register_activation_hook( __FILE__, [ 'Keevault_LM_Contracts_WC_Integration', 'activate' ] );