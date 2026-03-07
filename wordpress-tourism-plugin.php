<?php
/**
 * Plugin Name: WordPress Tourism Plugin
 * Description: Tourism package management with manual entry and CSV import/export.
 * Version: 1.0.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTP_Tourism_Plugin {
	const POST_TYPE = 'wtp_package';
	const OPTION_WHATSAPP_NUMBER = 'wtp_whatsapp_number';

	/**
	 * @var string[]
	 */
	private $fields = array(
		'destination',
		'transport_type',
		'departure_date',
		'number_of_days',
		'accommodation',
		'transfer',
		'baggage',
		'excursions',
		'observation',
	);

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_package_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_missing_whatsapp_notice' ) );
		add_action( 'admin_post_wtp_import_packages', array( $this, 'handle_import' ) );
		add_action( 'admin_post_wtp_export_packages', array( $this, 'handle_export' ) );
		add_filter( 'the_content', array( $this, 'render_single_package_content' ) );
	}

	/**
	 * Register package post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Tourism Packages', 'wordpress-tourism-plugin' ),
			'singular_name'      => __( 'Tourism Package', 'wordpress-tourism-plugin' ),
			'add_new'            => __( 'Add New Package', 'wordpress-tourism-plugin' ),
			'add_new_item'       => __( 'Add New Tourism Package', 'wordpress-tourism-plugin' ),
			'edit_item'          => __( 'Edit Tourism Package', 'wordpress-tourism-plugin' ),
			'new_item'           => __( 'New Tourism Package', 'wordpress-tourism-plugin' ),
			'view_item'          => __( 'View Tourism Package', 'wordpress-tourism-plugin' ),
			'search_items'       => __( 'Search Tourism Packages', 'wordpress-tourism-plugin' ),
			'not_found'          => __( 'No tourism packages found.', 'wordpress-tourism-plugin' ),
			'not_found_in_trash' => __( 'No tourism packages found in trash.', 'wordpress-tourism-plugin' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'            => $labels,
				'public'            => true,
				'publicly_queryable'=> true,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'menu_position'     => 25,
				'menu_icon'         => 'dashicons-palmtree',
				'supports'          => array( 'title' ),
				'capability_type'   => 'post',
				'has_archive'       => false,
				'rewrite'           => array( 'slug' => 'tour-package' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Register plugin shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'tour_packages', array( $this, 'render_tour_packages_shortcode' ) );
	}

	/**
	 * Register package details meta box.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'wtp_package_details',
			__( 'Package Details', 'wordpress-tourism-plugin' ),
			array( $this, 'render_package_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Enqueue media scripts in package admin screen.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Enqueue frontend stylesheet.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$handle = 'wtp-frontend';
		wp_register_style( $handle, false, array(), '1.1.0' );
		wp_enqueue_style( $handle );

		$css = '
		.wtp-package-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem;margin:1.5rem 0;}
		.wtp-package-card{background:#fff;border:1px solid #e8ecf1;border-radius:14px;overflow:hidden;box-shadow:0 10px 25px rgba(15,23,42,.06);display:flex;flex-direction:column;height:100%;}
		.wtp-package-card__media{aspect-ratio:16/10;background:#f4f7fb;overflow:hidden;}
		.wtp-package-card__media img{width:100%;height:100%;object-fit:cover;display:block;}
		.wtp-package-card__content{padding:1rem;display:flex;flex-direction:column;gap:.55rem;flex:1;}
		.wtp-package-card__title{font-size:1.2rem;font-weight:700;line-height:1.3;margin:0;}
		.wtp-meta{margin:0;padding:0;list-style:none;display:grid;gap:.35rem;color:#334155;}
		.wtp-meta strong{color:#0f172a;}
		.wtp-observation{margin:0;color:#475569;}
		.wtp-card-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:auto;padding-top:.4rem;}
		.wtp-button{display:inline-flex;align-items:center;justify-content:center;padding:.65rem .95rem;border-radius:8px;text-decoration:none;font-weight:600;font-size:.95rem;transition:.2s ease;}
		.wtp-button--primary{background:#0f172a;color:#fff;}
		.wtp-button--primary:hover{background:#1e293b;color:#fff;}
		.wtp-button--whatsapp{background:#25d366;color:#fff;}
		.wtp-button--whatsapp:hover{background:#1fa855;color:#fff;}
		.wtp-single{max-width:980px;margin:2rem auto;display:grid;gap:1.4rem;}
		.wtp-gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;}
		.wtp-gallery img{width:100%;height:220px;object-fit:cover;border-radius:12px;box-shadow:0 10px 20px rgba(15,23,42,.08);}
		.wtp-single__header h1{margin:.2rem 0;font-size:2rem;line-height:1.25;}
		.wtp-detail-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.7rem 1rem;padding:0;margin:0;list-style:none;}
		.wtp-detail-list li{padding:.7rem .85rem;background:#f8fafc;border-radius:10px;color:#334155;}
		.wtp-detail-list strong{display:block;color:#0f172a;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem;}
		.wtp-panel{background:#fff;border:1px solid #e8ecf1;border-radius:12px;padding:1rem;}
		.wtp-panel h3{margin-top:0;font-size:1rem;}
		@media (max-width:640px){.wtp-button{width:100%;}.wtp-single__header h1{font-size:1.6rem;}}
		';

		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Render package list shortcode.
	 *
	 * @return string
	 */
	public function render_tour_packages_shortcode() {
		$packages = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $packages ) ) {
			return '<p>' . esc_html__( 'No tourism packages available at the moment.', 'wordpress-tourism-plugin' ) . '</p>';
		}

		ob_start();
		echo '<div class="wtp-package-grid">';
		$whatsapp_number = $this->get_whatsapp_number();

		foreach ( $packages as $package ) {
			$images          = get_post_meta( $package->ID, 'package_images', true );
			$images          = is_array( $images ) ? $images : array();
			$main_image      = ! empty( $images[0] ) ? $images[0] : '';
			$destination     = $this->get_package_value( $package->ID, 'destination' );
			$departure_date  = $this->format_departure_date( $this->get_package_value( $package->ID, 'departure_date' ) );
			$number_of_days  = $this->get_package_value( $package->ID, 'number_of_days' );
			$transport_type  = $this->get_package_value( $package->ID, 'transport_type' );
			$observation     = $this->get_package_value( $package->ID, 'observation' );
			$permalink       = get_permalink( $package->ID );
			$whatsapp_url    = $this->build_whatsapp_url( $whatsapp_number, $destination, $this->get_package_value( $package->ID, 'departure_date' ) );

			echo '<article class="wtp-package-card">';
			echo '<a class="wtp-package-card__media" href="' . esc_url( $permalink ) . '">';
			if ( ! empty( $main_image ) ) {
				echo '<img src="' . esc_url( $main_image ) . '" alt="' . esc_attr( $destination ) . '" />';
			} else {
				echo '<img src="https://via.placeholder.com/800x500?text=' . rawurlencode( $destination ) . '" alt="' . esc_attr( $destination ) . '" />';
			}
			echo '</a>';
			echo '<div class="wtp-package-card__content">';
			echo '<h3 class="wtp-package-card__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $destination ) . '</a></h3>';
			echo '<ul class="wtp-meta">';
			echo '<li><strong>' . esc_html__( 'Departure:', 'wordpress-tourism-plugin' ) . '</strong> ' . esc_html( $departure_date ) . '</li>';
			echo '<li><strong>' . esc_html__( 'Days:', 'wordpress-tourism-plugin' ) . '</strong> ' . esc_html( $number_of_days ) . '</li>';
			echo '<li><strong>' . esc_html__( 'Transport:', 'wordpress-tourism-plugin' ) . '</strong> ' . esc_html( $transport_type ) . '</li>';
			echo '</ul>';
			echo '<p class="wtp-observation">' . esc_html( wp_trim_words( $observation, 20, '...' ) ) . '</p>';
			echo '<div class="wtp-card-actions">';
			echo '<a class="wtp-button wtp-button--primary" href="' . esc_url( $permalink ) . '">' . esc_html__( 'Ver detalle', 'wordpress-tourism-plugin' ) . '</a>';
			if ( ! empty( $whatsapp_url ) ) {
				echo '<a class="wtp-button wtp-button--whatsapp" href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Consultar por WhatsApp', 'wordpress-tourism-plugin' ) . '</a>';
			}
			echo '</div>';
			echo '</div>';
			echo '</article>';
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Render single package front-end content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function render_single_package_content( $content ) {
		if ( ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id        = get_the_ID();
		$destination    = $this->get_package_value( $post_id, 'destination' );
		$transport_type = $this->get_package_value( $post_id, 'transport_type' );
		$departure_raw  = $this->get_package_value( $post_id, 'departure_date' );
		$departure_date = $this->format_departure_date( $departure_raw );
		$days           = $this->get_package_value( $post_id, 'number_of_days' );
		$accommodation  = $this->get_package_value( $post_id, 'accommodation' );
		$transfer       = $this->get_package_value( $post_id, 'transfer' );
		$baggage        = $this->get_package_value( $post_id, 'baggage' );
		$excursions     = $this->get_package_value( $post_id, 'excursions' );
		$observation    = $this->get_package_value( $post_id, 'observation' );
		$images         = get_post_meta( $post_id, 'package_images', true );
		$images         = is_array( $images ) ? array_filter( array_slice( $images, 0, 5 ) ) : array();
		$whatsapp_url   = $this->build_whatsapp_url( $this->get_whatsapp_number(), $destination, $departure_raw );

		ob_start();
		echo '<section class="wtp-single">';
		echo '<header class="wtp-single__header">';
		echo '<p>' . esc_html__( 'Tourism Package', 'wordpress-tourism-plugin' ) . '</p>';
		echo '<h1>' . esc_html( $destination ) . '</h1>';
		echo '</header>';

		if ( ! empty( $images ) ) {
			echo '<div class="wtp-gallery">';
			foreach ( $images as $image_url ) {
				echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $destination ) . '" />';
			}
			echo '</div>';
		}

		echo '<ul class="wtp-detail-list">';
		echo '<li><strong>' . esc_html__( 'Destination', 'wordpress-tourism-plugin' ) . '</strong>' . esc_html( $destination ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Transport', 'wordpress-tourism-plugin' ) . '</strong>' . esc_html( $transport_type ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Departure', 'wordpress-tourism-plugin' ) . '</strong>' . esc_html( $departure_date ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Days', 'wordpress-tourism-plugin' ) . '</strong>' . esc_html( $days ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Accommodation', 'wordpress-tourism-plugin' ) . '</strong>' . esc_html( $accommodation ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Transfer', 'wordpress-tourism-plugin' ) . '</strong>' . esc_html( $transfer ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Baggage', 'wordpress-tourism-plugin' ) . '</strong>' . esc_html( $baggage ) . '</li>';
		echo '</ul>';

		echo '<div class="wtp-panel"><h3>' . esc_html__( 'Excursions', 'wordpress-tourism-plugin' ) . '</h3><p>' . nl2br( esc_html( $excursions ) ) . '</p></div>';
		echo '<div class="wtp-panel"><h3>' . esc_html__( 'Observation', 'wordpress-tourism-plugin' ) . '</h3><p>' . nl2br( esc_html( $observation ) ) . '</p></div>';
		if ( ! empty( $whatsapp_url ) ) {
			echo '<a class="wtp-button wtp-button--whatsapp" href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Consultar por WhatsApp', 'wordpress-tourism-plugin' ) . '</a>';
		}
		echo '</section>';

		return (string) ob_get_clean();
	}

	/**
	 * Get sanitized package meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key Meta key.
	 * @return string
	 */
	private function get_package_value( $post_id, $key ) {
		return (string) get_post_meta( $post_id, $key, true );
	}

	/**
	 * Format departure date.
	 *
	 * @param string $date_raw Date in Y-m-d format.
	 * @return string
	 */
	private function format_departure_date( $date_raw ) {
		$timestamp = strtotime( $date_raw );
		if ( ! $timestamp ) {
			return $date_raw;
		}

		return gmdate( 'd/m/Y', $timestamp );
	}

	/**
	 * Build WhatsApp URL with package context.
	 *
	 * @param string $number WhatsApp number in international format.
	 * @param string $destination Destination.
	 * @param string $departure_raw Raw departure date.
	 * @return string
	 */
	private function build_whatsapp_url( $number, $destination, $departure_raw ) {
		if ( empty( $number ) ) {
			return '';
		}

		$message = sprintf(
			/* translators: 1: destination name, 2: departure date. */
			__( 'Hola! Quiero consultar por el paquete a %1$s con salida el %2$s.', 'wordpress-tourism-plugin' ),
			$destination,
			$this->format_departure_date( $departure_raw )
		);

		return 'https://wa.me/' . rawurlencode( $number ) . '?text=' . rawurlencode( $message );
	}

	/**
	 * Get configured WhatsApp number.
	 *
	 * @return string
	 */
	private function get_whatsapp_number() {
		$number = get_option( self::OPTION_WHATSAPP_NUMBER, '' );

		return $this->sanitize_whatsapp_number( $number );
	}

	/**
	 * Render package details form.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_package_meta_box( $post ) {
		wp_nonce_field( 'wtp_save_package_meta', 'wtp_package_nonce' );

		echo '<table class="form-table" role="presentation">';
		$this->render_text_row( $post->ID, 'destination', __( 'Destination', 'wordpress-tourism-plugin' ) );
		$this->render_text_row( $post->ID, 'transport_type', __( 'Transport Type', 'wordpress-tourism-plugin' ) );
		$this->render_date_row( $post->ID, 'departure_date', __( 'Departure Date', 'wordpress-tourism-plugin' ) );
		$this->render_number_row( $post->ID, 'number_of_days', __( 'Number of Days', 'wordpress-tourism-plugin' ) );
		$this->render_text_row( $post->ID, 'accommodation', __( 'Accommodation', 'wordpress-tourism-plugin' ) );
		$this->render_text_row( $post->ID, 'transfer', __( 'Transfer', 'wordpress-tourism-plugin' ) );
		$this->render_text_row( $post->ID, 'baggage', __( 'Baggage', 'wordpress-tourism-plugin' ) );
		$this->render_textarea_row( $post->ID, 'excursions', __( 'Excursions', 'wordpress-tourism-plugin' ) );
		$this->render_textarea_row( $post->ID, 'observation', __( 'Observation', 'wordpress-tourism-plugin' ) );
		echo '</table>';

		$this->render_images_section( $post->ID );
	}

	private function render_text_row( $post_id, $field, $label ) {
		$value = get_post_meta( $post_id, $field, true );
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input class="regular-text" type="text" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" /></td>';
		echo '</tr>';
	}

	private function render_date_row( $post_id, $field, $label ) {
		$value = get_post_meta( $post_id, $field, true );
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="date" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" /></td>';
		echo '</tr>';
	}

	private function render_number_row( $post_id, $field, $label ) {
		$value = get_post_meta( $post_id, $field, true );
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="number" min="1" step="1" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" /></td>';
		echo '</tr>';
	}

	private function render_textarea_row( $post_id, $field, $label ) {
		$value = get_post_meta( $post_id, $field, true );
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><textarea class="large-text" rows="4" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">' . esc_textarea( $value ) . '</textarea></td>';
		echo '</tr>';
	}

	private function render_images_section( $post_id ) {
		$images = get_post_meta( $post_id, 'package_images', true );
		$images = is_array( $images ) ? $images : array();

		echo '<h4>' . esc_html__( 'Package Images (up to 5)', 'wordpress-tourism-plugin' ) . '</h4>';
		echo '<p>' . esc_html__( 'Choose images from the WordPress media library.', 'wordpress-tourism-plugin' ) . '</p>';
		echo '<div id="wtp-images-wrapper">';

		for ( $index = 0; $index < 5; $index++ ) {
			$url = isset( $images[ $index ] ) ? $images[ $index ] : '';
			echo '<div class="wtp-image-row" style="margin-bottom:12px;">';
			echo '<label><strong>' . sprintf( esc_html__( 'Image %d', 'wordpress-tourism-plugin' ), $index + 1 ) . '</strong></label><br />';
			echo '<input type="text" class="regular-text wtp-image-url" name="package_images[]" value="' . esc_attr( $url ) . '" placeholder="https://example.com/image.jpg" /> ';
			echo '<button type="button" class="button wtp-select-image">' . esc_html__( 'Select from Media Library', 'wordpress-tourism-plugin' ) . '</button>';
			echo '</div>';
		}

		echo '</div>';
		?>
		<script>
		jQuery(function($){
			$(document).on('click', '.wtp-select-image', function(e){
				e.preventDefault();
				const $row = $(this).closest('.wtp-image-row');
				const $input = $row.find('.wtp-image-url');
				const frame = wp.media({
					title: '<?php echo esc_js( __( 'Select Package Image', 'wordpress-tourism-plugin' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Use this image', 'wordpress-tourism-plugin' ) ); ?>' },
					multiple: false
				});
				frame.on('select', function(){
					const attachment = frame.state().get('selection').first().toJSON();
					if (attachment && attachment.url) {
						$input.val(attachment.url);
					}
				});
				frame.open();
			});
		});
		</script>
		<?php
	}

	/**
	 * Save package details.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function save_package_meta( $post_id, $post ) {
		if ( ! isset( $_POST['wtp_package_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wtp_package_nonce'] ) ), 'wtp_save_package_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( self::POST_TYPE !== $post->post_type ) {
			return;
		}

		foreach ( $this->fields as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			$value = wp_unslash( $_POST[ $field ] );
			if ( 'number_of_days' === $field ) {
				$value = absint( $value );
			} elseif ( in_array( $field, array( 'excursions', 'observation' ), true ) ) {
				$value = sanitize_textarea_field( $value );
			} else {
				$value = sanitize_text_field( $value );
			}

			update_post_meta( $post_id, $field, $value );
		}

		$images = array();
		if ( isset( $_POST['package_images'] ) && is_array( $_POST['package_images'] ) ) {
			$raw_images = array_slice( wp_unslash( $_POST['package_images'] ), 0, 5 );
			foreach ( $raw_images as $url ) {
				$cleaned = esc_url_raw( trim( $url ) );
				$images[] = $cleaned;
			}
		}

		update_post_meta( $post_id, 'package_images', $images );
	}

	/**
	 * Add CSV import/export admin page.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			__( 'WhatsApp Settings', 'wordpress-tourism-plugin' ),
			__( 'WhatsApp Settings', 'wordpress-tourism-plugin' ),
			'manage_options',
			'wtp-whatsapp-settings',
			array( $this, 'render_whatsapp_settings_page' )
		);

		add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			__( 'Import / Export Packages', 'wordpress-tourism-plugin' ),
			__( 'Import / Export', 'wordpress-tourism-plugin' ),
			'manage_options',
			'wtp-import-export',
			array( $this, 'render_import_export_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wtp_settings',
			self::OPTION_WHATSAPP_NUMBER,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_whatsapp_number' ),
				'default'           => '',
			)
		);

		add_settings_section(
			'wtp_whatsapp_section',
			__( 'WhatsApp Configuration', 'wordpress-tourism-plugin' ),
			'__return_empty_string',
			'wtp-whatsapp-settings'
		);

		add_settings_field(
			'wtp_whatsapp_number',
			__( 'Default WhatsApp Number', 'wordpress-tourism-plugin' ),
			array( $this, 'render_whatsapp_number_field' ),
			'wtp-whatsapp-settings',
			'wtp_whatsapp_section'
		);
	}

	/**
	 * Render settings field for WhatsApp number.
	 *
	 * @return void
	 */
	public function render_whatsapp_number_field() {
		$value = get_option( self::OPTION_WHATSAPP_NUMBER, '' );
		echo '<input type="text" id="wtp_whatsapp_number" name="' . esc_attr( self::OPTION_WHATSAPP_NUMBER ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="5491112345678" />';
		echo '<p class="description">' . esc_html__( 'Enter the number in international format (country code + number, no spaces). Example: 5491112345678.', 'wordpress-tourism-plugin' ) . '</p>';
	}

	/**
	 * Render WhatsApp settings page.
	 *
	 * @return void
	 */
	public function render_whatsapp_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tourism Packages: WhatsApp Settings', 'wordpress-tourism-plugin' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wtp_settings' );
				do_settings_sections( 'wtp-whatsapp-settings' );
				submit_button( __( 'Save Settings', 'wordpress-tourism-plugin' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Show admin notice when WhatsApp number has not been configured.
	 *
	 * @return void
	 */
	public function maybe_render_missing_whatsapp_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! empty( $this->get_whatsapp_number() ) ) {
			return;
		}

		$settings_url = admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=wtp-whatsapp-settings' );
		echo '<div class="notice notice-warning"><p>';
		echo wp_kses_post(
			sprintf(
				/* translators: %s: settings url. */
				__( 'The default WhatsApp number is not configured yet. Please set it in <a href="%s">WhatsApp Settings</a> so inquiry buttons work correctly.', 'wordpress-tourism-plugin' ),
				esc_url( $settings_url )
			)
		);
		echo '</p></div>';
	}

	/**
	 * Sanitize WhatsApp number value.
	 *
	 * @param string $number Raw number.
	 * @return string
	 */
	public function sanitize_whatsapp_number( $number ) {
		$number = sanitize_text_field( (string) $number );

		return preg_replace( '/\D+/', '', $number );
	}

	/**
	 * Render import/export page.
	 *
	 * @return void
	 */
	public function render_import_export_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$imported = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
		$error    = isset( $_GET['wtp_error'] ) ? sanitize_text_field( wp_unslash( $_GET['wtp_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tourism Packages: CSV Import / Export', 'wordpress-tourism-plugin' ); ?></h1>

			<?php if ( $imported > 0 ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Imported %d package(s).', 'wordpress-tourism-plugin' ), $imported ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $error ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Export Packages', 'wordpress-tourism-plugin' ); ?></h2>
			<p><?php esc_html_e( 'Download all tourism packages as CSV.', 'wordpress-tourism-plugin' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wtp_export_packages', 'wtp_export_nonce' ); ?>
				<input type="hidden" name="action" value="wtp_export_packages" />
				<?php submit_button( __( 'Export CSV', 'wordpress-tourism-plugin' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Import Packages', 'wordpress-tourism-plugin' ); ?></h2>
			<p><?php esc_html_e( 'CSV headers must include: destination, transport_type, departure_date, number_of_days, accommodation, transfer, baggage, excursions, observation, image_1, image_2, image_3, image_4, image_5.', 'wordpress-tourism-plugin' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wtp_import_packages', 'wtp_import_nonce' ); ?>
				<input type="hidden" name="action" value="wtp_import_packages" />
				<input type="file" name="wtp_csv_file" accept=".csv,text/csv" required />
				<?php submit_button( __( 'Import CSV', 'wordpress-tourism-plugin' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Export package data to CSV.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wordpress-tourism-plugin' ) );
		}

		check_admin_referer( 'wtp_export_packages', 'wtp_export_nonce' );

		$filename = 'tourism-packages-' . gmdate( 'Y-m-d-His' ) . '.csv';
		$headers_sent = headers_sent();
		if ( $headers_sent ) {
			wp_die( esc_html__( 'Cannot export CSV because headers are already sent.', 'wordpress-tourism-plugin' ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Could not open export stream.', 'wordpress-tourism-plugin' ) );
		}

		$headers = array_merge( $this->fields, array( 'image_1', 'image_2', 'image_3', 'image_4', 'image_5' ) );
		fputcsv( $output, $headers );

		$packages = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		foreach ( $packages as $package ) {
			$row = array();
			foreach ( $this->fields as $field ) {
				$row[] = (string) get_post_meta( $package->ID, $field, true );
			}

			$images = get_post_meta( $package->ID, 'package_images', true );
			$images = is_array( $images ) ? array_slice( $images, 0, 5 ) : array();
			for ( $i = 0; $i < 5; $i++ ) {
				$row[] = isset( $images[ $i ] ) ? $images[ $i ] : '';
			}

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Import package data from CSV.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wordpress-tourism-plugin' ) );
		}

		check_admin_referer( 'wtp_import_packages', 'wtp_import_nonce' );

		if ( empty( $_FILES['wtp_csv_file']['tmp_name'] ) ) {
			$this->redirect_import( 0, __( 'No CSV file uploaded.', 'wordpress-tourism-plugin' ) );
		}

		$file = fopen( wp_unslash( $_FILES['wtp_csv_file']['tmp_name'] ), 'r' );
		if ( false === $file ) {
			$this->redirect_import( 0, __( 'Unable to read uploaded CSV file.', 'wordpress-tourism-plugin' ) );
		}

		$header = fgetcsv( $file );
		if ( empty( $header ) ) {
			fclose( $file );
			$this->redirect_import( 0, __( 'CSV file appears empty.', 'wordpress-tourism-plugin' ) );
		}

		$header_map = array();
		foreach ( $header as $index => $column ) {
			$header_map[ sanitize_key( $column ) ] = $index;
		}

		$required = array_merge( $this->fields, array( 'image_1', 'image_2', 'image_3', 'image_4', 'image_5' ) );
		foreach ( $required as $required_key ) {
			if ( ! array_key_exists( $required_key, $header_map ) ) {
				fclose( $file );
				$this->redirect_import( 0, sprintf( __( 'Missing required column: %s', 'wordpress-tourism-plugin' ), $required_key ) );
			}
		}

		$imported = 0;
		while ( ( $row = fgetcsv( $file ) ) !== false ) {
			$destination = isset( $row[ $header_map['destination'] ] ) ? sanitize_text_field( $row[ $header_map['destination'] ] ) : '';
			if ( '' === $destination ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $destination,
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			foreach ( $this->fields as $field ) {
				$value = isset( $row[ $header_map[ $field ] ] ) ? $row[ $header_map[ $field ] ] : '';
				if ( 'number_of_days' === $field ) {
					$value = absint( $value );
				} elseif ( in_array( $field, array( 'excursions', 'observation' ), true ) ) {
					$value = sanitize_textarea_field( $value );
				} else {
					$value = sanitize_text_field( $value );
				}
				update_post_meta( $post_id, $field, $value );
			}

			$images = array();
			for ( $i = 1; $i <= 5; $i++ ) {
				$key      = 'image_' . $i;
				$image    = isset( $row[ $header_map[ $key ] ] ) ? $row[ $header_map[ $key ] ] : '';
				$images[] = esc_url_raw( trim( $image ) );
			}
			update_post_meta( $post_id, 'package_images', $images );

			$imported++;
		}

		fclose( $file );
		$this->redirect_import( $imported );
	}

	/**
	 * Redirect to import page with status.
	 *
	 * @param int    $imported Number imported.
	 * @param string $error Error message.
	 * @return void
	 */
	private function redirect_import( $imported = 0, $error = '' ) {
		$url = add_query_arg(
			array(
				'post_type' => self::POST_TYPE,
				'page'      => 'wtp-import-export',
				'imported'  => absint( $imported ),
			),
			admin_url( 'edit.php' )
		);

		if ( ! empty( $error ) ) {
			$url = add_query_arg( 'wtp_error', $error, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}
}

$wtp_tourism_plugin = new WTP_Tourism_Plugin();
$wtp_tourism_plugin->init();
