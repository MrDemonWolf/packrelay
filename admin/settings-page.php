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
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<form action="options.php" method="post">
		<?php
		settings_fields( PackRelay_Settings::PAGE_SLUG );
		do_settings_sections( PackRelay_Settings::PAGE_SLUG );
		submit_button();
		?>
	</form>
</div>
