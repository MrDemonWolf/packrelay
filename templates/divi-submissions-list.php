<?php
/**
 * Divi submissions list view template.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 *
 * Variables available:
 * @var array  $entries     Array of entry rows.
 * @var int    $total       Total number of entries matching the filter.
 * @var int    $total_pages Total number of pages.
 * @var int    $paged       Current page number.
 * @var int    $per_page    Entries per page.
 * @var string $form_name   Current form name filter.
 * @var int    $page_id     Current page ID filter.
 * @var array  $forms       Distinct form names for filter dropdown.
 * @var array  $pages       Distinct page_id/page_title pairs for filter dropdown.
 * @var bool   $deleted     Whether an entry was just deleted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap packrelay-submissions-wrap">
	<h1><?php esc_html_e( 'Form Submissions', 'packrelay' ); ?></h1>

	<?php if ( $deleted ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Submission deleted.', 'packrelay' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="packrelay-submissions-actions">
		<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
			<input type="hidden" name="page" value="packrelay-divi-submissions" />

			<select name="form_name">
				<option value=""><?php esc_html_e( 'All Forms', 'packrelay' ); ?></option>
				<?php foreach ( $forms as $fname ) : ?>
					<option value="<?php echo esc_attr( $fname ); ?>" <?php selected( $form_name, $fname ); ?>>
						<?php echo esc_html( $fname ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="page_id">
				<option value=""><?php esc_html_e( 'All Pages', 'packrelay' ); ?></option>
				<?php foreach ( $pages as $pg ) : ?>
					<option value="<?php echo esc_attr( $pg['page_id'] ); ?>" <?php selected( $page_id, $pg['page_id'] ); ?>>
						<?php echo esc_html( $pg['page_title'] ? $pg['page_title'] : '#' . $pg['page_id'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'packrelay' ); ?></button>
		</form>

		<?php
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'packrelay_export' => '1',
					'form_name'        => $form_name,
					'page_id'          => $page_id,
				),
				admin_url( 'admin.php' )
			),
			'packrelay_export_csv'
		);
		?>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button">
			<?php esc_html_e( 'Export CSV', 'packrelay' ); ?>
		</a>
	</div>

	<p class="packrelay-stats">
		<?php
		printf(
			/* translators: %s: number of submissions */
			esc_html( _n( '%s submission', '%s submissions', $total, 'packrelay' ) ),
			esc_html( number_format_i18n( $total ) )
		);
		?>
	</p>

	<?php if ( empty( $entries ) ) : ?>
		<p><?php esc_html_e( 'No submissions found.', 'packrelay' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped packrelay-submissions-table">
			<thead>
				<tr>
					<th class="column-id"><?php esc_html_e( 'ID', 'packrelay' ); ?></th>
					<th class="column-contact"><?php esc_html_e( 'Contact', 'packrelay' ); ?></th>
					<th class="column-form"><?php esc_html_e( 'Form', 'packrelay' ); ?></th>
					<th class="column-page"><?php esc_html_e( 'Page', 'packrelay' ); ?></th>
					<th class="column-date"><?php esc_html_e( 'Submitted', 'packrelay' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'packrelay' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$fields  = json_decode( $entry['fields'], true );
					$name    = PackRelay_Divi_Submissions::extract_name( $fields );
					$email   = PackRelay_Divi_Submissions::extract_email( $fields );
					$view_url = add_query_arg(
						array(
							'page'     => 'packrelay-divi-submissions',
							'action'   => 'view',
							'entry_id' => $entry['id'],
						),
						admin_url( 'admin.php' )
					);
					$delete_url = wp_nonce_url(
						add_query_arg(
							array(
								'packrelay_delete' => '1',
								'entry_id'         => $entry['id'],
							),
							admin_url( 'admin.php' )
						),
						'packrelay_delete_submission_' . $entry['id']
					);
					?>
					<tr>
						<td class="column-id"><?php echo absint( $entry['id'] ); ?></td>
						<td class="column-contact">
							<?php if ( $name ) : ?>
								<span class="contact-name"><?php echo esc_html( $name ); ?></span><br>
							<?php endif; ?>
							<?php if ( $email ) : ?>
								<span class="contact-email"><?php echo esc_html( $email ); ?></span>
							<?php endif; ?>
							<?php if ( ! $name && ! $email ) : ?>
								<span class="contact-name">&mdash;</span>
							<?php endif; ?>
						</td>
						<td class="column-form"><?php echo esc_html( $entry['form_name'] ); ?></td>
						<td class="column-page"><?php echo esc_html( $entry['page_title'] ? $entry['page_title'] : '&mdash;' ); ?></td>
						<td class="column-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['date_created'] ) ) ); ?></td>
						<td class="column-actions">
							<a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'packrelay' ); ?></a>
							|
							<a href="<?php echo esc_url( $delete_url ); ?>" class="delete-link" onclick="return confirm('<?php echo esc_js( __( 'Delete this submission?', 'packrelay' ) ); ?>');"><?php esc_html_e( 'Delete', 'packrelay' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="packrelay-pagination tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $paged,
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
