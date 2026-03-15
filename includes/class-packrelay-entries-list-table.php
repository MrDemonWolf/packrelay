<?php
/**
 * Entries list table for admin.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class PackRelay_Entries_List_Table
 *
 * WP_List_Table subclass for displaying PackRelay entries.
 */
class PackRelay_Entries_List_Table extends \WP_List_Table {

	/**
	 * Entry store instance.
	 *
	 * @var PackRelay_Entry_Store
	 */
	private $store;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			)
		);

		$this->store = new PackRelay_Entry_Store();
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'id'           => __( 'ID', 'packrelay' ),
			'source'       => __( 'Source', 'packrelay' ),
			'provider'     => __( 'Provider', 'packrelay' ),
			'form_id'      => __( 'Form ID', 'packrelay' ),
			'fields'       => __( 'Fields', 'packrelay' ),
			'ip_address'   => __( 'IP Address', 'packrelay' ),
			'date_created' => __( 'Date', 'packrelay' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'id'           => array( 'id', true ),
			'provider'     => array( 'provider', false ),
			'form_id'      => array( 'form_id', false ),
			'date_created' => array( 'date_created', false ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'packrelay' ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item The entry data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="entry_ids[]" value="%d" />', absint( $item['id'] ) );
	}

	/**
	 * ID column with row actions.
	 *
	 * @param array $item The entry data.
	 * @return string
	 */
	public function column_id( $item ) {
		$view_url   = PackRelay_Entries_Page::get_view_url( $item['id'] );
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'     => 'packrelay-entries',
					'action'   => 'delete',
					'entry_id' => $item['id'],
				),
				admin_url( 'admin.php' )
			),
			'packrelay_delete_entry_' . $item['id']
		);

		$actions = array(
			'view'   => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), __( 'View', 'packrelay' ) ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_js( __( 'Are you sure?', 'packrelay' ) ), __( 'Delete', 'packrelay' ) ),
		);

		return sprintf( '#%d %s', absint( $item['id'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Source column — shows where the submission came from.
	 *
	 * @param array $item The entry data.
	 * @return string
	 */
	public function column_source( $item ) {
		if ( 'divi_frontend' === $item['provider'] ) {
			return esc_html__( 'Divi Frontend', 'packrelay' );
		}

		return esc_html__( 'Mobile App', 'packrelay' );
	}

	/**
	 * Provider column.
	 *
	 * @param array $item The entry data.
	 * @return string
	 */
	public function column_provider( $item ) {
		$labels = array(
			'divi'           => 'Divi',
			'divi_frontend'  => 'Divi',
			'wpforms'        => 'WPForms',
			'gravityforms'   => 'Gravity Forms',
		);

		return esc_html( $labels[ $item['provider'] ] ?? $item['provider'] );
	}

	/**
	 * Form ID column.
	 *
	 * @param array $item The entry data.
	 * @return string
	 */
	public function column_form_id( $item ) {
		return esc_html( $item['form_id'] );
	}

	/**
	 * Fields column (truncated preview).
	 *
	 * @param array $item The entry data.
	 * @return string
	 */
	public function column_fields( $item ) {
		$fields = json_decode( $item['fields'], true );
		if ( ! is_array( $fields ) ) {
			return '&mdash;';
		}

		$preview = array();
		foreach ( $fields as $key => $value ) {
			$preview[] = esc_html( $key ) . ': ' . esc_html( mb_strimwidth( (string) $value, 0, 30, '...' ) );
		}

		return implode( '<br>', array_slice( $preview, 0, 3 ) );
	}

	/**
	 * IP Address column.
	 *
	 * @param array $item The entry data.
	 * @return string
	 */
	public function column_ip_address( $item ) {
		return esc_html( $item['ip_address'] );
	}

	/**
	 * Date column.
	 *
	 * @param array $item The entry data.
	 * @return string
	 */
	public function column_date_created( $item ) {
		return esc_html( $item['date_created'] );
	}

	/**
	 * Extra table navigation (provider and source filters).
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_provider = sanitize_text_field( $_GET['provider_filter'] ?? '' );
		$current_source   = sanitize_text_field( $_GET['source_filter'] ?? '' );
		?>
		<div class="alignleft actions">
			<select name="provider_filter">
				<option value=""><?php esc_html_e( 'All Providers', 'packrelay' ); ?></option>
				<option value="divi" <?php selected( $current_provider, 'divi' ); ?>>Divi</option>
				<option value="wpforms" <?php selected( $current_provider, 'wpforms' ); ?>>WPForms</option>
				<option value="gravityforms" <?php selected( $current_provider, 'gravityforms' ); ?>>Gravity Forms</option>
			</select>
			<select name="source_filter">
				<option value=""><?php esc_html_e( 'All Sources', 'packrelay' ); ?></option>
				<option value="mobile_app" <?php selected( $current_source, 'mobile_app' ); ?>><?php esc_html_e( 'Mobile App', 'packrelay' ); ?></option>
				<option value="divi_frontend" <?php selected( $current_source, 'divi_frontend' ); ?>><?php esc_html_e( 'Divi Frontend', 'packrelay' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'packrelay' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$per_page = 20;
		$page_num = $this->get_pagenum();
		$offset   = ( $page_num - 1 ) * $per_page;

		$args = array(
			'per_page' => $per_page,
			'offset'   => $offset,
			'orderby'  => sanitize_text_field( $_GET['orderby'] ?? 'id' ),
			'order'    => sanitize_text_field( $_GET['order'] ?? 'DESC' ),
		);

		if ( ! empty( $_GET['provider_filter'] ) ) {
			$args['provider'] = sanitize_text_field( $_GET['provider_filter'] );
		}

		if ( ! empty( $_GET['source_filter'] ) ) {
			$source = sanitize_text_field( $_GET['source_filter'] );
			if ( 'divi_frontend' === $source ) {
				$args['provider'] = 'divi_frontend';
			} elseif ( 'mobile_app' === $source ) {
				$args['exclude_provider'] = 'divi_frontend';
			}
		}

		if ( ! empty( $_GET['form_id_filter'] ) ) {
			$args['form_id'] = sanitize_text_field( $_GET['form_id_filter'] );
		}

		$this->items = $this->store->get_entries( $args );
		$total_items = $this->store->count( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Message when no items found.
	 */
	public function no_items() {
		esc_html_e( 'No entries found.', 'packrelay' );
	}
}
