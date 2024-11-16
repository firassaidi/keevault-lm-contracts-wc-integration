<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;

// Fetch the search query
$search_query = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

// Prepare the query
$table_name = $wpdb->prefix . 'keevault_contracts'; // Replace with your table name
$query      = "SELECT * FROM $table_name";
if ( ! empty( $search_query ) ) {
	$query .= $wpdb->prepare( " WHERE name LIKE %s OR information LIKE %s OR contract_key LIKE %s", "%$search_query%", "%$search_query%", "%$search_query%" );
}
$results = $wpdb->get_results( $query );

// Pagination setup (for example, 10 rows per page)
$page              = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$rows_per_page     = 10;
$total_results     = count( $results );
$total_pages       = ceil( $total_results / $rows_per_page );
$offset            = ( $page - 1 ) * $rows_per_page;
$paginated_results = array_slice( $results, $offset, $rows_per_page );

?>
<div class="wrap woocommerce">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Contracts', 'keevault' ); ?></h1>
	<form method="get" class="search-form wp-clearfix">
		<input type="hidden" name="page" value="keevault-contracts"/>
		<p class="search-box">
			<input type="search" id="post-search-input" name="search" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search by name, information, or contract key', 'keevault' ); ?>"/>
			<button type="submit" class="button"><?php esc_html_e( 'Search', 'keevault' ); ?></button>
		</p>
	</form>
	<table class="wp-list-table widefat fixed striped table-view-list posts">
		<thead>
		<tr>
			<th scope="col" id="order_id" class="manage-column column-order_id"><?php esc_html_e( 'Order ID', 'keevault' ); ?></th>
			<th scope="col" id="user_id" class="manage-column column-user_id"><?php esc_html_e( 'User', 'keevault' ); ?></th>
			<th scope="col" id="name" class="manage-column column-name"><?php esc_html_e( 'Name', 'keevault' ); ?></th>
			<th scope="col" id="information" class="manage-column column-information"><?php esc_html_e( 'Information', 'keevault' ); ?></th>
			<th scope="col" id="contract_key" class="manage-column column-contract_key"><?php esc_html_e( 'Contract Key', 'keevault' ); ?></th>
			<th scope="col" id="license_keys_quantity" class="manage-column column-license_keys_quantity"><?php esc_html_e( 'License Quantity', 'keevault' ); ?></th>
			<th scope="col" id="created_at" class="manage-column column-created_at"><?php esc_html_e( 'Created At', 'keevault' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php if ( $paginated_results ): ?>
			<?php foreach ( $paginated_results as $row ):
				$created_at = ( ! empty( $row->created_at ) ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->created_at ) ) : '';
				?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row->order_id . '&action=edit' ) ); ?>"><?php echo esc_html( $row->order_id ); ?></a></td>
					<td><a href="<?php echo esc_url( get_edit_user_link( $row->user_id ) ); ?>"><?php echo esc_html( get_the_author_meta( 'display_name', $row->user_id ) ); ?></a></td>
					<td><?php echo esc_html( $row->name ); ?></td>
					<td><?php echo esc_html( $row->information ); ?></td>
					<td><?php echo esc_html( $row->contract_key ); ?></td>
					<td><?php echo esc_html( $row->license_keys_quantity ); ?></td>
					<td><?php echo esc_html( $created_at ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr>
				<td colspan="12"><?php esc_html_e( 'No results found.', 'keevault' ); ?></td>
			</tr>
		<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<div class="tablenav">
		<div class="tablenav-pages">
			<?php if ( $total_pages > 1 ): ?>
				<span class="pagination-links">
                    <?php if ( $page > 1 ): ?>
	                    <a class="prev-page button" href="?page=keevault-contracts&paged=<?php echo $page - 1; ?>&search=<?php echo esc_attr( $search_query ); ?>">&laquo; <?php esc_html_e( 'Previous', 'keevault' ); ?></a>
                    <?php endif; ?>
                    <span class="paging-input">
                        <?php printf( esc_html__( 'Page %1$s of %2$s', 'keevault' ), $page, $total_pages ); ?>
                    </span>
                    <?php if ( $page < $total_pages ): ?>
	                    <a class="next-page button" href="?page=keevault-contracts&paged=<?php echo $page + 1; ?>&search=<?php echo esc_attr( $search_query ); ?>"><?php esc_html_e( 'Next', 'keevault' ); ?> &raquo;</a>
                    <?php endif; ?>
                </span>
			<?php endif; ?>
		</div>
	</div>
</div>
