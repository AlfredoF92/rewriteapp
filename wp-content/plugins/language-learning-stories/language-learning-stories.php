<?php
/**
 * Plugin Name: Language Learning Stories
 * Description: Gestione storie con frasi, traduzioni e immagini per esercizi di traduzione.
 * Version:     0.1.0
 * Author:      ReadWrite
 * Text Domain: language-learning-stories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LLS_PLUGIN_VERSION', '0.1.0' );

class LLS_Plugin {

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ], 5 );
		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 20 );
		add_action( 'init', [ $this, 'maybe_migrate_remove_alt3' ], 25 );
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_story_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_init', [ $this, 'maybe_create_sample_story' ] );

		add_filter( 'template_include', [ $this, 'template_include_story' ], 99 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'wp_ajax_lls_save_progress', [ $this, 'ajax_save_progress' ] );
		add_action( 'wp_ajax_nopriv_lls_save_progress', [ $this, 'ajax_save_progress' ] );
	}

	public function register_post_type() {
		$labels = [
			'name'               => __( 'Storie', 'language-learning-stories' ),
			'singular_name'      => __( 'Storia', 'language-learning-stories' ),
			'menu_name'          => __( 'Storie', 'language-learning-stories' ),
			'name_admin_bar'     => __( 'Storia', 'language-learning-stories' ),
			'add_new'            => __( 'Aggiungi nuova', 'language-learning-stories' ),
			'add_new_item'       => __( 'Aggiungi nuova storia', 'language-learning-stories' ),
			'edit_item'          => __( 'Modifica storia', 'language-learning-stories' ),
			'new_item'           => __( 'Nuova storia', 'language-learning-stories' ),
			'all_items'          => __( 'Tutte le storie', 'language-learning-stories' ),
			'view_item'          => __( 'Vedi storia', 'language-learning-stories' ),
			'search_items'       => __( 'Cerca storie', 'language-learning-stories' ),
			'not_found'          => __( 'Nessuna storia trovata', 'language-learning-stories' ),
			'not_found_in_trash' => __( 'Nessuna storia nel cestino', 'language-learning-stories' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_admin_bar'  => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'supports'           => [ 'title', 'editor', 'thumbnail' ],
			'has_archive'        => false,
			'rewrite'            => [ 'slug' => 'storie' ],
			'capability_type'    => 'post',
			'menu_icon'          => 'dashicons-book-alt',
		];

		register_post_type( 'lls_story', $args );
	}

	/**
	 * Flush rewrite rules quando la versione del plugin cambia, così /storie/slug/ funziona.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_option( 'lls_plugin_version' ) === LLS_PLUGIN_VERSION ) {
			return;
		}
		flush_rewrite_rules();
		update_option( 'lls_plugin_version', LLS_PLUGIN_VERSION );
	}

	/**
	 * Rimuove la chiave alt3 da _lls_sentences in tutte le storie (una tantum).
	 */
	public function maybe_migrate_remove_alt3() {
		if ( get_option( 'lls_meta_removed_alt3_v1' ) ) {
			return;
		}
		$posts = get_posts(
			[
				'post_type'      => 'lls_story',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
		foreach ( $posts as $post_id ) {
			$sentences = get_post_meta( $post_id, '_lls_sentences', true );
			if ( ! is_array( $sentences ) ) {
				continue;
			}
			$changed = false;
			foreach ( $sentences as $i => $row ) {
				if ( ! is_array( $row ) || ! isset( $row['alt3'] ) ) {
					continue;
				}
				unset( $sentences[ $i ]['alt3'] );
				$changed = true;
			}
			if ( $changed ) {
				update_post_meta( $post_id, '_lls_sentences', array_values( $sentences ) );
			}
		}
		update_option( 'lls_meta_removed_alt3_v1', 1 );
	}

	public function register_meta_boxes() {
		add_meta_box(
			'lls_story_info',
			__( 'Informazioni Storia', 'language-learning-stories' ),
			[ $this, 'render_story_info_box' ],
			'lls_story',
			'normal',
			'high'
		);

		add_meta_box(
			'lls_story_sentences',
			__( 'Frasi della Storia', 'language-learning-stories' ),
			[ $this, 'render_sentences_box' ],
			'lls_story',
			'normal',
			'default'
		);
	}

	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'lls_story' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		$plugin_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style(
			'lls-admin-style',
			$plugin_url . 'assets/lls-admin.css',
			[],
			'0.1.25'
		);

		wp_enqueue_script(
			'jquery-ui-sortable'
		);

		wp_enqueue_script(
			'lls-admin-script',
			$plugin_url . 'assets/lls-admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			'0.1.25',
			true
		);

		$sentences = get_post_meta( get_the_ID(), '_lls_sentences', true );
		$images    = get_post_meta( get_the_ID(), '_lls_images', true );
		$sentences = is_array( $sentences ) ? $sentences : [];
		foreach ( $sentences as $sk => $srow ) {
			if ( is_array( $srow ) && isset( $srow['alt3'] ) ) {
				unset( $sentences[ $sk ]['alt3'] );
			}
		}
		$sentences = array_values( $sentences );

		wp_localize_script(
			'lls-admin-script',
			'llsAdmin',
			[
				'sentences'      => $sentences,
				'images'         => is_array( $images ) ? $images : [],
				'i18n'           => [
					'addSentence'         => __( 'Aggiungi Frase', 'language-learning-stories' ),
					'edit'                => __( 'Modifica', 'language-learning-stories' ),
					'delete'              => __( 'Elimina', 'language-learning-stories' ),
					'sentence'            => __( 'Frase', 'language-learning-stories' ),
					'imageBox'            => __( 'Box immagine', 'language-learning-stories' ),
					'insertImageHere'     => __( 'Inserisci un box immagine in questa posizione', 'language-learning-stories' ),
					'uploadImage'         => __( 'Carica Immagine', 'language-learning-stories' ),
					'removeImage'         => __( 'Rimuovi immagine', 'language-learning-stories' ),
					'confirmDelete'       => __( 'Sei sicuro di voler eliminare questa frase?', 'language-learning-stories' ),
					'confirmDeleteImage'  => __( 'Sei sicuro di voler eliminare questo box immagine?', 'language-learning-stories' ),
				],
				'nonce'          => wp_create_nonce( 'lls_admin_nonce' ),
			]
		);
	}

	public function render_story_info_box( $post ) {
		wp_nonce_field( 'lls_save_story', 'lls_story_nonce' );

		$opening_image_id = (int) get_post_meta( $post->ID, '_lls_opening_image_id', true );
		$opening_image    = $opening_image_id ? wp_get_attachment_image( $opening_image_id, 'medium' ) : '';
		?>
		<div class="lls-story-info">
			<p>
				<strong><?php esc_html_e( 'Immagine di apertura', 'language-learning-stories' ); ?></strong><br>
				<small><?php esc_html_e( 'Questa immagine apparirà prima della prima frase.', 'language-learning-stories' ); ?></small>
			</p>
			<div class="lls-opening-image-wrapper">
				<div class="lls-opening-image-preview">
					<?php
					if ( $opening_image ) {
						echo $opening_image;
					} else {
						echo '<span class="lls-opening-image-placeholder">' . esc_html__( 'Nessuna immagine selezionata', 'language-learning-stories' ) . '</span>';
					}
					?>
				</div>
				<input type="hidden" id="lls_opening_image_id" name="lls_opening_image_id" value="<?php echo esc_attr( $opening_image_id ); ?>">
				<p>
					<button type="button" class="button lls-opening-image-upload">
						<?php esc_html_e( 'Carica Immagine', 'language-learning-stories' ); ?>
					</button>
					<button type="button" class="button-link-delete lls-opening-image-remove"<?php echo $opening_image_id ? '' : ' style="display:none;"'; ?>>
						<?php esc_html_e( 'Rimuovi immagine', 'language-learning-stories' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	public function render_sentences_box( $post ) {
		$sentences = get_post_meta( $post->ID, '_lls_sentences', true );
		$images    = get_post_meta( $post->ID, '_lls_images', true );

		$sentences = is_array( $sentences ) ? $sentences : [];
		$images    = is_array( $images ) ? $images : [];
		?>
		<div class="lls-sentences-wrapper">
			<div class="lls-sentences-header">
				<div class="lls-sentences-header-buttons">
					<button type="button" class="button button-primary lls-add-sentence"><?php esc_html_e( '➕ Aggiungi Frase', 'language-learning-stories' ); ?></button>
					<button type="button" class="button lls-import-csv" title="<?php esc_attr_e( 'Solo file CSV con colonne separate da punto e virgola (;).', 'language-learning-stories' ); ?>"><?php esc_html_e( '📤 Importa CSV', 'language-learning-stories' ); ?></button>
					<button type="button" class="button lls-export-csv" title="<?php esc_attr_e( 'Scarica un CSV con colonne separate da punto e virgola (;).', 'language-learning-stories' ); ?>"><?php esc_html_e( '💾 Esporta CSV', 'language-learning-stories' ); ?></button>
				</div>
				<p class="description lls-csv-hint"><?php esc_html_e( 'CSV: usa solo il punto e virgola (;) tra una colonna e l’altra. Import ed esportazione usano lo stesso formato.', 'language-learning-stories' ); ?></p>
			</div>

			<div class="lls-sentences-list" id="lls-sentences-list">
				<?php
				$index = 1;
				foreach ( $sentences as $sentence ) {
					$this->render_sentence_card( $index, $sentence );
					$index++;
				}
				?>
			</div>

			<div class="lls-images-wrapper">
				<h4><?php esc_html_e( 'Box Immagini', 'language-learning-stories' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Ogni immagine sarà mostrata dopo la frase con il numero di posizione indicato.', 'language-learning-stories' ); ?></p>
				<div class="lls-images-list" id="lls-images-list">
					<?php
					foreach ( $images as $image ) {
						$this->render_image_card( $image );
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_sentence_card( $index, $sentence ) {
		$text_it        = isset( $sentence['text_it'] ) ? (string) $sentence['text_it'] : '';
		$main_trans     = isset( $sentence['main_translation'] ) ? (string) $sentence['main_translation'] : '';
		$alt1           = isset( $sentence['alt1'] ) ? (string) $sentence['alt1'] : '';
		$alt2           = isset( $sentence['alt2'] ) ? (string) $sentence['alt2'] : '';
		$grammar        = isset( $sentence['grammar'] ) ? (string) $sentence['grammar'] : '';
		?>
		<div class="lls-sentence-card">
			<div class="lls-sentence-header">
				<span class="lls-sentence-handle">↕</span>
				<span class="lls-sentence-title"><?php echo esc_html( sprintf( __( 'Frase #%d', 'language-learning-stories' ), $index ) ); ?></span>
				<span class="lls-sentence-preview"><?php echo esc_html( wp_html_excerpt( wp_strip_all_tags( $text_it ), 80, '…' ) ); ?></span>
				<button type="button" class="button-link lls-toggle-sentence"><?php esc_html_e( 'Modifica', 'language-learning-stories' ); ?></button>
				<button type="button" class="button-link-delete lls-delete-sentence"><?php esc_html_e( 'Elimina', 'language-learning-stories' ); ?></button>
			</div>
			<div class="lls-sentence-body">
				<p>
					<label>
						<strong><?php esc_html_e( 'Frase in italiano', 'language-learning-stories' ); ?></strong><br>
						<textarea name="lls_sentences[text_it][]" class="widefat" rows="2"><?php echo esc_textarea( $text_it ); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong><?php esc_html_e( 'Traduzione principale (appare nella storia)', 'language-learning-stories' ); ?></strong><br>
						<textarea name="lls_sentences[main_translation][]" class="widefat" rows="2"><?php echo esc_textarea( $main_trans ); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong><?php esc_html_e( 'Traduzione alternativa 1', 'language-learning-stories' ); ?></strong><br>
						<input type="text" name="lls_sentences[alt1][]" class="widefat" value="<?php echo esc_attr( $alt1 ); ?>">
					</label>
				</p>
				<p>
					<label>
						<strong><?php esc_html_e( 'Traduzione alternativa 2', 'language-learning-stories' ); ?></strong><br>
						<input type="text" name="lls_sentences[alt2][]" class="widefat" value="<?php echo esc_attr( $alt2 ); ?>">
					</label>
				</p>
				<p>
					<label>
						<strong><?php esc_html_e( 'Consigli grammaticali', 'language-learning-stories' ); ?></strong><br>
						<textarea name="lls_sentences[grammar][]" class="widefat lls-grammar-text" rows="8"><?php echo esc_textarea( $grammar ); ?></textarea>
					</label>
					<em class="description"><?php esc_html_e( 'Puoi usare tag HTML (es. &lt;strong&gt;, &lt;ul&gt;, &lt;li&gt;). Nel CSV la colonna va scritta in codice HTML.', 'language-learning-stories' ); ?></em>
					<span class="lls-grammar-counter"></span>
				</p>
				<p class="lls-insert-image-position">
					<button type="button" class="button button-secondary lls-insert-image-here">
						<?php esc_html_e( 'Inserisci un box immagine in questa posizione', 'language-learning-stories' ); ?>
					</button>
					<button type="button" class="button button-secondary lls-save-sentence">
						<?php esc_html_e( 'Salva', 'language-learning-stories' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_image_card( $image ) {
		$position = isset( $image['position'] ) ? (int) $image['position'] : 0;
		$id       = isset( $image['attachment_id'] ) ? (int) $image['attachment_id'] : 0;
		$thumb    = $id ? wp_get_attachment_image( $id, 'thumbnail' ) : '';
		?>
		<div class="lls-image-card">
			<div class="lls-image-inner">
				<div class="lls-image-position">
					<label>
						<?php esc_html_e( 'Posizione (dopo frase #)', 'language-learning-stories' ); ?><br>
						<input type="number" name="lls_images[position][]" value="<?php echo esc_attr( $position ); ?>" min="1" step="1" style="width:80px;">
					</label>
				</div>
				<div class="lls-image-thumb">
					<?php
					if ( $thumb ) {
						echo $thumb;
					} else {
						echo '<span class="lls-image-placeholder">' . esc_html__( 'Nessuna immagine', 'language-learning-stories' ) . '</span>';
					}
					?>
				</div>
				<div class="lls-image-actions">
					<input type="hidden" name="lls_images[attachment_id][]" value="<?php echo esc_attr( $id ); ?>" class="lls-image-id">
					<button type="button" class="button lls-image-upload"><?php esc_html_e( 'Carica Immagine', 'language-learning-stories' ); ?></button>
					<button type="button" class="button-link-delete lls-image-delete"><?php esc_html_e( 'Elimina', 'language-learning-stories' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	public function save_story_meta( $post_id, $post ) {
		if ( 'lls_story' !== $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['lls_story_nonce'] ) || ! wp_verify_nonce( $_POST['lls_story_nonce'], 'lls_save_story' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$opening_image_id = isset( $_POST['lls_opening_image_id'] ) ? (int) $_POST['lls_opening_image_id'] : 0;
		update_post_meta( $post_id, '_lls_opening_image_id', $opening_image_id );

		$sentences_data = isset( $_POST['lls_sentences'] ) && is_array( $_POST['lls_sentences'] ) ? $_POST['lls_sentences'] : [];
		$sentences      = [];

		if ( ! empty( $sentences_data ) ) {
			$count = isset( $sentences_data['text_it'] ) && is_array( $sentences_data['text_it'] ) ? count( $sentences_data['text_it'] ) : 0;
			for ( $i = 0; $i < $count; $i++ ) {
				$text_it    = isset( $sentences_data['text_it'][ $i ] ) ? wp_kses_post( $sentences_data['text_it'][ $i ] ) : '';
				$main_trans = isset( $sentences_data['main_translation'][ $i ] ) ? wp_kses_post( $sentences_data['main_translation'][ $i ] ) : '';
				$alt1       = isset( $sentences_data['alt1'][ $i ] ) ? sanitize_text_field( $sentences_data['alt1'][ $i ] ) : '';
				$alt2       = isset( $sentences_data['alt2'][ $i ] ) ? sanitize_text_field( $sentences_data['alt2'][ $i ] ) : '';
				$grammar    = isset( $sentences_data['grammar'][ $i ] ) ? wp_kses_post( $sentences_data['grammar'][ $i ] ) : '';

				if ( '' === trim( $text_it ) && '' === trim( $main_trans ) ) {
					continue;
				}

				$sentences[] = [
					'text_it'          => $text_it,
					'main_translation' => $main_trans,
					'alt1'             => $alt1,
					'alt2'             => $alt2,
					'grammar'          => $grammar,
				];
			}
		}

		if ( ! empty( $sentences ) ) {
			update_post_meta( $post_id, '_lls_sentences', $sentences );
		} else {
			delete_post_meta( $post_id, '_lls_sentences' );
		}

		$images_positions = isset( $_POST['lls_images']['position'] ) ? (array) $_POST['lls_images']['position'] : [];
		$images_ids       = isset( $_POST['lls_images']['attachment_id'] ) ? (array) $_POST['lls_images']['attachment_id'] : [];
		$images           = [];

		$total_images = max( count( $images_positions ), count( $images_ids ) );
		for ( $i = 0; $i < $total_images; $i++ ) {
			$pos = isset( $images_positions[ $i ] ) ? (int) $images_positions[ $i ] : 0;
			$id  = isset( $images_ids[ $i ] ) ? (int) $images_ids[ $i ] : 0;

			if ( $pos <= 0 || $id <= 0 ) {
				continue;
			}

			$images[] = [
				'position'      => $pos,
				'attachment_id' => $id,
			];
		}

		if ( ! empty( $images ) ) {
			update_post_meta( $post_id, '_lls_images', $images );
		} else {
			delete_post_meta( $post_id, '_lls_images' );
		}
	}

	public function template_include_story( $template ) {
		if ( is_singular( 'lls_story' ) ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-lls_story.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	public function enqueue_frontend_assets() {
		if ( ! is_singular( 'lls_story' ) ) {
			return;
		}

		$post_id = get_the_ID();
		$sentences = get_post_meta( $post_id, '_lls_sentences', true );
		$images    = get_post_meta( $post_id, '_lls_images', true );
		$opening_image_id = (int) get_post_meta( $post_id, '_lls_opening_image_id', true );

		$sentences = is_array( $sentences ) ? $sentences : [];
		$images    = is_array( $images ) ? $images : [];

		foreach ( $sentences as $k => $row ) {
			if ( is_array( $row ) && isset( $row['alt3'] ) ) {
				unset( $sentences[ $k ]['alt3'] );
			}
		}
		$sentences = array_values( $sentences );

		$progress = [ 'completed' => 0, 'story_text' => '' ];
		if ( is_user_logged_in() ) {
			$saved = get_user_meta( get_current_user_id(), '_lls_progress_' . $post_id, true );
			if ( is_array( $saved ) && isset( $saved['completed'] ) ) {
				$progress['completed'] = (int) $saved['completed'];
				$progress['story_text'] = isset( $saved['story_text'] ) ? (string) $saved['story_text'] : '';
			}
		}

		$opening_image_url = '';
		if ( $opening_image_id ) {
			$opening_image_url = wp_get_attachment_image_url( $opening_image_id, 'large' );
		}
		$images_with_urls = [];
		foreach ( $images as $img ) {
			$aid = isset( $img['attachment_id'] ) ? (int) $img['attachment_id'] : 0;
			$images_with_urls[] = [
				'position'      => isset( $img['position'] ) ? (int) $img['position'] : 0,
				'attachment_id'  => $aid,
				'url'            => $aid ? wp_get_attachment_image_url( $aid, 'large' ) : '',
			];
		}

		$plugin_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style(
			'lls-frontend-font',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@300;600&display=swap',
			[],
			null
		);

		wp_enqueue_style(
			'lls-frontend-style',
			$plugin_url . 'assets/lls-frontend.css',
			[ 'lls-frontend-font' ],
			'0.1.26'
		);

		wp_enqueue_script(
			'lls-frontend-script',
			$plugin_url . 'assets/lls-frontend.js',
			[ 'jquery' ],
			'0.1.26',
			true
		);

		wp_localize_script(
			'lls-frontend-script',
			'llsStory',
			[
				'storyId'         => $post_id,
				'title'           => get_the_title(),
				'sentences'       => $sentences,
				'images'          => $images_with_urls,
				'openingImageUrl' => $opening_image_url,
				'progress'        => $progress,
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'lls_save_progress' ),
			]
		);
	}

	public function ajax_save_progress() {
		check_ajax_referer( 'lls_save_progress', 'nonce' );

		$story_id = isset( $_POST['story_id'] ) ? (int) $_POST['story_id'] : 0;
		$completed = isset( $_POST['completed'] ) ? (int) $_POST['completed'] : 0;
		$story_text = isset( $_POST['story_text'] ) ? wp_kses_post( wp_unslash( $_POST['story_text'] ) ) : '';

		if ( ! $story_id || get_post_type( $story_id ) !== 'lls_story' ) {
			wp_send_json_error();
		}

		if ( is_user_logged_in() ) {
			update_user_meta(
				get_current_user_id(),
				'_lls_progress_' . $story_id,
				[ 'completed' => $completed, 'story_text' => $story_text ]
			);
		}

		wp_send_json_success();
	}

	public function maybe_create_sample_story() {
		if ( get_option( 'lls_sample_story_created' ) ) {
			return;
		}

		$existing = get_page_by_title( 'La Cicala e la Formica', OBJECT, 'lls_story' );
		if ( $existing ) {
			update_option( 'lls_sample_story_created', 1 );
			return;
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => 'La Cicala e la Formica',
				'post_type'    => 'lls_story',
				'post_status'  => 'publish',
				'post_content' => "Una breve storia educativa sulla cicala e la formica per esercizi di traduzione dall'italiano all'inglese.",
			]
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		$grammar1 = "Per questa frase ti consiglio di:\n\n• \"C'era una volta\" → usa \"Once upon a time\"\n  È la formula classica per iniziare le fiabe in inglese. Alternativa: \"There once was\"\n\n• \"una cicala\" → \"a cicada\"\n  Usa \"a\" perché \"cicada\" inizia con consonante (non vocale)\n\n• \"che viveva\" → \"that lived\" oppure \"who lived\"\n  Nelle fiabe puoi usare \"who\" anche per gli animali! Entrambi corretti.\n\n• \"in un grande prato verde\" → \"in a large green meadow\"\n  ORDINE IMPORTANTE: in inglese l'aggettivo va PRIMA del nome\n  Corretto: \"large green meadow\"\n  Sbagliato: \"meadow large green\"\n\n• \"grande\" → puoi dire \"large\", \"big\", o \"vast\"\n  Tutte e tre vanno bene, ma \"large\" è più formale\n\nRicorda: questa è una frase al passato, quindi usa \"was\" e \"lived\"!";

		$grammar2 = "Per questa frase ti consiglio di:\n\n• \"La cicala\" → \"The cicada\"\n  Usa \"The\" perché è LA cicala specifica della storia (non una qualsiasi)\n\n• \"amava\" → \"loved\"\n  Passato del verbo \"to love\". È un verbo regolare quindi aggiungi -ed\n\n• \"cantare\" → due opzioni corrette:\n  Opzione 1: \"to sing\" (infinito con \"to\")\n  Opzione 2: \"singing\" (forma -ing, gerundio)\n\n  Puoi dire: \"loved to sing\" oppure \"loved singing\"\n  Entrambi significano la stessa cosa!\n\nStruttura semplice: Soggetto + verbo passato + infinito/gerundio\n\nAltri verbi simili che funzionano così:\n\"like to do\" / \"like doing\"\n\"enjoy to do\" / \"enjoy doing\" (ma \"enjoy\" preferisce il gerundio!)";

		$grammar3 = "Per questa frase ti consiglio di:\n\n• \"Cantava\" → \"sang\" oppure \"would sing\"\n  \"Sang\" = passato semplice di \"to sing\" (verbo irregolare!)\n  \"Would sing\" = azione abituale nel passato (faceva sempre così)\n\n• \"al mattino\" → \"in the morning\"\n  Preposizione: IN per le parti del giorno (in the morning, in the evening)\n\n• \"a mezzogiorno\" → \"at noon\" oppure \"at midday\"\n  Preposizione: AT per momenti specifici del giorno\n\n• \"al tramonto\" → \"at sunset\"\n  Anche qui AT perché è un momento preciso\n\n• \"anche\" → \"also\" oppure \"even\"\n  \"Also\" va dopo il soggetto: \"she also sang\"\n  \"Even\" è più enfatico: \"she even sang\" (enfatizza che cantava PERSINO al tramonto)\n\n• Ripetizione stilistica:\n  Puoi ripetere \"she sang\" tre volte per dare ritmo\n  Oppure dire solo una volta e elencare: \"She sang in the morning, at noon, and at sunset\"\n\nNota la virgola prima di \"and\" nelle liste di 3+ elementi (stile americano)!";

		$grammar4 = "Per questa frase ti consiglio di:\n\n• \"Il suo\" → \"Her\"\n  Possessivo femminile perché la cicala è femmina nella storia\n  Attenzione: in inglese \"her\" non cambia se il nome è maschile o femminile\n\n• \"canto\" → puoi dire:\n  \"Song\" = il canto come risultato/prodotto\n  \"Singing\" = l'azione del cantare\n  Entrambi corretti, ma \"song\" è più poetico\n\n• \"era allegro\" → \"was cheerful\"\n  \"Was\" = passato di \"to be\"\n  \"Cheerful\" = allegro, gioioso\n  Alternative: \"joyful\", \"happy\", \"merry\"\n\n• \"riempiva l'aria di musica\" → \"filled the air with music\"\n  ATTENZIONE: usa \"WITH\" non \"OF\"!\n  Corretto: \"filled... WITH music\"\n  Sbagliato: \"filled... OF music\"\n\n  Struttura: \"to fill\" qualcosa \"WITH\" qualcos'altro\n\n• Due frasi coordinate con \"and\"\n  Stesso soggetto (il canto), due verbi: \"was\" e \"filled\"\n\nAlternativa più creativa: \"made the air musical\" (rese l'aria musicale)";

		$grammar5 = "Per questa frase ti consiglio di:\n\n• \"Ogni giorno\" → tre opzioni:\n  \"Every day\" = più comune, uso quotidiano\n  \"Each day\" = più formale, enfatizza il singolo giorno\n  \"Daily\" = avverbio, molto conciso\n  Tutti e tre corretti!\n\n• \"si svegliava\" → \"woke up\" PHRASAL VERB!\n  Questo è importante: \"wake up\" è un verbo separato in due parti\n  Non puoi dire solo \"wake\" in questo contesto\n  Il complemento va DOPO \"up\": \"woke up without hurry\"\n\n  Alternative:\n  \"Rose\" (letterario, poetico - si alzava)\n  \"Awakened\" (molto formale, quasi mai usato nel parlato)\n\n• \"senza fretta\" → puoi dire:\n  \"Without hurry\" (senza fretta)\n  \"Without rush\" (senza fretta/urgenza)\n  \"At her leisure\" (con calma, a suo piacimento)\n  \"Unhurriedly\" (avverbio - senza fretta)\n\n• \"without\" + nome (NO articolo)\n  Corretto: \"without hurry\"\n  Sbagliato: \"without a hurry\"\n\n• Virgola dopo espressione di tempo iniziale:\n  \"Every day,\" ← virgola obbligatoria!\n  Separa l'espressione temporale dal resto della frase\n\nPhrasal verbs comuni simili: wake up, get up, stand up, sit down, lie down";

		$sentences = [
			[
                'text_it'          => "C'era una volta una cicala che viveva in un grande prato verde.",
				'main_translation' => 'Once upon a time there was a cicada that lived in a large green meadow.',
				'alt1'             => 'Once upon a time there was a cicada that lived in a large green meadow.',
				'alt2'             => 'There once was a cicada living in a big green field.',
				'grammar'          => $grammar1,
			],
			[
				'text_it'          => 'La cicala amava cantare.',
				'main_translation' => 'The cicada loved to sing.',
				'alt1'             => 'The cicada loved to sing.',
				'alt2'             => 'The cicada loved singing.',
				'grammar'          => $grammar2,
			],
			[
				'text_it'          => 'Cantava al mattino, cantava a mezzogiorno e cantava anche al tramonto.',
				'main_translation' => 'She sang in the morning, she sang at noon and she also sang at sunset.',
				'alt1'             => 'She sang in the morning, she sang at noon and she also sang at sunset.',
				'alt2'             => 'She would sing in the morning, at midday, and even at sunset.',
				'grammar'          => $grammar3,
			],
			[
				'text_it'          => "Il suo canto era allegro e riempiva l'aria di musica.",
				'main_translation' => 'Her song was cheerful and filled the air with music.',
				'alt1'             => 'Her song was cheerful and filled the air with music.',
				'alt2'             => 'Her singing was joyful and it filled the air with music.',
				'grammar'          => $grammar4,
			],
			[
				'text_it'          => 'Ogni giorno, la cicala si svegliava senza fretta.',
				'main_translation' => 'Every day, the cicada woke up without hurry.',
				'alt1'             => 'Every day, the cicada woke up without hurry.',
				'alt2'             => 'Each morning, the cicada rose without any rush.',
				'grammar'          => $grammar5,
			],
		];

		update_post_meta( $post_id, '_lls_sentences', $sentences );

		update_option( 'lls_sample_story_created', 1 );
	}
}

new LLS_Plugin();

register_activation_hook( __FILE__, function () {
	$labels = [
		'name' => 'Storie',
		'singular_name' => 'Storia',
	];
	register_post_type( 'lls_story', [
		'labels'       => $labels,
		'public'       => true,
		'publicly_queryable' => true,
		'rewrite'      => [ 'slug' => 'storie' ],
		'has_archive'  => false,
	] );
	flush_rewrite_rules();
} );

