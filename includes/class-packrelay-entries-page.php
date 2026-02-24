<?php
/**
 * Admin entries page controller.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Entries_Page
 *
 * Handles the admin menu page for viewing and managing PackRelay entries.
 */
class PackRelay_Entries_Page {

	/**
	 * Register admin menu pages.
	 */
	public function add_menu_pages() {
		add_menu_page(
			__( 'PackRelay', 'packrelay' ),
			__( 'PackRelay', 'packrelay' ),
			'manage_options',
			'packrelay-entries',
			array( $this, 'render_page' ),
			'dashicons-email-alt'
		);

		add_submenu_page(
			'packrelay-entries',
			__( 'Entries', 'packrelay' ),
			__( 'Entries', 'packrelay' ),
			'manage_options',
			'packrelay-entries',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin styles and scripts for PackRelay pages.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'packrelay' ) ) {
			return;
		}

		wp_enqueue_style(
			'packrelay-google-fonts',
			'https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Roboto:wght@400;500&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'packrelay-admin',
			PACKRELAY_PLUGIN_URL . 'assets/css/packrelay-admin.css',
			array( 'packrelay-google-fonts' ),
			PACKRELAY_VERSION
		);

		wp_enqueue_script(
			'packrelay-admin',
			PACKRELAY_PLUGIN_URL . 'assets/js/packrelay-admin.js',
			array( 'jquery' ),
			PACKRELAY_VERSION,
			true
		);

		wp_localize_script(
			'packrelay-admin',
			'packrelayAdmin',
			array(
				'adminEmail' => get_option( 'admin_email' ),
			)
		);
	}

	/**
	 * Render the entries page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! PackRelay_Activator::is_provider_available() ) {
			$provider = PackRelay_Provider_Factory::create();
			$label    = $provider->get_label();

			echo '<div class="wrap packrelay-wrap">';
			echo '<h1>' . esc_html__( 'PackRelay Entries', 'packrelay' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: form builder name */
				esc_html__( 'PackRelay requires %s to be installed and active. Please install %s to use PackRelay.', 'packrelay' ),
				esc_html( $label ),
				esc_html( $label )
			);
			echo '</p></div>';
			echo '</div>';
			return;
		}

		$action = sanitize_text_field( $_GET['action'] ?? '' );

		if ( 'view' === $action && ! empty( $_GET['entry_id'] ) ) {
			$this->render_detail_page( absint( $_GET['entry_id'] ) );
			return;
		}

		if ( 'delete' === $action && ! empty( $_GET['entry_id'] ) ) {
			$this->handle_delete( absint( $_GET['entry_id'] ) );
		}

		$this->handle_bulk_actions();
		$this->render_list_page();
	}

	/**
	 * Handle CSV export.
	 */
	public function handle_export() {
		if ( ! isset( $_GET['packrelay_export'] ) || '1' !== $_GET['packrelay_export'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'packrelay_export_csv' );

		$provider_filter = sanitize_text_field( $_GET['provider_filter'] ?? '' );
		$source_filter   = sanitize_text_field( $_GET['source_filter'] ?? '' );

		$query_args = array(
			'per_page' => 99999,
			'offset'   => 0,
		);

		if ( $provider_filter ) {
			$query_args['provider'] = $provider_filter;
		}

		if ( 'divi_frontend' === $source_filter ) {
			$query_args['provider'] = 'divi_frontend';
		} elseif ( 'mobile_app' === $source_filter ) {
			$query_args['exclude_provider'] = 'divi_frontend';
		}

		$store   = new PackRelay_Entry_Store();
		$entries = $store->get_entries( $query_args );

		// Collect all unique field labels across entries.
		$all_labels = array();
		foreach ( $entries as $entry ) {
			$fields = json_decode( $entry['fields'], true );
			if ( is_array( $fields ) ) {
				foreach ( array_keys( $fields ) as $label ) {
					if ( ! in_array( $label, $all_labels, true ) ) {
						$all_labels[] = $label;
					}
				}
			}
		}

		$filename = 'packrelay-entries-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// UTF-8 BOM for Excel compatibility.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Header row.
		$headers = array_merge(
			array( 'ID', 'Source', 'Provider', 'Form ID', 'Form Name', 'Page', 'Date', 'IP Address' ),
			$all_labels
		);
		fputcsv( $output, $headers );

		// Data rows.
		foreach ( $entries as $entry ) {
			$fields = json_decode( $entry['fields'], true );
			$source = ( 'divi_frontend' === $entry['provider'] ) ? 'Divi Frontend' : 'Mobile App';
			$row    = array(
				$entry['id'],
				$source,
				$entry['provider'],
				$entry['form_id'],
				$entry['form_name'],
				$entry['page_title'],
				$entry['date_created'],
				$entry['ip_address'],
			);

			foreach ( $all_labels as $label ) {
				$row[] = isset( $fields[ $label ] ) ? $fields[ $label ] : '';
			}

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle single entry deletion.
	 *
	 * @param int $entry_id The entry ID.
	 */
	private function handle_delete( $entry_id ) {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'packrelay_delete_entry_' . $entry_id ) ) {
			return;
		}

		$store = new PackRelay_Entry_Store();
		$store->delete_entry( $entry_id );

		echo '<div class="notice notice-success"><p>' . esc_html__( 'Entry deleted.', 'packrelay' ) . '</p></div>';
	}

	/**
	 * Handle bulk actions.
	 */
	private function handle_bulk_actions() {
		if ( empty( $_POST['entry_ids'] ) || empty( $_POST['action'] ) ) {
			return;
		}

		if ( 'delete' !== $_POST['action'] && 'delete' !== ( $_POST['action2'] ?? '' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'bulk-entries' ) ) {
			return;
		}

		$store = new PackRelay_Entry_Store();
		$ids   = array_map( 'absint', $_POST['entry_ids'] );

		foreach ( $ids as $id ) {
			$store->delete_entry( $id );
		}

		echo '<div class="notice notice-success"><p>';
		printf(
			/* translators: %d: number of entries deleted */
			esc_html__( '%d entries deleted.', 'packrelay' ),
			count( $ids )
		);
		echo '</p></div>';
	}

	/**
	 * Render the list page.
	 */
	private function render_list_page() {
		$list_table = new PackRelay_Entries_List_Table();
		$list_table->prepare_items();

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'packrelay_export' => '1',
					'provider_filter'  => sanitize_text_field( $_GET['provider_filter'] ?? '' ),
					'source_filter'    => sanitize_text_field( $_GET['source_filter'] ?? '' ),
				),
				admin_url( 'admin.php' )
			),
			'packrelay_export_csv'
		);

		echo '<div class="wrap packrelay-wrap">';
		echo '<h1>' . esc_html__( 'PackRelay Entries', 'packrelay' ) . '</h1>';
		echo '<p><a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Export CSV', 'packrelay' ) . '</a></p>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="packrelay-entries" />';
		$list_table->display();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the detail page for a single entry.
	 *
	 * @param int $entry_id The entry ID.
	 */
	private function render_detail_page( $entry_id ) {
		$store = new PackRelay_Entry_Store();
		$entry = $store->get_entry( $entry_id );

		$back_url = admin_url( 'admin.php?page=packrelay-entries' );

		echo '<div class="wrap packrelay-wrap">';
		printf(
			'<h1>%s <a href="%s" class="page-title-action">%s</a></h1>',
			/* translators: %d: entry ID */
			esc_html( sprintf( __( 'Entry #%d', 'packrelay' ), $entry_id ) ),
			esc_url( $back_url ),
			esc_html__( 'Back to Entries', 'packrelay' )
		);

		if ( ! $entry ) {
			echo '<p>' . esc_html__( 'Entry not found.', 'packrelay' ) . '</p>';
			echo '</div>';
			return;
		}

		$provider_labels = array(
			'divi'           => 'Divi',
			'divi_frontend'  => 'Divi',
			'wpforms'        => 'WPForms',
			'gravityforms'   => 'Gravity Forms',
		);

		$source = ( 'divi_frontend' === $entry['provider'] )
			? __( 'Divi Frontend', 'packrelay' )
			: __( 'Mobile App', 'packrelay' );

		echo '<div class="packrelay-detail">';
		echo '<table class="widefat striped">';
		echo '<tbody>';

		echo '<tr><th>' . esc_html__( 'ID', 'packrelay' ) . '</th><td>' . absint( $entry['id'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Source', 'packrelay' ) . '</th><td>' . esc_html( $source ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Provider', 'packrelay' ) . '</th><td>' . esc_html( $provider_labels[ $entry['provider'] ] ?? $entry['provider'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Form ID', 'packrelay' ) . '</th><td>' . esc_html( $entry['form_id'] ) . '</td></tr>';

		if ( ! empty( $entry['form_name'] ) ) {
			echo '<tr><th>' . esc_html__( 'Form Name', 'packrelay' ) . '</th><td>' . esc_html( $entry['form_name'] ) . '</td></tr>';
		}

		if ( ! empty( $entry['page_title'] ) ) {
			echo '<tr><th>' . esc_html__( 'Page', 'packrelay' ) . '</th><td>' . esc_html( $entry['page_title'] ) . '</td></tr>';
		}

		echo '<tr><th>' . esc_html__( 'IP Address', 'packrelay' ) . '</th><td>' . esc_html( $entry['ip_address'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'User Agent', 'packrelay' ) . '</th><td>' . esc_html( $entry['user_agent'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Date', 'packrelay' ) . '</th><td>' . esc_html( $entry['date_created'] ) . '</td></tr>';

		if ( ! empty( $entry['referer_url'] ) ) {
			echo '<tr><th>' . esc_html__( 'Referer', 'packrelay' ) . '</th><td>' . esc_html( $entry['referer_url'] ) . '</td></tr>';
		}

		echo '</tbody>';
		echo '</table>';

		// Fields table.
		$fields = json_decode( $entry['fields'], true );
		if ( is_array( $fields ) && ! empty( $fields ) ) {
			echo '<h2>' . esc_html__( 'Submitted Fields', 'packrelay' ) . '</h2>';
			echo '<table class="widefat striped">';
			echo '<thead><tr><th>' . esc_html__( 'Field', 'packrelay' ) . '</th><th>' . esc_html__( 'Value', 'packrelay' ) . '</th></tr></thead>';
			echo '<tbody>';

			foreach ( $fields as $field_id => $value ) {
				echo '<tr>';
				echo '<td>' . esc_html( $field_id ) . '</td>';
				echo '<td>' . esc_html( $value ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
		}

		echo '</div>';
		echo '</div>';
	}
}
