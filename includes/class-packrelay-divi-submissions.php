<?php
/**
 * Divi front-end form submission capture and admin page.
 *
 * Hooks into Divi's et_pb_contact_form_submit action to save submissions
 * to the unified entries table and provides an admin UI for viewing them.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Divi_Submissions
 *
 * Captures Divi front-end form submissions and provides admin list/detail/export views.
 */
class PackRelay_Divi_Submissions {

	/**
	 * Entry store instance.
	 *
	 * @var PackRelay_Entry_Store
	 */
	private $entry_store;

	/**
	 * Constructor.
	 *
	 * @param PackRelay_Entry_Store|null $entry_store Optional entry store instance for testing.
	 */
	public function __construct( $entry_store = null ) {
		$this->entry_store = $entry_store ? $entry_store : new PackRelay_Entry_Store();
	}

	/**
	 * Save a Divi front-end form submission.
	 *
	 * Hooked to et_pb_contact_form_submit action.
	 *
	 * @param array  $processed_fields  Array of processed field data from Divi.
	 * @param bool   $et_contact_error  Whether Divi detected validation errors.
	 * @param array  $contact_form_info Form metadata from Divi.
	 */
	public function save_submission( $processed_fields, $et_contact_error, $contact_form_info ) {
		if ( $et_contact_error ) {
			return;
		}

		$fields = array();
		if ( is_array( $processed_fields ) ) {
			foreach ( $processed_fields as $field ) {
				$label = isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '';
				$value = isset( $field['value'] ) ? sanitize_text_field( $field['value'] ) : '';
				if ( '' !== $label ) {
					$fields[ $label ] = $value;
				}
			}
		}

		$form_id    = isset( $contact_form_info['contact_form_unique_id'] ) ? sanitize_text_field( $contact_form_info['contact_form_unique_id'] ) : '';
		$form_number = isset( $contact_form_info['contact_form_number'] ) ? absint( $contact_form_info['contact_form_number'] ) : 0;
		$form_title = isset( $contact_form_info['title'] ) ? sanitize_text_field( $contact_form_info['title'] ) : '';

		$page_id    = get_the_ID() ? absint( get_the_ID() ) : 0;
		$page_title = $page_id ? get_the_title( $page_id ) : '';

		$this->entry_store->add(
			array(
				'provider'    => 'divi_frontend',
				'form_id'     => $form_id ? $form_id : (string) $form_number,
				'form_name'   => $form_title ? $form_title : __( 'Contact Form', 'packrelay' ),
				'page_id'     => $page_id,
				'page_title'  => $page_title,
				'referer_url' => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'fields'      => wp_json_encode( $fields ),
				'ip_address'  => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			)
		);
	}

	/**
	 * Add Form Submissions submenu page under PackRelay.
	 */
	public function add_submenu_page() {
		$settings = PackRelay_Settings::get_settings();
		$provider = $settings['form_provider'] ?? 'divi';

		if ( 'divi' !== $provider ) {
			return;
		}

		add_submenu_page(
			'packrelay-entries',
			__( 'Form Submissions', 'packrelay' ),
			__( 'Form Submissions', 'packrelay' ),
			'manage_options',
			'packrelay-divi-submissions',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin styles for the submissions page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'packrelay-divi-submissions' ) ) {
			return;
		}

		wp_enqueue_style(
			'packrelay-admin',
			PACKRELAY_PLUGIN_URL . 'assets/css/packrelay-admin.css',
			array(),
			PACKRELAY_VERSION
		);
	}

	/**
	 * Render the submissions page — routes to list or detail.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! PackRelay_Activator::is_provider_available() ) {
			$provider = PackRelay_Provider_Factory::create();
			$label    = $provider->get_label();

			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Form Submissions', 'packrelay' ) . '</h1>';
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
			$this->render_detail( absint( $_GET['entry_id'] ) );
			return;
		}

		$this->render_list();
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

		$form_name = sanitize_text_field( $_GET['form_name'] ?? '' );
		$page_id   = absint( $_GET['page_id'] ?? 0 );

		$query_args = array(
			'provider' => 'divi_frontend',
			'per_page' => 99999,
			'offset'   => 0,
		);

		if ( $form_name ) {
			$query_args['form_name'] = $form_name;
		}

		if ( $page_id ) {
			$query_args['page_id'] = $page_id;
		}

		$entries = $this->entry_store->get_entries( $query_args );

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

		$filename = 'packrelay-submissions-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// UTF-8 BOM for Excel compatibility.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Header row.
		$headers = array_merge(
			array( 'ID', 'Form', 'Page', 'Date', 'IP Address' ),
			$all_labels
		);
		fputcsv( $output, $headers );

		// Data rows.
		foreach ( $entries as $entry ) {
			$fields = json_decode( $entry['fields'], true );
			$row    = array(
				$entry['id'],
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
	 */
	public function handle_delete() {
		if ( ! isset( $_GET['packrelay_delete'] ) || empty( $_GET['entry_id'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$entry_id = absint( $_GET['entry_id'] );

		check_admin_referer( 'packrelay_delete_submission_' . $entry_id );

		$this->entry_store->delete_entry( $entry_id );

		wp_redirect( admin_url( 'admin.php?page=packrelay-divi-submissions&deleted=1' ) );
		exit;
	}

	/**
	 * Render the list view.
	 */
	private function render_list() {
		$per_page  = 20;
		$paged     = absint( $_GET['paged'] ?? 1 );
		$paged     = max( 1, $paged );
		$offset    = ( $paged - 1 ) * $per_page;
		$form_name = sanitize_text_field( $_GET['form_name'] ?? '' );
		$page_id   = absint( $_GET['page_id'] ?? 0 );

		$query_args = array(
			'provider' => 'divi_frontend',
			'per_page' => $per_page,
			'offset'   => $offset,
		);

		if ( $form_name ) {
			$query_args['form_name'] = $form_name;
		}

		if ( $page_id ) {
			$query_args['page_id'] = $page_id;
		}

		$entries     = $this->entry_store->get_entries( $query_args );
		$total       = $this->entry_store->count( $query_args );
		$total_pages = ceil( $total / $per_page );
		$forms       = $this->entry_store->get_distinct_forms( 'divi_frontend' );
		$pages       = $this->entry_store->get_distinct_pages( 'divi_frontend' );

		$deleted = ! empty( $_GET['deleted'] );

		include PACKRELAY_PLUGIN_DIR . 'templates/divi-submissions-list.php';
	}

	/**
	 * Render the detail view for a single submission.
	 *
	 * @param int $entry_id The entry ID.
	 */
	private function render_detail( $entry_id ) {
		$entry = $this->entry_store->get_entry( $entry_id );

		include PACKRELAY_PLUGIN_DIR . 'templates/divi-submissions-detail.php';
	}

	/**
	 * Extract a contact name from form fields.
	 *
	 * @param array $fields Decoded fields array.
	 * @return string
	 */
	public static function extract_name( $fields ) {
		if ( ! is_array( $fields ) ) {
			return '';
		}

		$name_keys = array( 'name', 'Name', 'full_name', 'Full Name', 'your_name', 'Your Name', 'first_name', 'First Name' );
		foreach ( $name_keys as $key ) {
			if ( ! empty( $fields[ $key ] ) ) {
				return $fields[ $key ];
			}
		}

		return '';
	}

	/**
	 * Extract a contact email from form fields.
	 *
	 * @param array $fields Decoded fields array.
	 * @return string
	 */
	public static function extract_email( $fields ) {
		if ( ! is_array( $fields ) ) {
			return '';
		}

		$email_keys = array( 'email', 'Email', 'email_address', 'Email Address', 'your_email', 'Your Email' );
		foreach ( $email_keys as $key ) {
			if ( ! empty( $fields[ $key ] ) ) {
				return $fields[ $key ];
			}
		}

		return '';
	}
}
