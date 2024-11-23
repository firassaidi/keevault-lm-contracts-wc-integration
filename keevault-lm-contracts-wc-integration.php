<?php
/*
Plugin Name: Keevault License Manager - Contracts WooCommerce Integration
Description: Creates Keevault contracts when WooCommerce products are purchased.
Version: 1.0.6
Author: Firas Saidi
*/

defined( 'ABSPATH' ) || exit;
defined( 'KEEVAULT_LM_CONTRACTS' ) || define( 'KEEVAULT_LM_CONTRACTS', __DIR__ );
defined( 'KEEVAULT_LM_CONTRACTS_BASE' ) || define( 'KEEVAULT_LM_CONTRACTS_BASE', plugin_dir_url( __FILE__ ) );

class Keevault_LM_Contracts_WC_Integration {

	public function __construct() {
		// Order Hook for contract creation
		add_action( 'woocommerce_thankyou', [ $this, 'create_contract_on_order' ] );

		if ( is_user_logged_in() ) {
			// Add my-account endpoint
			add_action( 'init', [ $this, 'contracts_my_account_endpoint' ] );
			add_filter( 'query_vars', [ $this, 'contracts_query_vars' ], 0 );
			add_action( 'woocommerce_account_contracts_endpoint', [ $this, 'contracts_endpoint_content' ] );
			add_filter( 'woocommerce_account_menu_items', [ $this, 'contracts_my_account_menu_items' ] );

			// Show contract details on the order details page
			add_action( 'woocommerce_after_order_details', [ $this, 'show_contract_details_on_the_order_page' ] );
			add_action( 'wp_ajax_get_contract_details', [ $this, 'get_contract_details' ] );
		}

		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			// If the settings menu and fields were not already added by another Keevault plugin, add them.
			add_action( 'admin_menu', [ $this, 'add_keevault_menu' ] );

			if ( ! defined( 'KEEVAULT_LM' ) ) {
				add_action( 'admin_init', [ $this, 'keevault_settings_init' ] );
			}

			// Product-specific Keevault settings
			add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_keevault_product_settings' ] );
			add_action( 'woocommerce_process_product_meta', [ $this, 'save_keevault_product_settings' ] );

			// Add settings for product variations
			add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'add_keevault_variation_settings' ], 10, 3 );
			add_action( 'woocommerce_save_product_variation', [ $this, 'save_keevault_variation_settings' ], 10, 2 );

			// Admin scripts
			add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
			add_action( 'wp_ajax_select2_user_search', [ $this, 'handle_ajax_user_search' ] );
		}
	}

	public function admin_enqueue_scripts(): void {
		// Enqueue Select2 CSS
		wp_enqueue_style( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css' );

		// Enqueue Select2 JS
		wp_enqueue_script( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array( 'jquery' ), null, true );

		// Enqueue the custom script for AJAX user search
		wp_enqueue_script( 'select2-ajax-users', KEEVAULT_LM_CONTRACTS_BASE . '/assets/js/select2-ajax-users.js', array( 'jquery', 'select2' ), null, true );

		// Localize the script with AJAX URL and nonce
		wp_localize_script( 'select2-ajax-users', 'select2AjaxUsers', array(
			'ajax_url'                => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'select2_users_nonce' ),
			'select_user_placeholder' => esc_html__( 'Select User', 'keevault' ),
		) );
	}

	public function handle_ajax_user_search(): void {
		// Check nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'select2_users_nonce' ) ) {
			die( 'Permission Denied' );
		}

		// Get the search query
		$search_query = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';

		// Query users
		$args = array(
			'search'         => "*" . esc_attr( $search_query ) . "*",
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email', 'display_name' ),
			'fields'         => array( 'ID', 'display_name' ),
			'number'         => 20, // Limit to 20 results
		);

		$users   = get_users( $args );
		$results = array();

		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => $user->ID,
				'text' => $user->display_name,
			);
		}

		wp_send_json_success( $results );
	}

	public function show_contract_details_on_the_order_page( $order ): void {
		// Enqueue the JavaScript for AJAX
		wp_enqueue_script( 'show-contract-details-js', plugin_dir_url( __FILE__ ) . 'assets/js/show-contract-details.js', array( 'jquery' ), '1.0', true );

		// Pass order ID and AJAX URL to JavaScript
		wp_localize_script( 'show-contract-details-js', 'contractDetailsData', array(
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'order_id'              => $order->get_id(),
			'contract_id'           => esc_html__( 'ID', 'keevault' ),
			'no_contracts_found'    => esc_html__( 'No contract details available for this order.', 'keevault' ),
			'contract_name'         => esc_html__( 'Name', 'keevault' ),
			'contract_key'          => esc_html__( 'Contract Key', 'keevault' ),
			'license_keys_quantity' => esc_html__( 'License Keys Quantity', 'keevault' ),
			'contract_status'       => esc_html__( 'Status', 'keevault' ),
			'failed_to_load'        => esc_html__( 'Failed to load contract details. Please try again later.', 'keevault' ),
		) );

		// Add a container where contract details will be displayed
		echo '<h2>' . esc_html__( 'Contract Details', 'keevault' ) . '</h2>';
		echo '<div id="contract-details-container">' . esc_html__( 'Loading contract details...', 'keevault' ) . '</div>';
	}

	public function get_contract_details(): void {
		global $wpdb;

		// Verify that the user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( esc_html__( 'You do not have permission to view this content.', 'keevault' ) );
		}

		// Get the current user ID
		$current_user_id = get_current_user_id();

		// Get order ID from AJAX request
		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

		// Return error if no valid order ID
		if ( ! $order_id ) {
			wp_send_json_error( esc_html__( 'Invalid order ID.', 'keevault' ) );
		}

		// Get the order object
		$order = wc_get_order( $order_id );

		// Check if the order exists
		if ( ! $order ) {
			wp_send_json_error( esc_html__( 'Order not found.', 'keevault' ) );
		}

		// Check if the current user is either an admin or the order owner
		if ( ! current_user_can( 'manage_woocommerce' ) && $order->get_user_id() !== $current_user_id ) {
			wp_send_json_error( esc_html__( 'You do not have permission to view this content.', 'keevault' ) );
		}

		// Query the table for contract data related to the order
		$table_name = $wpdb->prefix . 'keevault_contracts';
		$contracts  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE order_id = %d ORDER BY id DESC",
			$order_id
		) );

		// Check if contracts were found
		if ( ! $contracts ) {
			wp_send_json_error( esc_html__( 'No contracts found for this order.', 'keevault' ) );
		}

		// Return contract data as JSON
		wp_send_json_success( $contracts );
	}


	public function contracts_my_account_menu_items( $items ) {
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

		// Get the current page from the URL (default to 1 if not set)
		$current_page = isset( $_GET['contracts-page'] ) ? max( 1, intval( $_GET['contracts-page'] ) ) : 1;
		$per_page     = 10; // Number of contracts per page

		// Calculate the offset for the SQL query
		$offset = ( $current_page - 1 ) * $per_page;

		// Query to fetch total number of records for pagination
		$total_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
			get_current_user_id()
		) );

		// Query to fetch paginated data from the table
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, order_id, name, contract_key, license_keys_quantity, created_at 
        FROM $table_name 
        WHERE user_id = %d 
        ORDER BY id DESC
        LIMIT %d OFFSET %d ",
			get_current_user_id(),
			$per_page,
			$offset
		), ARRAY_A );

		// Check if there are any records
		if ( ! empty( $results ) ) {
			echo '<h3>' . esc_html__( 'Contracts Information', 'keevault' ) . '</h3>';

			// Start the WooCommerce-like table structure
			echo '<table class="woocommerce-orders-table woocommerce-orders-table--contracts shop_table shop_table_responsive">';
			echo '<thead>';
			echo '<tr>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-item-id"><span class="nobr">' . esc_html__( 'Order ID', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-name"><span class="nobr">' . esc_html__( 'Name', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-contract-key"><span class="nobr">' . esc_html__( 'Contract Key', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-contract-key"><span class="nobr">' . esc_html__( 'License Keys Quantity', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-created"><span class="nobr">' . esc_html__( 'Created At', 'keevault' ) . '</span></th>';
			echo '</tr>';
			echo '</thead>';

			echo '<tbody>';
			// Loop through the results and output each row in the table
			foreach ( $results as $row ) {
				$created_at = ( ! empty( $row['created_at'] ) ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ) : '';

				echo '<tr class="woocommerce-orders-table__row">';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-item-id">' . esc_html( $row['order_id'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-name">' . esc_html( $row['name'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-contract-key">' . esc_html( $row['contract_key'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-contract-key">' . esc_html( $row['license_keys_quantity'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-created">' . esc_html( $created_at ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';

			// Pagination logic using WooCommerce's pagination function
			$total_pages = ceil( $total_count / $per_page );

			// Only show pagination if there are multiple pages
			if ( $total_pages > 1 ) {
				// Define previous and next page URLs
				$prev_link = $current_page > 1 ? esc_url( add_query_arg( 'contracts-page', $current_page - 1 ) ) : '';
				$next_link = $current_page < $total_pages ? esc_url( add_query_arg( 'contracts-page', $current_page + 1 ) ) : '';

				// Display pagination buttons
				echo '<div class="woocommerce-pagination">';

				// Previous page button
				if ( $prev_link ) {
					echo '<a class="woocommerce-button button" href="' . $prev_link . '">' . __( 'Previous', 'keevault' ) . '</a>';
				}

				// Page numbers logic
				$start_page = max( 1, $current_page - 1 ); // Start from 1 or 1 page before current page
				$end_page   = min( $total_pages, $current_page + 1 ); // End at current page + 1 or the total number of pages

				// Adjust the range if the current page is near the beginning or end
				if ( $current_page == 1 ) {
					$end_page = min( 3, $total_pages ); // If we're on the first page, show the first 3 pages
				}
				if ( $current_page == $total_pages ) {
					$start_page = max( $total_pages - 2, 1 ); // If we're on the last page, show the last 3 pages
				}

				// Loop through the pages and display the page numbers (3 pages at most)
				for ( $i = $start_page; $i <= $end_page; $i ++ ) {
					// Highlight the current page
					$current = ( $i == $current_page ) ? ' style="background-color: #ddd;"' : '';

					// Output page number link
					echo '<a class="woocommerce-button button"' . $current . ' href="' . esc_url( add_query_arg( 'contracts-page', $i ) ) . '">' . $i . '</a>';
				}

				// Next page button
				if ( $next_link ) {
					echo '<a class="woocommerce-button button" href="' . $next_link . '">' . __( 'Next', 'keevault' ) . '</a>';
				}

				echo '</div>';
			}
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
		$keevault_menu_exists = $this->is_top_level_menu_slug_exists( 'keevault' );

		if ( ! $keevault_menu_exists ) {
			add_menu_page(
				esc_html__( 'Keevault', 'keevault' ),
				esc_html__( 'Keevault', 'keevault' ),
				'manage_options',
				'keevault',
				'__return_null',
				'dashicons-admin-network',
				30
			);
		}

		add_submenu_page(
			'keevault',
			esc_html__( 'Contracts', 'keevault' ),
			esc_html__( 'Contracts', 'keevault' ),
			'manage_options',
			'keevault-contracts',
			[ $this, 'contracts_page' ],
			40
		);

		if ( $keevault_menu_exists ) {
			add_submenu_page(
				'keevault',
				esc_html__( 'Settings', 'keevault' ),
				esc_html__( 'Settings', 'keevault' ),
				'manage_options',
				'keevault-contracts-settings',
				[ $this, 'settings_page' ],
				50
			);
		}

		remove_submenu_page( 'keevault', 'keevault' );
	}

	public function keevault_settings_init(): void {
		register_setting( 'keevault_lm_api_settings', 'keevault_lm_api_key' );
		register_setting( 'keevault_lm_api_settings', 'keevault_lm_api_url' );

		add_settings_section( 'keevault_lm_api_section', esc_html__( 'Keevault API Configuration', 'keevault' ), null, 'keevault_lm_api_settings' );

		add_settings_field(
			'keevault_lm_api_key',
			esc_html__( 'API Key', 'keevault' ),
			function () {
				$keevault_lm_api_key = get_option( 'keevault_lm_api_key' );
				echo "<input type='text' class='regular-text' name='keevault_lm_api_key' value='{$keevault_lm_api_key}' />";
			},
			'keevault_lm_api_settings',
			'keevault_lm_api_section',
		);

		add_settings_field(
			'keevault_lm_api_url',
			esc_html__( 'API URL', 'keevault' ),
			function () {
				$keevault_lm_api_url = get_option( 'keevault_lm_api_url' );
				echo "<input type='text' class='regular-text' name='keevault_lm_api_url' value='{$keevault_lm_api_url}' />";
			},
			'keevault_lm_api_settings',
			'keevault_lm_api_section'
		);
	}

	public function contracts_page(): void {
		include KEEVAULT_LM_CONTRACTS . '/dashboard/pages/contracts.php';
	}

	public function settings_page(): void {
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'keevault_lm_api_settings' );
			do_settings_sections( 'keevault_lm_api_settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	public function add_keevault_product_settings(): void {
		echo '<div class="options_group show_if_simple">';

		woocommerce_wp_text_input( [
			'id'          => '_keevault_product_id',
			'label'       => esc_html__( 'Keevault Product ID For Contracts', 'keevault' ),
			'desc_tip'    => 'true',
			'description' => esc_html__( 'Keevault Product ID for license creation.', 'keevault' ),
		] );

		woocommerce_wp_text_input( [
			'id'                => '_keevault_license_quantity',
			'label'             => esc_html__( 'Contract License Keys Quantity', 'keevault' ),
			'desc_tip'          => 'true',
			'description'       => esc_html__( 'Number of license keys to create per purchase.', 'keevault' ),
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

	public function add_keevault_variation_settings( $loop, $variation_data, $variation ): void {
		echo '<div class="options_group show_if_variable">';

		woocommerce_wp_text_input( [
			'id'          => '_keevault_product_id_' . $variation->ID,
			'label'       => esc_html__( 'Keevault Product ID For Contracts', 'keevault' ),
			'description' => esc_html__( 'Keevault Product ID for this variation.', 'keevault' ),
			'value'       => get_post_meta( $variation->ID, '_keevault_product_id', true )
		] );

		woocommerce_wp_text_input( [
			'id'                => '_keevault_license_quantity_' . $variation->ID,
			'label'             => esc_html__( 'Contract License Keys Quantity', 'keevault' ),
			'description'       => esc_html__( 'Number of license keys to create for this variation.', 'keevault' ),
			'type'              => 'number',
			'custom_attributes' => [ 'min' => '1' ],
			'value'             => get_post_meta( $variation->ID, '_keevault_license_quantity', true )
		] );

		echo '</div>';
	}

	public function save_keevault_variation_settings( $variation_id, $i ): void {
		$keevault_product_id       = isset( $_POST[ '_keevault_product_id_' . $variation_id ] ) ? sanitize_text_field( $_POST[ '_keevault_product_id_' . $variation_id ] ) : '';
		$keevault_license_quantity = isset( $_POST[ '_keevault_license_quantity_' . $variation_id ] ) ? intval( $_POST[ '_keevault_license_quantity_' . $variation_id ] ) : 1;

		update_post_meta( $variation_id, '_keevault_product_id', $keevault_product_id );
		update_post_meta( $variation_id, '_keevault_license_quantity', $keevault_license_quantity );
	}

	public function create_contract_on_order( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order->get_status() == 'processing' || $order->get_status() == 'completed' ) {
			$api_key = get_option( 'keevault_lm_api_key' );
			$api_url = get_option( 'keevault_lm_api_url' );

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
				//'sslverify' => false
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
			unset( $response_body['response']['contract']['id'] );

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

	public function is_top_level_menu_slug_exists( $slug ): bool {
		global $menu;

		// Loop through the top-level menus
		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[2] ) && $menu_item[2] === $slug ) {
				return true;
			}
		}

		return false;
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

function keevault_lm_contracts_integration_init(): void {
	new Keevault_LM_Contracts_WC_Integration();
}

add_action( 'plugins_loaded', 'keevault_lm_contracts_integration_init' );

// Register the activation hook to create the database table
register_activation_hook( __FILE__, [ 'Keevault_LM_Contracts_WC_Integration', 'activate' ] );