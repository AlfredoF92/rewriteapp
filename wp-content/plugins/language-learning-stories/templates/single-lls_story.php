<?php
/**
 * Template per la pagina singola Storia (esercizio utente).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$lls_known = get_post_meta( get_the_ID(), '_lls_known_lang', true );
if ( ! in_array( $lls_known, lls_known_lang_codes(), true ) ) {
	$lls_known = 'it';
}
$lls_merged = function_exists( 'lls_get_merged_ui_strings' ) ? lls_get_merged_ui_strings( $lls_known ) : [];
$lls_loading = isset( $lls_merged['loading'] ) ? $lls_merged['loading'] : 'Caricamento…';
?>

<main id="lls-story-page" class="lls-story-page">
	<div id="lls-story-root" class="lls-story-root">
		<div class="lls-story-loading"><?php echo esc_html( $lls_loading ); ?></div>
	</div>
</main>

<?php
get_footer();
