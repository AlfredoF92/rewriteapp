<?php
/**
 * Widget Elementor: pulsante sblocco storia (coin).
 *
 * @package Language_Learning_Stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) || class_exists( 'LLS_Elementor_Widget_Story_Unlock' ) ) {
	return;
}

/**
 * Widget Elementor: pulsante sblocco storia (Loop / singola storia).
 */
class LLS_Elementor_Widget_Story_Unlock extends \Elementor\Widget_Base {

	public function get_name() {
		return 'lls_story_unlock_button';
	}

	public function get_title() {
		return esc_html__( 'Story unlock (coins)', 'language-learning-stories' );
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'lls', 'story', 'unlock', 'coin', 'storia' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_note',
			[
				'label' => esc_html__( 'Info', 'language-learning-stories' ),
			]
		);
		$this->add_control(
			'help_note',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<p>' . esc_html__( 'Shows Enter / Log in / Unlock with coins for the current story. Use inside a Loop for lls_story or on the single story template.', 'language-learning-stories' ) . '</p>',
			]
		);
		$this->end_controls_section();
	}

	protected function render() {
		$post_id = function_exists( 'lls_story_unlock_resolve_post_id' ) ? lls_story_unlock_resolve_post_id( 0 ) : (int) get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'lls_story' ) {
			if ( class_exists( '\Elementor\Plugin' ) ) {
				$ed = \Elementor\Plugin::$instance->editor;
				if ( $ed && $ed->is_edit_mode() ) {
					echo '<p class="lls-elementor-placeholder">' . esc_html__( 'Preview: place this widget in a story loop or on a single story page.', 'language-learning-stories' ) . '</p>';
				}
			}
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML generato da funzione con escape interno.
		echo lls_get_story_unlock_button_html( $post_id );
	}
}
