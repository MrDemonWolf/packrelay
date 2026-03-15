<?php
/**
 * Custom entry storage table.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Entry_Store
 *
 * Manages the wp_packrelay_entries custom table for unified entry storage.
 */
class PackRelay_Entry_Store {

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'packrelay_entries';
	}

	/**
	 * Create the entries table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(20) NOT NULL DEFAULT '',
			form_id varchar(50) NOT NULL DEFAULT '',
			form_name varchar(255) NOT NULL DEFAULT '',
			page_id bigint(20) unsigned NOT NULL DEFAULT 0,
			page_title varchar(255) NOT NULL DEFAULT '',
			referer_url text NOT NULL,
			fields longtext NOT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text NOT NULL,
			date_created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY provider (provider),
			KEY form_id (form_id),
			KEY date_created (date_created),
			KEY provider_form_id (provider, form_id),
			KEY provider_date_created (provider, date_created)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add an entry to the table.
	 *
	 * @param array $data Entry data with keys: provider, form_id, fields, ip_address, user_agent.
	 * @return int|false The entry ID or false on failure.
	 */
	public function add( $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::get_table_name(),
			array(
				'provider'     => sanitize_text_field( $data['provider'] ?? '' ),
				'form_id'      => sanitize_text_field( $data['form_id'] ?? '' ),
				'form_name'    => sanitize_text_field( $data['form_name'] ?? '' ),
				'page_id'      => absint( $data['page_id'] ?? 0 ),
				'page_title'   => sanitize_text_field( $data['page_title'] ?? '' ),
				'referer_url'  => sanitize_text_field( $data['referer_url'] ?? '' ),
				'fields'       => $data['fields'] ?? '{}',
				'ip_address'   => sanitize_text_field( $data['ip_address'] ?? '' ),
				'user_agent'   => sanitize_text_field( $data['user_agent'] ?? '' ),
				'date_created' => $data['date_created'] ?? current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get entries with optional filters.
	 *
	 * @param array $args Query arguments: provider, form_id, form_name, page_id, per_page, offset, orderby, order.
	 * @return array
	 */
	public function get_entries( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'provider'  => '',
			'form_id'   => '',
			'form_name' => '',
			'page_id'   => 0,
			'per_page'  => 20,
			'offset'    => 0,
			'orderby'   => 'id',
			'order'     => 'DESC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = self::get_table_name();
		$where = array();
		$values = array();

		if ( ! empty( $args['provider'] ) ) {
			$where[]  = 'provider = %s';
			$values[] = $args['provider'];
		}

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %s';
			$values[] = $args['form_id'];
		}

		if ( ! empty( $args['form_name'] ) ) {
			$where[]  = 'form_name = %s';
			$values[] = $args['form_name'];
		}

		if ( ! empty( $args['page_id'] ) ) {
			$where[]  = 'page_id = %d';
			$values[] = absint( $args['page_id'] );
		}

		if ( ! empty( $args['exclude_provider'] ) ) {
			$where[]  = 'provider != %s';
			$values[] = $args['exclude_provider'];
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		$allowed_orderby = array( 'id', 'provider', 'form_id', 'date_created' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'id';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM $table $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";

		$values[] = absint( $args['per_page'] );
		$values[] = absint( $args['offset'] );

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get a single entry by ID.
	 *
	 * @param int $id The entry ID.
	 * @return array|null
	 */
	public function get_entry( $id ) {
		global $wpdb;

		$table = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ),
			ARRAY_A
		);
	}

	/**
	 * Delete an entry by ID.
	 *
	 * @param int $id The entry ID.
	 * @return bool
	 */
	public function delete_entry( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			self::get_table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Count entries with optional filters.
	 *
	 * @param array $args Query arguments: provider, form_id, form_name, page_id.
	 * @return int
	 */
	public function count( $args = array() ) {
		global $wpdb;

		$table  = self::get_table_name();
		$where  = array();
		$values = array();

		if ( ! empty( $args['provider'] ) ) {
			$where[]  = 'provider = %s';
			$values[] = $args['provider'];
		}

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %s';
			$values[] = $args['form_id'];
		}

		if ( ! empty( $args['form_name'] ) ) {
			$where[]  = 'form_name = %s';
			$values[] = $args['form_name'];
		}

		if ( ! empty( $args['page_id'] ) ) {
			$where[]  = 'page_id = %d';
			$values[] = absint( $args['page_id'] );
		}

		if ( ! empty( $args['exclude_provider'] ) ) {
			$where[]  = 'provider != %s';
			$values[] = $args['exclude_provider'];
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		$sql = "SELECT COUNT(*) FROM $table $where_sql";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get distinct form names for a given provider.
	 *
	 * @param string $provider The provider slug.
	 * @return array List of form_name values.
	 */
	public function get_distinct_forms( $provider ) {
		global $wpdb;

		$table = self::get_table_name();

		$sql = $wpdb->prepare(
			"SELECT DISTINCT form_name FROM $table WHERE provider = %s AND form_name != '' ORDER BY form_name ASC",
			$provider
		);

		$results = $wpdb->get_col( $sql );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get distinct page_id/page_title pairs for a given provider.
	 *
	 * @param string $provider The provider slug.
	 * @return array List of arrays with page_id and page_title keys.
	 */
	public function get_distinct_pages( $provider ) {
		global $wpdb;

		$table = self::get_table_name();

		$sql = $wpdb->prepare(
			"SELECT DISTINCT page_id, page_title FROM $table WHERE provider = %s AND page_id > 0 ORDER BY page_title ASC",
			$provider
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}
}
