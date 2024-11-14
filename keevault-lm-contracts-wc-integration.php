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

		// Add my-account endpoint
		add_action( 'init', [ $this, 'contracts_my_account_endpoint' ] );
		add_filter( 'query_vars', [ $this, 'contracts_query_vars' ], 0 );
		add_action( 'woocommerce_account_contracts_endpoint', [ $this, 'contracts_endpoint_content' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'contracts_my_account_menu_items' ] );

	}

	function contracts_my_account_menu_items( $items ) {
		$logout = $items['customer-logout'];
		unset( $items['customer-logout'] );

		$items['contracts']       = esc_html__( 'Contracts', 'keevault' );
		$items['customer-logout'] = $logout;

		return $items;
	}

	// Content for the contracts endpoint
	public function contracts_endpoint_content(): void {
		global $wpdb;

		// Define the table name
		$table_name = $wpdb->prefix . 'keevault_contracts';

		// Query to fetch all data from the table
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, order_id, name, contract_key, created_at FROM $table_name WHERE user_id = %d",
			get_current_user_id()
		), ARRAY_A );

		// Check if there are any records
		if ( ! empty( $results ) ) {
			echo '<h3>' . esc_html__( 'Contracts Information', 'keevault' ) . '</h3>';

			// Start the WooCommerce-like table structure
			echo '<table class="woocommerce-orders-table woocommerce-orders-table--contracts shop_table shop_table_responsive">';
			echo '<thead>';
			echo '<tr>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-id"><span class="nobr">' . esc_html__( 'Contract ID', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-item-id"><span class="nobr">' . esc_html__( 'Order ID', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-name"><span class="nobr">' . esc_html__( 'Name', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-contract-key"><span class="nobr">' . esc_html__( 'Contract Key', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-created"><span class="nobr">' . esc_html__( 'Created At', 'keevault' ) . '</span></th>';
			echo '</tr>';
			echo '</thead>';

			echo '<tbody>';
			// Loop through the results and output each row in the table
			foreach ( $results as $row ) {
				$created_at = ( ! empty( $row['created_at'] ) ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ) : '';

				echo '<tr class="woocommerce-orders-table__row">';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-id">' . esc_html( $row['id'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-item-id">' . esc_html( $row['order_id'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-name">' . esc_html( $row['name'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-contract-key">' . esc_html( $row['contract_key'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-created">' . esc_html( $created_at ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
		} else {
			echo '<p>' . esc_html__( 'No contracts found.', 'keevault' ) . '</p>';
		}
	}

	// Register new endpoint for My Account
	public function contracts_my_account_endpoint(): void {
		add_rewrite_endpoint( 'contracts', EP_ROOT | EP_PAGES );
	}


	// Ensure endpoint is available on My Account page
	public function contracts_query_vars( $vars ) {
		$vars[] = 'contracts';

		return $vars;
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

	private function create_keevault_contract( $api_key, $api_url, $product_id, $license_quantity, $order, $item_id, $item_number ): void {
		$endpoint = '/api/v1/create-contract';
		$body     = [
			'api_key'               => $api_key,
			'product_id'            => $product_id,
			'license_keys_quantity' => $license_quantity,

			'contract_key'         => $this->uuid(),
			'contract_name'        => 'Contract ' . $order->get_id() . $item_id . $item_number,
			'contract_information' => sprintf( esc_html__( 'Contract create through the WooCommerce integration plugin for Order #%d', 'keevault' ), $order->get_id() ),
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
			$response_body['response']['contract']['user_id']     = get_current_user_id();
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
		add_rewrite_endpoint( 'contracts', EP_ROOT | EP_PAGES );
		flush_rewrite_rules(); // Flush rewrite rules

		global $wpdb;

		$table_name      = $wpdb->prefix . 'keevault_contracts';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		            order_id INT UNSIGNED NOT NULL,
		            item_id INT UNSIGNED NOT NULL,
		            item_number INT UNSIGNED NOT NULL,
		            user_id INT UNSIGNED NOT NULL,
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