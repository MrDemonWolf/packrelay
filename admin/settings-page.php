<?php
/**
 * Settings page template.
 *
 * @package PackRelay
 * @copyright 2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$packrelay_provider_available = PackRelay_Activator::is_provider_available();
$packrelay_provider           = PackRelay_Provider_Factory::create();
?>
<div class="wrap packrelay-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="packrelay-header">
		<div class="packrelay-header-icon">
			<span class="dashicons dashicons-rest-api"></span>
		</div>
		<div class="packrelay-header-info">
			<h2><?php esc_html_e( 'PackRelay', 'packrelay' ); ?></h2>
			<div class="packrelay-header-meta">
				<span class="packrelay-version-badge">v<?php echo esc_html( PACKRELAY_VERSION ); ?></span>
				<?php if ( $packrelay_provider_available ) : ?>
					<span class="packrelay-status-pill active"><?php esc_html_e( 'Active', 'packrelay' ); ?></span>
					<span class="packrelay-provider-badge"><?php echo esc_html( $packrelay_provider->get_label() ); ?></span>
				<?php else : ?>
					<span class="packrelay-status-pill inactive"><?php esc_html_e( 'Provider Missing', 'packrelay' ); ?></span>
				<?php endif; ?>
				<a href="https://github.com/mrdemonwolf/packrelay" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View on GitHub', 'packrelay' ); ?> &#8599;
				</a>
			</div>
		</div>
	</div>

	<form action="options.php" method="post">
		<?php
		settings_fields( PackRelay_Settings::PAGE_SLUG );
		do_settings_sections( PackRelay_Settings::PAGE_SLUG );
		submit_button();
		?>
	</form>
</div>
