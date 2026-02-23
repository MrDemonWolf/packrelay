<?php
/**
 * Divi submissions detail view template.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 *
 * Variables available:
 * @var array|null $entry    The entry row, or null if not found.
 * @var int        $entry_id The entry ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back_url = admin_url( 'admin.php?page=packrelay-divi-submissions' );
?>
<div class="wrap packrelay-detail">
	<h1>
		<?php
		printf(
			/* translators: %d: entry ID */
			esc_html__( 'Submission #%d', 'packrelay' ),
			absint( $entry_id )
		);
		?>
		<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
			<?php esc_html_e( 'Back to Submissions', 'packrelay' ); ?>
		</a>
	</h1>

	<?php if ( ! $entry ) : ?>
		<p><?php esc_html_e( 'Submission not found.', 'packrelay' ); ?></p>
	<?php else : ?>
		<?php
		$fields = json_decode( $entry['fields'], true );
		?>

		<?php if ( is_array( $fields ) && ! empty( $fields ) ) : ?>
			<h2><?php esc_html_e( 'Form Data', 'packrelay' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $fields as $label => $value ) : ?>
						<?php
						$display_label = ucwords( str_replace( array( '_', '-' ), ' ', $label ) );
						$is_url        = ( filter_var( $value, FILTER_VALIDATE_URL ) );
						?>
						<tr>
							<th><?php echo esc_html( $display_label ); ?></th>
							<td>
								<?php if ( $is_url ) : ?>
									<a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $value ); ?>
									</a>
								<?php else : ?>
									<?php echo nl2br( esc_html( $value ) ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Submission Details', 'packrelay' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'ID', 'packrelay' ); ?></th>
					<td><?php echo absint( $entry['id'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Form Name', 'packrelay' ); ?></th>
					<td><?php echo esc_html( $entry['form_name'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Form ID', 'packrelay' ); ?></th>
					<td><?php echo esc_html( $entry['form_id'] ); ?></td>
				</tr>
				<?php if ( ! empty( $entry['page_title'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Page', 'packrelay' ); ?></th>
						<td>
							<?php if ( ! empty( $entry['page_id'] ) ) : ?>
								<a href="<?php echo esc_url( get_permalink( $entry['page_id'] ) ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $entry['page_title'] ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $entry['page_title'] ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Date', 'packrelay' ); ?></th>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['date_created'] ) ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'IP Address', 'packrelay' ); ?></th>
					<td><?php echo esc_html( $entry['ip_address'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'User Agent', 'packrelay' ); ?></th>
					<td><?php echo esc_html( $entry['user_agent'] ); ?></td>
				</tr>
				<?php if ( ! empty( $entry['referer_url'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Referer', 'packrelay' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $entry['referer_url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $entry['referer_url'] ); ?>
							</a>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
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
		<p>
			<a href="<?php echo esc_url( $delete_url ); ?>" class="delete-link" onclick="return confirm('<?php echo esc_js( __( 'Delete this submission?', 'packrelay' ) ); ?>');">
				<?php esc_html_e( 'Delete this submission', 'packrelay' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
