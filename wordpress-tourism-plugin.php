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
	const OPTION_FIELD_LABELS = 'wtp_field_labels';
	const OPTION_CATALOG_STYLE = 'wtp_catalog_style';
	const OPTION_CATALOG_LIMIT = 'wtp_catalog_limit';
	const OPTION_CATALOG_COLUMNS = 'wtp_catalog_columns';
	const OPTION_CATALOG_LAYOUT = 'wtp_catalog_layout';
	const OPTION_CATALOG_SHOW_WHATSAPP = 'wtp_catalog_show_whatsapp';
	const META_FIELD_VISIBILITY = 'wtp_field_visibility';

	/**
	 * @var string[]
	 */
	private $fields = array(
		'destination',
		'price',
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
	 * @var string[]
	 */
	private $default_field_labels = array(
		'destination'    => 'Destino',
		'price'          => 'Precio',
		'transport_type' => 'Transporte',
		'departure_date' => 'Fecha de salida',
		'number_of_days' => 'Días',
		'accommodation'  => 'Alojamiento',
		'transfer'       => 'Traslado',
		'baggage'        => 'Equipaje',
		'excursions'     => 'Excursiones',
		'observation'    => 'Observaciones',
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
		add_shortcode( 'tourism_packages', array( $this, 'render_tour_packages_shortcode' ) );
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
		wp_register_style( $handle, false, array(), '1.2.0' );
		wp_enqueue_style( $handle );

		$css = '
		.wtp-package-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem;margin:1.5rem 0;}
		.wtp-package-grid.wtp-package-grid--list{grid-template-columns:1fr;}
		.wtp-package-grid.wtp-package-grid--list .wtp-package-card{display:grid;grid-template-columns:minmax(220px,280px) minmax(0,1fr);}
		.wtp-package-grid.wtp-package-grid--list .wtp-package-card__media{aspect-ratio:auto;min-height:100%;}
		.wtp-package-grid.wtp-package-grid--list .wtp-package-card__content{padding:1.15rem;}
		.wtp-package-grid.wtp-package-grid--style-hero .wtp-package-card{border-radius:18px;border-color:#d7e8ff;box-shadow:0 16px 26px rgba(14,116,144,.13);}
		.wtp-package-grid.wtp-package-grid--style-hero .wtp-package-card__title a{color:#0b3c5d;}
		.wtp-package-grid.wtp-package-grid--style-blog .wtp-package-card{border-radius:8px;box-shadow:none;border-color:#dbe4ef;}
		.wtp-package-grid.wtp-package-grid--style-blog .wtp-package-card__media img{filter:saturate(.92);}
		.wtp-package-grid.wtp-package-grid--style-compact{gap:.9rem;}
		.wtp-package-grid.wtp-package-grid--style-compact .wtp-package-card__content{padding:.85rem;}
		.wtp-package-grid.wtp-package-grid--style-compact .wtp-package-card__title{font-size:1.05rem;}
		.wtp-package-card{background:#fff;border:1px solid #e8ecf1;border-radius:14px;overflow:hidden;box-shadow:0 10px 25px rgba(15,23,42,.06);display:flex;flex-direction:column;height:100%;}
		.wtp-package-card__media{aspect-ratio:16/10;background:#f4f7fb;overflow:hidden;}
		.wtp-package-card__media img{width:100%;height:100%;object-fit:cover;display:block;}
		.wtp-package-card__content{padding:1rem;display:flex;flex-direction:column;gap:.55rem;flex:1;}
		.wtp-package-card__title{font-size:1.2rem;font-weight:700;line-height:1.3;margin:0;}
		.wtp-package-card__title a{text-decoration:none;color:#0f172a;}
		.wtp-package-card__price{display:inline-block;font-size:1.2rem;font-weight:800;color:#0f172a;background:#ecfeff;border:1px solid #a5f3fc;padding:.25rem .55rem;border-radius:999px;}
		.wtp-meta{margin:0;padding:0;list-style:none;display:grid;gap:.35rem;color:#334155;}
		.wtp-meta strong{color:#0f172a;}
		.wtp-observation{margin:0;color:#475569;}
		.wtp-card-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:auto;padding-top:.4rem;}
		.wtp-button{display:inline-flex;align-items:center;justify-content:center;padding:.65rem .95rem;border-radius:8px;text-decoration:none;font-weight:600;font-size:.95rem;transition:.2s ease;}
		.wtp-button--primary{background:#0f172a;color:#fff;}
		.wtp-button--primary:hover{background:#1e293b;color:#fff;}
		.wtp-button--whatsapp{background:#25d366;color:#fff;}
		.wtp-button--whatsapp:hover{background:#1fa855;color:#fff;}
		.wtp-single{max-width:1040px;margin:2rem auto;padding:1.25rem;background:linear-gradient(165deg,#f8fbff,#ffffff);border:1px solid #e6edf5;border-radius:18px;display:grid;gap:1.4rem;box-shadow:0 16px 30px rgba(15,23,42,.07);}
		.wtp-single__header p{margin:0;font-size:.8rem;letter-spacing:.08em;text-transform:uppercase;color:#0ea5a4;font-weight:700;}
		.wtp-single__header h1{margin:.35rem 0;font-size:2.2rem;line-height:1.2;color:#0f172a;}
		.wtp-single__price{margin:0;font-size:2rem;font-weight:800;line-height:1.1;color:#0f172a;}
		.wtp-gallery{display:grid;gap:.85rem;}
		.wtp-gallery__main{max-width:780px;margin:0 auto;}
		.wtp-gallery__main img{width:100%;height:420px;object-fit:cover;border-radius:14px;box-shadow:0 14px 28px rgba(15,23,42,.12);}
		.wtp-gallery__thumbs{display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap;}
		.wtp-gallery__thumb{border:2px solid transparent;border-radius:12px;padding:0;background:#fff;cursor:pointer;overflow:hidden;box-shadow:0 7px 16px rgba(15,23,42,.1);}
		.wtp-gallery__thumb img{width:100px;height:72px;object-fit:cover;display:block;}
		.wtp-gallery__thumb.is-active{border-color:#0ea5a4;}
		.wtp-detail-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.7rem 1rem;padding:0;margin:0;list-style:none;}
		.wtp-detail-list li{padding:.7rem .85rem;background:#f8fafc;border-radius:10px;color:#334155;}
		.wtp-detail-list strong{display:block;color:#0f172a;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem;}
		.wtp-panel{background:#fff;border:1px solid #e8ecf1;border-radius:12px;padding:1rem;}
		.wtp-panel h3{margin-top:0;font-size:1rem;}
		@media (max-width:640px){.wtp-button{width:100%;}.wtp-single{padding:1rem;}.wtp-single__header h1{font-size:1.7rem;}.wtp-gallery__main img{height:270px;}.wtp-gallery__thumb img{width:74px;height:58px;}.wtp-package-grid.wtp-package-grid--list .wtp-package-card{grid-template-columns:1fr;}}
		';

		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Render package list shortcode.
	 *
	 * @return string
	 */
	public function render_tour_packages_shortcode( $atts = array() ) {
		$raw_atts = is_array( $atts ) ? $atts : array();

		$default_limit         = $this->sanitize_catalog_limit( get_option( self::OPTION_CATALOG_LIMIT, 6 ) );
		$default_columns       = $this->sanitize_catalog_columns( get_option( self::OPTION_CATALOG_COLUMNS, 3 ) );
		$default_layout        = $this->sanitize_catalog_layout( get_option( self::OPTION_CATALOG_LAYOUT, 'grid' ) );
		$default_show_whatsapp = $this->sanitize_catalog_toggle( get_option( self::OPTION_CATALOG_SHOW_WHATSAPP, 'yes' ) );

		$atts = shortcode_atts(
			array(
				'columns'             => $default_columns,
				'limit'               => $default_limit,
				'layout'              => $default_layout,
				'style'               => '',
				'show_image'          => 'yes',
				'show_date'           => 'yes',
				'show_departure_date' => 'yes',
				'show_days'           => 'yes',
				'show_whatsapp'       => $default_show_whatsapp,
			),
			$atts,
			'tourism_packages'
		);

		$field_labels = $this->get_field_labels();
		$catalog_style = $this->sanitize_catalog_style( $atts['style'] );
		if ( '' === $catalog_style ) {
			$catalog_style = $this->sanitize_catalog_style( get_option( self::OPTION_CATALOG_STYLE, 'hero' ) );
		}

		$columns = $this->sanitize_catalog_columns( $atts['columns'] );
		$limit   = $this->sanitize_catalog_limit( $atts['limit'] );
		$layout  = $this->sanitize_catalog_layout( $atts['layout'] );

		$show_image          = $this->shortcode_flag_to_bool( $atts['show_image'] );
		$show_departure_date = array_key_exists( 'show_date', $raw_atts )
			? $this->shortcode_flag_to_bool( $atts['show_date'] )
			: $this->shortcode_flag_to_bool( $atts['show_departure_date'] );
		$show_days           = $this->shortcode_flag_to_bool( $atts['show_days'] );
		$show_whatsapp       = $this->shortcode_flag_to_bool( $atts['show_whatsapp'] );

		$packages = $this->get_sorted_catalog_packages( $limit );

		if ( empty( $packages ) ) {
			return '<p>' . esc_html__( 'No tourism packages available at the moment.', 'wordpress-tourism-plugin' ) . '</p>';
		}

		ob_start();
		echo '<div class="wtp-package-grid wtp-package-grid--' . esc_attr( $layout ) . ' wtp-package-grid--style-' . esc_attr( $catalog_style ) . '" style="--wtp-columns:' . esc_attr( $columns ) . ';grid-template-columns:' . ( 'list' === $layout ? '1fr' : 'repeat(' . $columns . ',minmax(0,1fr))' ) . ';">';
		$whatsapp_number = $this->get_whatsapp_number();

		foreach ( $packages as $package ) {
			$field_visibility = $this->get_package_field_visibility( $package->ID );
			$images          = get_post_meta( $package->ID, 'package_images', true );
			$images          = is_array( $images ) ? $images : array();
			$main_image      = ! empty( $images[0] ) ? $images[0] : '';
			$destination     = $this->get_package_value( $package->ID, 'destination' );
			$departure_date  = $this->format_departure_date( $this->get_package_value( $package->ID, 'departure_date' ) );
			$number_of_days  = $this->get_package_value( $package->ID, 'number_of_days' );
			$transport_type  = $this->get_package_value( $package->ID, 'transport_type' );
			$price           = $this->format_package_price( $this->get_package_value( $package->ID, 'price' ) );
			$observation     = $this->get_package_value( $package->ID, 'observation' );
			$permalink       = get_permalink( $package->ID );
			$whatsapp_url    = $this->build_whatsapp_url( $whatsapp_number, $destination, $this->get_package_value( $package->ID, 'departure_date' ) );

			echo '<article class="wtp-package-card">';
			if ( $show_image ) {
				echo '<a class="wtp-package-card__media" href="' . esc_url( $permalink ) . '">';
				if ( ! empty( $main_image ) ) {
					echo '<img src="' . esc_url( $main_image ) . '" alt="' . esc_attr( $destination ) . '" />';
				} else {
					echo '<img src="https://via.placeholder.com/800x500?text=' . rawurlencode( $destination ) . '" alt="' . esc_attr( $destination ) . '" />';
				}
				echo '</a>';
			}
			echo '<div class="wtp-package-card__content">';
			echo '<h3 class="wtp-package-card__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $destination ) . '</a></h3>';
			if ( ! empty( $field_visibility['price'] ) && '' !== trim( $price ) ) {
				echo '<p class="wtp-package-card__price">' . esc_html( $price ) . '</p>';
			}
			echo '<ul class="wtp-meta">';
			if ( $show_departure_date && ! empty( $field_visibility['departure_date'] ) && '' !== trim( $departure_date ) ) {
				echo '<li><strong>' . esc_html( $field_labels['departure_date'] ) . ':</strong> ' . esc_html( $departure_date ) . '</li>';
			}
			if ( $show_days && ! empty( $field_visibility['number_of_days'] ) && '' !== trim( $number_of_days ) ) {
				echo '<li><strong>' . esc_html( $field_labels['number_of_days'] ) . ':</strong> ' . esc_html( $number_of_days ) . '</li>';
			}
			if ( ! empty( $field_visibility['transport_type'] ) && '' !== trim( $transport_type ) ) {
				echo '<li><strong>' . esc_html( $field_labels['transport_type'] ) . ':</strong> ' . esc_html( $transport_type ) . '</li>';
			}
			echo '</ul>';
			if ( ! empty( $field_visibility['observation'] ) && '' !== trim( $observation ) ) {
				echo '<p class="wtp-observation">' . esc_html( wp_trim_words( $observation, 20, '...' ) ) . '</p>';
			}
			echo '<div class="wtp-card-actions">';
			echo '<a class="wtp-button wtp-button--primary" href="' . esc_url( $permalink ) . '">' . esc_html__( 'Ver paquete', 'wordpress-tourism-plugin' ) . '</a>';
			if ( $show_whatsapp && ! empty( $whatsapp_url ) ) {
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
	 * Get published catalog packages sorted by nearest departure date.
	 *
	 * @param int $limit Number of packages to return.
	 * @return WP_Post[]
	 */
	private function get_sorted_catalog_packages( $limit = -1 ) {
		$packages = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		usort(
			$packages,
			array( $this, 'sort_packages_by_departure_date' )
		);

		if ( $limit > 0 ) {
			$packages = array_slice( $packages, 0, $limit );
		}

		return $packages;
	}

	/**
	 * Sort package posts by nearest departure date.
	 *
	 * @param WP_Post $a First package.
	 * @param WP_Post $b Second package.
	 * @return int
	 */
	private function sort_packages_by_departure_date( $a, $b ) {
		$date_a = strtotime( $this->get_package_value( $a->ID, 'departure_date' ) );
		$date_b = strtotime( $this->get_package_value( $b->ID, 'departure_date' ) );

		if ( false === $date_a && false === $date_b ) {
			return 0;
		}

		if ( false === $date_a ) {
			return 1;
		}

		if ( false === $date_b ) {
			return -1;
		}

		return $date_a <=> $date_b;
	}

	/**
	 * Convert shortcode attribute flag into boolean.
	 *
	 * @param string $value Attribute value.
	 * @return bool
	 */
	private function shortcode_flag_to_bool( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on' ), true );
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

		$post_id          = get_the_ID();
		$destination      = $this->get_package_value( $post_id, 'destination' );
		$transport_type   = $this->get_package_value( $post_id, 'transport_type' );
		$price            = $this->format_package_price( $this->get_package_value( $post_id, 'price' ) );
		$departure_raw    = $this->get_package_value( $post_id, 'departure_date' );
		$departure_date   = $this->format_departure_date( $departure_raw );
		$days             = $this->get_package_value( $post_id, 'number_of_days' );
		$accommodation    = $this->get_package_value( $post_id, 'accommodation' );
		$transfer         = $this->get_package_value( $post_id, 'transfer' );
		$baggage          = $this->get_package_value( $post_id, 'baggage' );
		$excursions       = $this->get_package_value( $post_id, 'excursions' );
		$observation      = $this->get_package_value( $post_id, 'observation' );
		$images           = get_post_meta( $post_id, 'package_images', true );
		$images           = is_array( $images ) ? array_filter( array_slice( $images, 0, 5 ) ) : array();
		$whatsapp_url     = $this->build_whatsapp_url( $this->get_whatsapp_number(), $destination, $departure_raw );
		$field_labels     = $this->get_field_labels();
		$field_visibility = $this->get_package_field_visibility( $post_id );
		$main_image_id    = 'wtp-main-image-' . $post_id;

		ob_start();
		echo '<section class="wtp-single">';
		echo '<header class="wtp-single__header">';
		echo '<p>' . esc_html__( 'Paquete turístico', 'wordpress-tourism-plugin' ) . '</p>';
		echo '<h1>' . esc_html( $destination ) . '</h1>';
		if ( ! empty( $field_visibility['price'] ) && '' !== trim( $price ) ) {
			echo '<p class="wtp-single__price">' . esc_html( $price ) . '</p>';
		}
		echo '</header>';

		if ( ! empty( $images ) ) {
			echo '<div class="wtp-gallery">';
			echo '<div class="wtp-gallery__main">';
			echo '<img id="' . esc_attr( $main_image_id ) . '" src="' . esc_url( reset( $images ) ) . '" alt="' . esc_attr( $destination ) . '" />';
			echo '</div>';

			if ( count( $images ) > 1 ) {
				echo '<div class="wtp-gallery__thumbs">';
				foreach ( $images as $index => $image_url ) {
					$active_class = 0 === $index ? ' is-active' : '';
					echo '<button class="wtp-gallery__thumb' . esc_attr( $active_class ) . '" type="button" data-main-target="' . esc_attr( $main_image_id ) . '" data-image="' . esc_url( $image_url ) . '"><img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $destination ) . '" /></button>';
				}
				echo '</div>';
			}
			echo '</div>';

			if ( count( $images ) > 1 ) {
				echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".wtp-gallery__thumb").forEach(function(thumb){thumb.addEventListener("click",function(){var targetId=this.getAttribute("data-main-target");var imgUrl=this.getAttribute("data-image");var target=document.getElementById(targetId);if(!target||!imgUrl){return;}target.src=imgUrl;document.querySelectorAll(".wtp-gallery__thumb[data-main-target=\""+targetId+"\"]").forEach(function(item){item.classList.remove("is-active");});this.classList.add("is-active");});});});</script>';
			}
		}

		$detail_values = array(
			'destination'    => $destination,
			'transport_type' => $transport_type,
			'departure_date' => $departure_date,
			'number_of_days' => $days,
			'accommodation'  => $accommodation,
			'transfer'       => $transfer,
			'baggage'        => $baggage,
		);

		echo '<ul class="wtp-detail-list">';
		foreach ( $detail_values as $field => $value ) {
			if ( empty( $field_visibility[ $field ] ) || '' === trim( (string) $value ) ) {
				continue;
			}

			echo '<li><strong>' . esc_html( $field_labels[ $field ] ) . '</strong>' . esc_html( $value ) . '</li>';
		}
		echo '</ul>';

		if ( ! empty( $field_visibility['excursions'] ) && '' !== trim( $excursions ) ) {
			echo '<div class="wtp-panel"><h3>' . esc_html( $field_labels['excursions'] ) . '</h3><p>' . nl2br( esc_html( $excursions ) ) . '</p></div>';
		}
		if ( ! empty( $field_visibility['observation'] ) && '' !== trim( $observation ) ) {
			echo '<div class="wtp-panel"><h3>' . esc_html( $field_labels['observation'] ) . '</h3><p>' . nl2br( esc_html( $observation ) ) . '</p></div>';
		}
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
	 * Normalize stored package price to a numeric string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalize_price_value( $value ) {
		$normalized = preg_replace( '/[^0-9.]/', '', (string) $value );
		if ( '' === $normalized ) {
			return '';
		}

		return (string) (float) $normalized;
	}

	/**
	 * Format package price for frontend output.
	 *
	 * @param string $value Raw stored numeric value.
	 * @return string
	 */
	private function format_package_price( $value ) {
		$numeric = $this->normalize_price_value( $value );
		if ( '' === $numeric ) {
			return '';
		}

		$amount = (float) $numeric;
		$decimals = ( floor( $amount ) === $amount ) ? 0 : 2;

		/* translators: %s: formatted numeric package price. */
		return sprintf( __( 'USD %s', 'wordpress-tourism-plugin' ), number_format_i18n( $amount, $decimals ) );
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
		$field_labels     = $this->get_field_labels();
		$field_visibility = $this->get_package_field_visibility( $post->ID );

		echo '<table class="form-table" role="presentation">';
		$this->render_text_row( $post->ID, 'destination', __( 'Destination', 'wordpress-tourism-plugin' ) );
		$this->render_price_row( $post->ID, 'price', __( 'Price', 'wordpress-tourism-plugin' ) );
		$this->render_text_row( $post->ID, 'transport_type', __( 'Transport Type', 'wordpress-tourism-plugin' ) );
		$this->render_date_row( $post->ID, 'departure_date', __( 'Departure Date', 'wordpress-tourism-plugin' ) );
		$this->render_number_row( $post->ID, 'number_of_days', __( 'Number of Days', 'wordpress-tourism-plugin' ) );
		$this->render_text_row( $post->ID, 'accommodation', __( 'Accommodation', 'wordpress-tourism-plugin' ) );
		$this->render_text_row( $post->ID, 'transfer', __( 'Transfer', 'wordpress-tourism-plugin' ) );
		$this->render_text_row( $post->ID, 'baggage', __( 'Baggage', 'wordpress-tourism-plugin' ) );
		$this->render_textarea_row( $post->ID, 'excursions', __( 'Excursions', 'wordpress-tourism-plugin' ) );
		$this->render_textarea_row( $post->ID, 'observation', __( 'Observation', 'wordpress-tourism-plugin' ) );
		echo '</table>';

		echo '<h4>' . esc_html__( 'Field Visibility', 'wordpress-tourism-plugin' ) . '</h4>';
		echo '<p>' . esc_html__( 'Choose which fields are shown on the frontend for this package. Empty fields are hidden automatically.', 'wordpress-tourism-plugin' ) . '</p>';
		echo '<fieldset>';
		foreach ( $this->fields as $field ) {
			echo '<label style="display:block;margin-bottom:8px;">';
			echo '<input type="checkbox" name="' . esc_attr( self::META_FIELD_VISIBILITY ) . '[' . esc_attr( $field ) . ']" value="1" ' . checked( ! empty( $field_visibility[ $field ] ), true, false ) . ' /> ';
			echo esc_html( $field_labels[ $field ] );
			echo '</label>';
		}
		echo '</fieldset>';

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

	private function render_price_row( $post_id, $field, $label ) {
		$value = $this->normalize_price_value( get_post_meta( $post_id, $field, true ) );
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="number" min="0" step="0.01" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Store only the numeric amount. Currency symbol is added automatically on display.', 'wordpress-tourism-plugin' ) . '</p></td>';
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
			} elseif ( 'price' === $field ) {
				$value = $this->normalize_price_value( $value );
			} elseif ( in_array( $field, array( 'excursions', 'observation' ), true ) ) {
				$value = sanitize_textarea_field( $value );
			} else {
				$value = sanitize_text_field( $value );
			}

			update_post_meta( $post_id, $field, $value );
		}

		$visibility_raw = isset( $_POST[ self::META_FIELD_VISIBILITY ] ) && is_array( $_POST[ self::META_FIELD_VISIBILITY ] )
			? wp_unslash( $_POST[ self::META_FIELD_VISIBILITY ] )
			: array();
		update_post_meta( $post_id, self::META_FIELD_VISIBILITY, $this->sanitize_field_visibility( $visibility_raw ) );

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
			__( 'Display Settings', 'wordpress-tourism-plugin' ),
			__( 'Display Settings', 'wordpress-tourism-plugin' ),
			'manage_options',
			'wtp-display-settings',
			array( $this, 'render_display_settings_page' )
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

		register_setting(
			'wtp_display_settings',
			self::OPTION_FIELD_LABELS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_field_labels' ),
				'default'           => $this->default_field_labels,
			)
		);

		register_setting(
			'wtp_display_settings',
			self::OPTION_CATALOG_STYLE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_catalog_style' ),
				'default'           => 'hero',
			)
		);

		register_setting(
			'wtp_display_settings',
			self::OPTION_CATALOG_LIMIT,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_catalog_limit' ),
				'default'           => 6,
			)
		);

		register_setting(
			'wtp_display_settings',
			self::OPTION_CATALOG_COLUMNS,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_catalog_columns' ),
				'default'           => 3,
			)
		);

		register_setting(
			'wtp_display_settings',
			self::OPTION_CATALOG_LAYOUT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_catalog_layout' ),
				'default'           => 'grid',
			)
		);

		register_setting(
			'wtp_display_settings',
			self::OPTION_CATALOG_SHOW_WHATSAPP,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_catalog_toggle' ),
				'default'           => 'yes',
			)
		);

		add_settings_section(
			'wtp_display_section',
			__( 'Field Labels', 'wordpress-tourism-plugin' ),
			'__return_empty_string',
			'wtp-display-settings'
		);

		add_settings_field(
			'wtp_field_display_options',
			__( 'Field labels', 'wordpress-tourism-plugin' ),
			array( $this, 'render_field_display_settings' ),
			'wtp-display-settings',
			'wtp_display_section'
		);

		add_settings_field(
			'wtp_catalog_style',
			__( 'Default catalog style', 'wordpress-tourism-plugin' ),
			array( $this, 'render_catalog_style_settings' ),
			'wtp-display-settings',
			'wtp_display_section'
		);

		add_settings_field(
			'wtp_catalog_defaults',
			__( 'Catalog defaults', 'wordpress-tourism-plugin' ),
			array( $this, 'render_catalog_defaults_settings' ),
			'wtp-display-settings',
			'wtp_display_section'
		);

		add_settings_section(
			'wtp_shortcode_help_section',
			__( 'Shortcode Help', 'wordpress-tourism-plugin' ),
			'__return_empty_string',
			'wtp-display-settings'
		);

		add_settings_field(
			'wtp_shortcode_help',
			__( 'Catalog shortcode reference', 'wordpress-tourism-plugin' ),
			array( $this, 'render_shortcode_help_box' ),
			'wtp-display-settings',
			'wtp_shortcode_help_section'
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
	 * Render display settings page.
	 *
	 * @return void
	 */
	public function render_display_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tourism Packages: Display Settings', 'wordpress-tourism-plugin' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wtp_display_settings' );
				do_settings_sections( 'wtp-display-settings' );
				submit_button( __( 'Save Settings', 'wordpress-tourism-plugin' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render label and visibility controls for frontend fields.
	 *
	 * @return void
	 */
	public function render_field_display_settings() {
		$labels     = $this->get_field_labels();

		echo '<p class="description" style="margin-bottom:12px;">' . esc_html__( 'Customize frontend labels. Field visibility is managed in each package.', 'wordpress-tourism-plugin' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:780px;">';
		echo '<thead><tr><th>' . esc_html__( 'Field', 'wordpress-tourism-plugin' ) . '</th><th>' . esc_html__( 'Visible label', 'wordpress-tourism-plugin' ) . '</th></tr></thead><tbody>';

		foreach ( $this->fields as $field ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $this->default_field_labels[ $field ] ) . '</strong></td>';
			echo '<td><input type="text" class="regular-text" name="' . esc_attr( self::OPTION_FIELD_LABELS ) . '[' . esc_attr( $field ) . ']" value="' . esc_attr( $labels[ $field ] ) . '" /></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description" style="margin-top:10px;">' . esc_html__( 'Use shortcode attributes to override the defaults configured below.', 'wordpress-tourism-plugin' ) . '</p>';
	}

	/**
	 * Render catalog style selector.
	 *
	 * @return void
	 */
	public function render_catalog_style_settings() {
		$current = $this->sanitize_catalog_style( get_option( self::OPTION_CATALOG_STYLE, 'hero' ) );
		$styles  = $this->get_catalog_style_options();

		echo '<select id="wtp_catalog_style" name="' . esc_attr( self::OPTION_CATALOG_STYLE ) . '">';
		foreach ( $styles as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose the default visual style used by [tourism_packages]. You can override it in each shortcode using style="...".', 'wordpress-tourism-plugin' ) . '</p>';
	}

	/**
	 * Render defaults for catalog shortcode behavior.
	 *
	 * @return void
	 */
	public function render_catalog_defaults_settings() {
		$limit         = $this->sanitize_catalog_limit( get_option( self::OPTION_CATALOG_LIMIT, 6 ) );
		$columns       = $this->sanitize_catalog_columns( get_option( self::OPTION_CATALOG_COLUMNS, 3 ) );
		$layout        = $this->sanitize_catalog_layout( get_option( self::OPTION_CATALOG_LAYOUT, 'grid' ) );
		$show_whatsapp = $this->sanitize_catalog_toggle( get_option( self::OPTION_CATALOG_SHOW_WHATSAPP, 'yes' ) );

		echo '<fieldset>';
		echo '<label for="wtp_catalog_limit"><strong>' . esc_html__( 'Default number of packages', 'wordpress-tourism-plugin' ) . '</strong></label><br />';
		echo '<input type="number" min="-1" step="1" id="wtp_catalog_limit" name="' . esc_attr( self::OPTION_CATALOG_LIMIT ) . '" value="' . esc_attr( $limit ) . '" class="small-text" />';
		echo '<p class="description" style="margin:6px 0 12px;">' . esc_html__( 'Use -1 to show all packages.', 'wordpress-tourism-plugin' ) . '</p>';

		echo '<label for="wtp_catalog_columns"><strong>' . esc_html__( 'Default columns per row', 'wordpress-tourism-plugin' ) . '</strong></label><br />';
		echo '<input type="number" min="1" max="4" step="1" id="wtp_catalog_columns" name="' . esc_attr( self::OPTION_CATALOG_COLUMNS ) . '" value="' . esc_attr( $columns ) . '" class="small-text" />';
		echo '<p class="description" style="margin:6px 0 12px;">' . esc_html__( 'Applies to grid layout and can be overridden with columns="..." in the shortcode.', 'wordpress-tourism-plugin' ) . '</p>';

		echo '<label for="wtp_catalog_layout"><strong>' . esc_html__( 'Default layout style', 'wordpress-tourism-plugin' ) . '</strong></label><br />';
		echo '<select id="wtp_catalog_layout" name="' . esc_attr( self::OPTION_CATALOG_LAYOUT ) . '">';
		echo '<option value="grid" ' . selected( $layout, 'grid', false ) . '>' . esc_html__( 'Grid', 'wordpress-tourism-plugin' ) . '</option>';
		echo '<option value="list" ' . selected( $layout, 'list', false ) . '>' . esc_html__( 'List', 'wordpress-tourism-plugin' ) . '</option>';
		echo '</select>';
		echo '<p class="description" style="margin:6px 0 12px;">' . esc_html__( 'Controls whether package cards are shown in a grid or in a vertical list.', 'wordpress-tourism-plugin' ) . '</p>';

		echo '<label for="wtp_catalog_show_whatsapp"><strong>' . esc_html__( 'Show WhatsApp button by default', 'wordpress-tourism-plugin' ) . '</strong></label><br />';
		echo '<select id="wtp_catalog_show_whatsapp" name="' . esc_attr( self::OPTION_CATALOG_SHOW_WHATSAPP ) . '">';
		echo '<option value="yes" ' . selected( $show_whatsapp, 'yes', false ) . '>' . esc_html__( 'Yes', 'wordpress-tourism-plugin' ) . '</option>';
		echo '<option value="no" ' . selected( $show_whatsapp, 'no', false ) . '>' . esc_html__( 'No', 'wordpress-tourism-plugin' ) . '</option>';
		echo '</select>';
		echo '<p class="description" style="margin:6px 0 0;">' . esc_html__( 'The shortcode can still override this with show_whatsapp="yes|no".', 'wordpress-tourism-plugin' ) . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Render shortcode documentation box in admin.
	 *
	 * @return void
	 */
	public function render_shortcode_help_box() {
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:14px;max-width:860px;">';
		echo '<p><strong>' . esc_html__( 'Available shortcode:', 'wordpress-tourism-plugin' ) . '</strong> <code>[tourism_packages]</code> <span style="opacity:.7;">(' . esc_html__( 'alias:', 'wordpress-tourism-plugin' ) . ' <code>[tour_packages]</code>)</span></p>';
		echo '<p><strong>' . esc_html__( 'Example usage:', 'wordpress-tourism-plugin' ) . '</strong></p>';
		echo '<ul style="list-style:disc;padding-left:20px;">';
		echo '<li><code>[tourism_packages]</code></li>';
		echo '<li><code>[tourism_packages limit="6" columns="3" layout="grid"]</code></li>';
		echo '<li><code>[tourism_packages layout="list" show_image="no" show_date="yes" show_days="yes" show_whatsapp="no"]</code></li>';
		echo '<li><code>[tourism_packages style="compact" limit="4"]</code></li>';
		echo '</ul>';

		echo '<table class="widefat striped" style="max-width:100%;margin-top:10px;">';
		echo '<thead><tr><th>' . esc_html__( 'Attribute', 'wordpress-tourism-plugin' ) . '</th><th>' . esc_html__( 'Accepted values', 'wordpress-tourism-plugin' ) . '</th><th>' . esc_html__( 'Description', 'wordpress-tourism-plugin' ) . '</th></tr></thead><tbody>';
		echo '<tr><td><code>limit</code></td><td><code>-1</code> or positive number</td><td>' . esc_html__( 'How many packages to show. -1 displays all packages.', 'wordpress-tourism-plugin' ) . '</td></tr>';
		echo '<tr><td><code>columns</code></td><td>1-4</td><td>' . esc_html__( 'Number of package cards per row when layout is grid.', 'wordpress-tourism-plugin' ) . '</td></tr>';
		echo '<tr><td><code>layout</code></td><td><code>grid</code>, <code>list</code></td><td>' . esc_html__( 'Card arrangement style for the catalog.', 'wordpress-tourism-plugin' ) . '</td></tr>';
		echo '<tr><td><code>style</code></td><td><code>hero</code>, <code>compact</code>, <code>blog</code></td><td>' . esc_html__( 'Visual card style theme.', 'wordpress-tourism-plugin' ) . '</td></tr>';
		echo '<tr><td><code>show_image</code></td><td><code>yes/no</code>, <code>true/false</code>, <code>1/0</code></td><td>' . esc_html__( 'Show or hide package images.', 'wordpress-tourism-plugin' ) . '</td></tr>';
		echo '<tr><td><code>show_date</code></td><td><code>yes/no</code>, <code>true/false</code>, <code>1/0</code></td><td>' . esc_html__( 'Show or hide departure date in each card.', 'wordpress-tourism-plugin' ) . '</td></tr>';
		echo '<tr><td><code>show_days</code></td><td><code>yes/no</code>, <code>true/false</code>, <code>1/0</code></td><td>' . esc_html__( 'Show or hide number of days in each card.', 'wordpress-tourism-plugin' ) . '</td></tr>';
		echo '<tr><td><code>show_whatsapp</code></td><td><code>yes/no</code>, <code>true/false</code>, <code>1/0</code></td><td>' . esc_html__( 'Show or hide the WhatsApp inquiry button.', 'wordpress-tourism-plugin' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '<p class="description" style="margin-top:10px;">' . esc_html__( 'Shortcode attributes always override global defaults configured in this page.', 'wordpress-tourism-plugin' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Allowed catalog style options.
	 *
	 * @return string[]
	 */
	private function get_catalog_style_options() {
		return array(
			'hero'    => __( 'Homepage / Hero cards', 'wordpress-tourism-plugin' ),
			'compact' => __( 'Normal page / Compact cards', 'wordpress-tourism-plugin' ),
			'blog'    => __( 'Blog-style section', 'wordpress-tourism-plugin' ),
		);
	}

	/**
	 * Sanitize default package limit.
	 *
	 * @param mixed $limit Limit value.
	 * @return int
	 */
	public function sanitize_catalog_limit( $limit ) {
		$limit = intval( $limit );

		if ( $limit < -1 ) {
			return -1;
		}

		return 0 === $limit ? 6 : $limit;
	}

	/**
	 * Sanitize default columns for catalog cards.
	 *
	 * @param mixed $columns Column count.
	 * @return int
	 */
	public function sanitize_catalog_columns( $columns ) {
		$columns = absint( $columns );

		if ( $columns < 1 ) {
			$columns = 3;
		}

		return min( 4, $columns );
	}

	/**
	 * Sanitize layout value.
	 *
	 * @param mixed $layout Layout type.
	 * @return string
	 */
	public function sanitize_catalog_layout( $layout ) {
		$layout = sanitize_key( (string) $layout );

		return in_array( $layout, array( 'grid', 'list' ), true ) ? $layout : 'grid';
	}

	/**
	 * Sanitize yes/no toggle settings.
	 *
	 * @param mixed $value Toggle value.
	 * @return string
	 */
	public function sanitize_catalog_toggle( $value ) {
		return $this->shortcode_flag_to_bool( $value ) ? 'yes' : 'no';
	}

	/**
	 * Sanitize catalog style option.
	 *
	 * @param string $style Style slug.
	 * @return string
	 */
	public function sanitize_catalog_style( $style ) {
		$style = sanitize_key( (string) $style );
		if ( '' === $style ) {
			return '';
		}

		$allowed = array_keys( $this->get_catalog_style_options() );

		return in_array( $style, $allowed, true ) ? $style : 'hero';
	}

	/**
	 * Get frontend field labels.
	 *
	 * @return string[]
	 */
	private function get_field_labels() {
		$labels = get_option( self::OPTION_FIELD_LABELS, array() );
		if ( ! is_array( $labels ) ) {
			$labels = array();
		}

		$defaults = $this->default_field_labels;
		foreach ( $defaults as $field => $default_label ) {
			if ( empty( $labels[ $field ] ) ) {
				$labels[ $field ] = $default_label;
			}
		}

		return $labels;
	}

	/**
	 * Get per-package field visibility settings.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private function get_package_field_visibility( $post_id ) {
		$visibility = get_post_meta( $post_id, self::META_FIELD_VISIBILITY, true );
		if ( ! is_array( $visibility ) ) {
			$visibility = array();
		}

		return wp_parse_args( $visibility, $this->get_default_field_visibility() );
	}

	/**
	 * Default visibility values.
	 *
	 * @return int[]
	 */
	private function get_default_field_visibility() {
		$visibility = array();
		foreach ( $this->fields as $field ) {
			$visibility[ $field ] = 1;
		}

		return $visibility;
	}

	/**
	 * Sanitize customizable labels.
	 *
	 * @param array $labels Raw labels.
	 * @return string[]
	 */
	public function sanitize_field_labels( $labels ) {
		$sanitized = array();
		$labels    = is_array( $labels ) ? $labels : array();

		foreach ( $this->default_field_labels as $field => $default_label ) {
			$value = isset( $labels[ $field ] ) ? sanitize_text_field( $labels[ $field ] ) : '';
			$sanitized[ $field ] = '' !== $value ? $value : $default_label;
		}

		return $sanitized;
	}

	/**
	 * Sanitize visibility settings.
	 *
	 * @param array $visibility Raw visibility settings.
	 * @return int[]
	 */
	public function sanitize_field_visibility( $visibility ) {
		$sanitized  = array();
		$visibility = is_array( $visibility ) ? $visibility : array();

		foreach ( $this->fields as $field ) {
			$sanitized[ $field ] = isset( $visibility[ $field ] ) ? 1 : 0;
		}

		return $sanitized;
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
			<p><?php esc_html_e( 'CSV headers must include: destination, price, transport_type, departure_date, number_of_days, accommodation, transfer, baggage, excursions, observation, image_1, image_2, image_3, image_4, image_5.', 'wordpress-tourism-plugin' ); ?></p>
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
				} elseif ( 'price' === $field ) {
					$value = $this->normalize_price_value( $value );
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
