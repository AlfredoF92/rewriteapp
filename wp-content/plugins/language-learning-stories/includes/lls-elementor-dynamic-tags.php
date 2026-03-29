<?php
/**
 * Tag dinamici Elementor per il post type lls_story (Loop Grid, singole, ecc.).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ID post storia nel contesto corrente (Loop Elementor / singola storia).
 *
 * @return int
 */
function lls_elementor_story_loop_post_id() {
	$post_id = get_the_ID();
	if ( $post_id && get_post_type( $post_id ) === 'lls_story' ) {
		return (int) $post_id;
	}
	return 0;
}

/**
 * Gruppo pannello Elementor.
 */
function lls_elementor_dynamic_tags_group() {
	return 'lls-story';
}

/**
 * Base tag storia.
 */
abstract class LLS_Elementor_Story_Tag_Base extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * @return int ID storia o 0.
	 */
	protected function story_id() {
		return lls_elementor_story_loop_post_id();
	}

	public function get_group() {
		return lls_elementor_dynamic_tags_group();
	}
}

/**
 * Base per tag che restituiscono dati (es. immagine per widget Immagine).
 */
abstract class LLS_Elementor_Story_Data_Tag_Base extends \Elementor\Core\DynamicTags\Data_Tag {

	/**
	 * @return int ID storia o 0.
	 */
	protected function story_id() {
		return lls_elementor_story_loop_post_id();
	}

	public function get_group() {
		return lls_elementor_dynamic_tags_group();
	}
}

/**
 * Titolo del post storia (lingua interfaccia / titolo WordPress).
 */
class LLS_Elementor_Story_Tag_Title extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-title';
	}

	public function get_title() {
		return esc_html__( 'Story — title (post title)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid ) {
			return;
		}
		echo esc_html( get_the_title( $sid ) );
	}
}

/**
 * Costo in coin (numero / testo).
 */
class LLS_Elementor_Story_Tag_Coin_Cost extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-coin-cost';
	}

	public function get_title() {
		return esc_html__( 'Story — coin cost', 'language-learning-stories' );
	}

	public function get_categories() {
		return [
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
		];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! function_exists( 'lls_get_story_coin_cost' ) ) {
			return;
		}
		echo esc_html( (string) (int) lls_get_story_coin_cost( $sid ) );
	}
}

/**
 * Ricompensa in coin al completamento.
 */
class LLS_Elementor_Story_Tag_Coin_Reward extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-coin-reward';
	}

	public function get_title() {
		return esc_html__( 'Story — coin reward (on finish)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
		];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! function_exists( 'lls_get_story_coin_reward' ) ) {
			return;
		}
		echo esc_html( (string) (int) lls_get_story_coin_reward( $sid ) );
	}
}

/**
 * Titolo nella lingua da imparare (meta).
 */
class LLS_Elementor_Story_Tag_Title_Target extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-title-target-lang';
	}

	public function get_title() {
		return esc_html__( 'Story — translated title (target language)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! function_exists( 'lls_get_story_title_in_target_lang' ) ) {
			return;
		}
		echo esc_html( lls_get_story_title_in_target_lang( $sid ) );
	}
}

/**
 * Codice lingua interfaccia (it, pl, es).
 */
class LLS_Elementor_Story_Tag_Known_Lang_Code extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-known-lang-code';
	}

	public function get_title() {
		return esc_html__( 'Story — interface language code', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid ) {
			return;
		}
		$raw = get_post_meta( $sid, '_lls_known_lang', true );
		$code = is_string( $raw ) && $raw !== '' ? $raw : 'it';
		if ( function_exists( 'lls_known_lang_codes' ) && ! in_array( $code, lls_known_lang_codes(), true ) ) {
			$code = 'it';
		}
		echo esc_html( $code );
	}
}

/**
 * Nome leggibile lingua interfaccia.
 */
class LLS_Elementor_Story_Tag_Known_Lang_Label extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-known-lang-label';
	}

	public function get_title() {
		return esc_html__( 'Story — interface language name', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! function_exists( 'lls_get_language_display_name_for_admin' ) ) {
			return;
		}
		$raw = get_post_meta( $sid, '_lls_known_lang', true );
		$code = is_string( $raw ) && $raw !== '' ? $raw : 'it';
		if ( function_exists( 'lls_known_lang_codes' ) && ! in_array( $code, lls_known_lang_codes(), true ) ) {
			$code = 'it';
		}
		echo esc_html( lls_get_language_display_name_for_admin( $code ) );
	}
}

/**
 * Codice lingua da imparare (en, pl, it, es).
 */
class LLS_Elementor_Story_Tag_Target_Lang_Code extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-target-lang-code';
	}

	public function get_title() {
		return esc_html__( 'Story — language to learn (code)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! function_exists( 'lls_get_story_target_lang' ) ) {
			return;
		}
		echo esc_html( lls_get_story_target_lang( $sid ) );
	}
}

/**
 * Nome lingua da imparare.
 */
class LLS_Elementor_Story_Tag_Target_Lang_Label extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-target-lang-label';
	}

	public function get_title() {
		return esc_html__( 'Story — language to learn (name)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! function_exists( 'lls_get_story_target_lang' ) || ! function_exists( 'lls_get_language_display_name_for_admin' ) ) {
			return;
		}
		$code = lls_get_story_target_lang( $sid );
		echo esc_html( lls_get_language_display_name_for_admin( $code ) );
	}
}

/**
 * URL immagine di apertura.
 */
class LLS_Elementor_Story_Tag_Opening_Image_Url extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-opening-image-url';
	}

	public function get_title() {
		return esc_html__( 'Story — opening image URL', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid ) {
			return;
		}
		$aid = (int) get_post_meta( $sid, '_lls_opening_image_id', true );
		if ( $aid <= 0 ) {
			return;
		}
		$url = wp_get_attachment_image_url( $aid, 'full' );
		if ( ! $url ) {
			return;
		}
		echo esc_url( $url );
	}
}

/**
 * ID attachment immagine di apertura.
 */
class LLS_Elementor_Story_Tag_Opening_Image_Id extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-opening-image-id';
	}

	public function get_title() {
		return esc_html__( 'Story — opening image attachment ID', 'language-learning-stories' );
	}

	public function get_categories() {
		return [
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
		];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid ) {
			return;
		}
		$aid = (int) get_post_meta( $sid, '_lls_opening_image_id', true );
		if ( $aid <= 0 ) {
			return;
		}
		echo esc_html( (string) $aid );
	}
}

/**
 * Immagine per widget Immagine: prima immagine di apertura LLS, altrimenti immagine in evidenza del post.
 */
class LLS_Elementor_Story_Tag_Feature_Image extends LLS_Elementor_Story_Data_Tag_Base {

	public function get_name() {
		return 'lls-story-feature-image';
	}

	public function get_title() {
		return esc_html__( 'Story — preview image (opening or featured)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [
			\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::MEDIA_CATEGORY,
		];
	}

	protected function register_controls() {
		$this->add_control(
			'fallback',
			[
				'label' => esc_html__( 'Fallback', 'language-learning-stories' ),
				'type'  => \Elementor\Controls_Manager::MEDIA,
			]
		);
	}

	/**
	 * @param array<string, mixed> $options Opzioni Elementor.
	 * @return array<string, mixed>|array{} Valore per widget immagine.
	 */
	protected function get_value( array $options = [] ) {
		$sid = $this->story_id();
		if ( ! $sid ) {
			$fb = $this->get_settings( 'fallback' );
			return is_array( $fb ) ? $fb : [];
		}

		$aid = (int) get_post_meta( $sid, '_lls_opening_image_id', true );
		if ( $aid <= 0 ) {
			$aid = (int) get_post_thumbnail_id( $sid );
		}
		if ( $aid <= 0 ) {
			$fb = $this->get_settings( 'fallback' );
			return is_array( $fb ) ? $fb : [];
		}

		$src = wp_get_attachment_image_src( $aid, 'full' );
		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			$fb = $this->get_settings( 'fallback' );
			return is_array( $fb ) ? $fb : [];
		}

		return [
			'id'  => $aid,
			'url' => $src[0],
		];
	}
}

/**
 * Trama breve (estratto o contenuto).
 */
class LLS_Elementor_Story_Tag_Summary extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-summary';
	}

	public function get_title() {
		return esc_html__( 'Story — summary (plot)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function register_controls() {
		$this->add_control(
			'words',
			[
				'label'   => esc_html__( 'Max words', 'language-learning-stories' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 35,
				'min'     => 5,
				'max'     => 120,
				'step'    => 1,
			]
		);
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! function_exists( 'lls_get_story_summary_text' ) ) {
			return;
		}
		$post = get_post( $sid );
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$settings = $this->get_settings();
		$words    = isset( $settings['words'] ) ? (int) $settings['words'] : 35;
		$words    = max( 5, min( 120, $words ) );
		echo esc_html( lls_get_story_summary_text( $post, $words ) );
	}
}

/**
 * Elenco categorie (solo nomi, separati da virgola).
 */
class LLS_Elementor_Story_Tag_Categories extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-categories';
	}

	public function get_title() {
		return esc_html__( 'Story — categories (names)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! taxonomy_exists( 'lls_story_category' ) ) {
			return;
		}
		$terms = get_the_terms( $sid, 'lls_story_category' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}
		$names = [];
		foreach ( $terms as $t ) {
			if ( $t instanceof WP_Term ) {
				$names[] = $t->name;
			}
		}
		echo esc_html( implode( ', ', $names ) );
	}
}

/**
 * Elenco tag (solo nomi).
 */
class LLS_Elementor_Story_Tag_Tags extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-tags';
	}

	public function get_title() {
		return esc_html__( 'Story — tags (names)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid || ! taxonomy_exists( 'lls_story_tag' ) ) {
			return;
		}
		$terms = get_the_terms( $sid, 'lls_story_tag' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}
		$names = [];
		foreach ( $terms as $t ) {
			if ( $t instanceof WP_Term ) {
				$names[] = $t->name;
			}
		}
		echo esc_html( implode( ', ', $names ) );
	}
}

/**
 * Numero frasi (sentences) della storia.
 */
class LLS_Elementor_Story_Tag_Sentence_Count extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-sentence-count';
	}

	public function get_title() {
		return esc_html__( 'Story — phrase count (number of sentences)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
		];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid ) {
			return;
		}
		$sentences = get_post_meta( $sid, '_lls_sentences', true );
		$n         = is_array( $sentences ) ? count( $sentences ) : 0;
		echo esc_html( (string) (int) $n );
	}
}

/**
 * L'utente corrente può accedere alla storia (sbloccata / gratuita): 1 o 0.
 */
class LLS_Elementor_Story_Tag_User_Can_Access extends LLS_Elementor_Story_Tag_Base {

	public function get_name() {
		return 'lls-story-user-can-access';
	}

	public function get_title() {
		return esc_html__( 'Story — current user can access (1/0)', 'language-learning-stories' );
	}

	public function get_categories() {
		return [
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
		];
	}

	public function render() {
		$sid = $this->story_id();
		if ( ! $sid ) {
			echo '0';
			return;
		}
		if ( ! function_exists( 'lls_user_can_access_story' ) ) {
			echo '1';
			return;
		}
		$uid = is_user_logged_in() ? get_current_user_id() : 0;
		echo lls_user_can_access_story( $uid, $sid ) ? '1' : '0';
	}
}

/**
 * Registra gruppo e tag con Elementor.
 *
 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Manager.
 */
function lls_register_elementor_story_dynamic_tags( $dynamic_tags_manager ) {
	if ( ! $dynamic_tags_manager instanceof \Elementor\Core\DynamicTags\Manager ) {
		return;
	}

	$dynamic_tags_manager->register_group(
		lls_elementor_dynamic_tags_group(),
		[
			'title' => esc_html__( 'Language Learning Stories', 'language-learning-stories' ),
		]
	);

	$tags = [
		new LLS_Elementor_Story_Tag_Title(),
		new LLS_Elementor_Story_Tag_Coin_Cost(),
		new LLS_Elementor_Story_Tag_Coin_Reward(),
		new LLS_Elementor_Story_Tag_Title_Target(),
		new LLS_Elementor_Story_Tag_Known_Lang_Code(),
		new LLS_Elementor_Story_Tag_Known_Lang_Label(),
		new LLS_Elementor_Story_Tag_Target_Lang_Code(),
		new LLS_Elementor_Story_Tag_Target_Lang_Label(),
		new LLS_Elementor_Story_Tag_Feature_Image(),
		new LLS_Elementor_Story_Tag_Opening_Image_Url(),
		new LLS_Elementor_Story_Tag_Opening_Image_Id(),
		new LLS_Elementor_Story_Tag_Summary(),
		new LLS_Elementor_Story_Tag_Categories(),
		new LLS_Elementor_Story_Tag_Tags(),
		new LLS_Elementor_Story_Tag_Sentence_Count(),
		new LLS_Elementor_Story_Tag_User_Can_Access(),
	];

	foreach ( $tags as $tag ) {
		$dynamic_tags_manager->register( $tag );
	}
}
