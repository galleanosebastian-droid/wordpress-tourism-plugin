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
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_package_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_post_wtp_import_packages', array( $this, 'handle_import' ) );
		add_action( 'admin_post_wtp_export_packages', array( $this, 'handle_export' ) );
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
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'menu_position'     => 25,
				'menu_icon'         => 'dashicons-palmtree',
				'supports'          => array( 'title' ),
				'capability_type'   => 'post',
				'has_archive'       => false,
				'show_in_rest'      => false,
			)
		);
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
			__( 'Import / Export Packages', 'wordpress-tourism-plugin' ),
			__( 'Import / Export', 'wordpress-tourism-plugin' ),
			'manage_options',
			'wtp-import-export',
			array( $this, 'render_import_export_page' )
		);
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
