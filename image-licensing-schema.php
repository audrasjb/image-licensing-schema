<?php
/*
Plugin Name: Image Licensing Schema – Structured Data for Google Images
Plugin URI: https://jeanbaptisteaudras.com/image-licensing-schema
Description: Provide Image Licensing Schema.org structured data for Google Images
Version: 1.0
Requires at least: 5.4
Requires PHP: 7.1
Author: audrasjb
Author URI: https://jeanbaptisteaudras.com
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: image-licensing-schema
*/

function imalisch_get_image_urls_from_content( $the_content ) {
	if ( is_admin() ) {
		return $the_content;
	}
	if ( 1 === get_transient( 'imalisch_post_images_licenses' ) || ! is_user_logged_in() ) {
		return $the_content;
	}
	libxml_use_internal_errors( true );
	$html = new DOMDocument();
	$html->loadHTML( mb_convert_encoding( $the_content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	$html->encoding = 'UTF-8';
	$imgs = $html->getElementsByTagName( 'img' );
	$urls = array();

	$existing_urls = (array) get_post_meta( get_the_ID(), '_imalisch_post_license', true );
	foreach ( $imgs as $img ) {
		$src = $img->getAttribute( 'src' );
		if ( isset( $existing_urls[$src] ) && ! empty( $existing_urls[$src] ) ) {
			$image_license_data = $existing_urls[$src];
		} else {
			$image_license_data = '';
		}
		$urls[] = array(
			'image_url' => $src,
			'image_license' => $image_license_data,
		);
	}
	update_post_meta( get_the_ID(), 'imalisch_post_images_licenses', $urls );
	set_transient( 'imalisch_post_images_licenses', 1, WEEK_IN_SECONDS );
	return $the_content;
}
add_filter( 'the_content', 'imalisch_get_image_urls_from_content', 15 );

function imalisch_add_schema_to_head() {
	$urls = get_post_meta( get_the_ID(), 'imalisch_post_images_licenses', true );
	if ( $urls ) {
		$license_option = get_option( 'imalisch_settings_license', '' );
		$license = '';
		if ( ! empty( $license_option ) ) {
			$license = esc_url( $license_option );
		}
		$page_option = get_option( 'imalisch_settings_page', '' );
		$page = '';
		if ( ! empty( $license_option ) ) {
			$page = esc_url( get_permalink( $page_option ) );
		}
		$i = 1;
		$script  = '<script type="application/ld+json">[';
		foreach ( $urls as $url ) {
			if ( empty( $license ) ) { continue; }
			if ( 'custom' === $license && empty( $page ) ) { continue; }
			$script .= '{"@context": "https://schema.org/","@type": "ImageObject","contentUrl": "' . esc_url( $url['image_url'] ) . '"';
			if ( ! empty( $url['image_license'] ) ) {
				$script .= ',"license": "' . esc_url( $url['image_license'] ) . '"';
			} elseif ( 'custom' === $license ) {
				$script .= ',"license": "' . esc_url( $page ) . '"';
			} elseif ( ! empty( $license ) ) {
				$script .= ',"license": "' . esc_url( $license ) . '"';
			}
			if ( ! empty( $page ) ) {
				$script .= ',"acquireLicensePage": "' . esc_url( $page ) . '"';
			}
			$script .= '}';
			if ( $i < count( $urls ) ) {
				$script .= ',';
			}
			$i++;
		}
		$script .= ']</script>';

		echo $script;
		echo "\n";
	}
}
add_action( 'wp_head', 'imalisch_add_schema_to_head' );

function imalisch_enqueue_admin_assets( $hook ) {
	if ( 'options-media.php' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'imalisch-admin-css', plugin_dir_url( __FILE__ ) . 'css/admin.css' );
	wp_enqueue_script( 'imalisch-admin-js', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ) );
}
add_action( 'admin_enqueue_scripts', 'imalisch_enqueue_admin_assets' );

function imalisch_settings_init() {
	register_setting( 'media', 'imalisch_settings_license', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', ) );
	register_setting( 'media', 'imalisch_settings_page', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', ) );
	add_settings_section(
		'imalisch_settings_section',
		__( 'Images Licensing Policy', 'image-licensing-schema' ),
		'imalisch_settings_section_callback',
		'media'
	);
	add_settings_field(
		'imalisch_settings_license',
		__( 'Select a license', 'image-licensing-schema' ),
		'imalisch_settings_select_license_callback',
		'media',
		'imalisch_settings_section'
	);
	add_settings_field(
		'imalisch_settings_page',
		__( 'Licensing Policy page', 'image-licensing-schema' ),
		'imalisch_settings_select_page_callback',
		'media',
		'imalisch_settings_section'
	);
}
add_action( 'admin_init', 'imalisch_settings_init' );

function imalisch_settings_section_callback() {
	?>
	<p><?php esc_html_e( 'First choose a license. Then, you can optionally provide a link to your Licensing Policy page (a section in your Legal Notice page, or a dedicated page, if possible).', 'image-licensing-schema' ); ?></p>
	<?php
}

function imalisch_settings_select_license_callback() {
	$options = get_option( 'imalisch_settings_license' );
	if ( isset( $options ) && ! empty( $options ) ) {
		$option = esc_html( $options );
	} else {
		$option = '';			   
	}
	?>
	<p class="imalisch_license_type">
		<?php
		/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
		$url = __( 'https://creativecommons.org/publicdomain/zero/1.0/', 'image-licensing-schema' );
		?>
		<input type="radio" name="imalisch_settings_license" id="imalisch_settings_cc0" value="<?php echo esc_url( $url ); ?>" <?php checked( $option, $url, true ); ?>>
		<label for="imalisch_settings_cc0">
			<?php esc_html_e( 'Public Domain (CCO)', 'image-licensing-schema' ); ?>
		</label>
		<button class="imalisch-button-link button-link"><small><?php esc_html_e( 'show details', 'image-licensing-schema' ); ?></small></button>
		<div class="imalisch_toggle_license_details">
			<span class="description"><?php esc_attr_e( 'CC0 allows reusers to distribute, remix, adapt, and build upon the material in any medium or format, with no conditions.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><a href="<?php echo $url; ?>" target="_blank"><?php esc_html_e( 'More information', 'image-licensing-schema' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens a new tab)', 'image-licensing-schema' ); ?></span></a></span>
		</div>
	</p>

	<p class="imalisch_license_type">
		<?php
		/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
		$url = __( 'https://creativecommons.org/licenses/by/4.0/', 'image-licensing-schema' );
		?>
		<input type="radio" name="imalisch_settings_license" id="imalisch_settings_cc_by" value="<?php echo esc_url( $url ); ?>" <?php checked( $option, $url, true ); ?>>
		<label for="imalisch_settings_cc_by">
			<?php esc_html_e( 'Creative Commons BY', 'image-licensing-schema' ); ?>
		</label>
		<button class="imalisch-button-link button-link"><small><?php esc_html_e( 'show details', 'image-licensing-schema' ); ?></small></button>
		<div class="imalisch_toggle_license_details">
			<span class="description"><?php esc_attr_e( 'BY: Credit must be given to the creator.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><a href="<?php echo $url; ?>" target="_blank"><?php esc_html_e( 'More information', 'image-licensing-schema' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens a new tab)', 'image-licensing-schema' ); ?></span></a></span>
		</div>
	</p>

	<p class="imalisch_license_type">
		<?php
		/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
		$url = __( 'https://creativecommons.org/licenses/by-sa/4.0/', 'image-licensing-schema' );
		?>
		<input type="radio" name="imalisch_settings_license" id="imalisch_settings_cc_by_sa" value="<?php echo esc_url( $url ); ?>" <?php checked( $option, $url, true ); ?>>
		<label for="imalisch_settings_cc_by_sa">
			<?php esc_html_e( 'Creative Commons BY-SA', 'image-licensing-schema' ); ?>
		</label>
		<button class="imalisch-button-link button-link"><small><?php esc_html_e( 'show details', 'image-licensing-schema' ); ?></small></button>
		<div class="imalisch_toggle_license_details">
			<span class="description"><?php esc_attr_e( 'BY: Credit must be given to the creator.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><?php esc_attr_e( 'SA: Adaptations must be shared under the same terms.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><a href="<?php echo $url; ?>" target="_blank"><?php esc_html_e( 'More information', 'image-licensing-schema' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens a new tab)', 'image-licensing-schema' ); ?></span></a></span>
		</div>
	</p>

	<p class="imalisch_license_type">
		<?php
		/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
		$url = __( 'https://creativecommons.org/licenses/by-nc/4.0/', 'image-licensing-schema' );
		?>
		<input type="radio" name="imalisch_settings_license" id="imalisch_settings_cc_by_nc" value="<?php echo esc_url( $url ); ?>" <?php checked( $option, $url, true ); ?>>
		<label for="imalisch_settings_cc_by_nc">
			<?php esc_html_e( 'Creative Commons BY-NC', 'image-licensing-schema' ); ?>
		</label>
		<button class="imalisch-button-link button-link"><small><?php esc_html_e( 'show details', 'image-licensing-schema' ); ?></small></button>
		<div class="imalisch_toggle_license_details">
			<span class="description"><?php esc_attr_e( 'BY: Credit must be given to the creator.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><?php esc_attr_e( 'NC: Only noncommercial uses of the work are permitted.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><a href="<?php echo $url; ?>" target="_blank"><?php esc_html_e( 'More information', 'image-licensing-schema' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens a new tab)', 'image-licensing-schema' ); ?></span></a></span>
		</div>
	</p>

	<p class="imalisch_license_type">
		<?php
		/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
		$url = __( 'https://creativecommons.org/licenses/by-nc-sa/4.0/', 'image-licensing-schema' );
		?>
		<input type="radio" name="imalisch_settings_license" id="imalisch_settings_cc_by_nc_sa" value="<?php echo esc_url( $url ); ?>" <?php checked( $option, $url, true ); ?>>
		<label for="imalisch_settings_cc_by_nc_sa">
			<?php esc_html_e( 'Creative Commons BY-NC-SA', 'image-licensing-schema' ); ?>
		</label>
		<button class="imalisch-button-link button-link"><small><?php esc_html_e( 'show details', 'image-licensing-schema' ); ?></small></button>
		<div class="imalisch_toggle_license_details">
			<span class="description"><?php esc_attr_e( 'BY: Credit must be given to the creator.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><?php esc_attr_e( 'NC: Only noncommercial uses of the work are permitted.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><?php esc_attr_e( 'SA: Adaptations must be shared under the same terms.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><a href="<?php echo $url; ?>" target="_blank"><?php esc_html_e( 'More information', 'image-licensing-schema' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens a new tab)', 'image-licensing-schema' ); ?></span></a></span>
		</div>
	</p>

	<p class="imalisch_license_type">
		<?php
		/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
		$url = __( 'https://creativecommons.org/licenses/by-nd/4.0/', 'image-licensing-schema' );
		?>
		<input type="radio" name="imalisch_settings_license" id="imalisch_settings_cc_by_nd" value="<?php echo esc_url( $url ); ?>" <?php checked( $option, $url, true ); ?>>
		<label for="imalisch_settings_cc_by_nd">
			<?php esc_html_e( 'Creative Commons BY-ND', 'image-licensing-schema' ); ?>
		</label>
		<button class="imalisch-button-link button-link"><small><?php esc_html_e( 'show details', 'image-licensing-schema' ); ?></small></button>
		<div class="imalisch_toggle_license_details">
			<span class="description"><?php esc_attr_e( 'BY: Credit must be given to the creator.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><?php esc_attr_e( 'ND: No derivatives or adaptations of the work are permitted.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><a href="<?php echo $url; ?>" target="_blank"><?php esc_html_e( 'More information', 'image-licensing-schema' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens a new tab)', 'image-licensing-schema' ); ?></span></a></span>
		</div>
	</p>

	<p class="imalisch_license_type">
		<?php
		/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
		$url = __( 'https://creativecommons.org/licenses/by-nc-nd/4.0/', 'image-licensing-schema' );
		?>
		<input type="radio" name="imalisch_settings_license" id="imalisch_settings_cc_by_nc_nd" value="<?php echo esc_url( $url ); ?>	" <?php checked( $option, $url, true ); ?>>
		<label for="imalisch_settings_cc_by_nc_nd">
			<?php esc_html_e( 'Creative Commons BY-NC-ND', 'image-licensing-schema' ); ?>
		</label>
		<button class="imalisch-button-link button-link"><small><?php esc_html_e( 'show details', 'image-licensing-schema' ); ?></small></button>
		<div class="imalisch_toggle_license_details">
			<span class="description"><?php esc_attr_e( 'BY: Credit must be given to the creator.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><?php esc_attr_e( 'NC: Only noncommercial uses of the work are permitted.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><?php esc_attr_e( 'ND: No derivatives or adaptations of the work are permitted.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><a href="<?php echo $url; ?>" target="_blank"><?php esc_html_e( 'More information', 'image-licensing-schema' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens a new tab)', 'image-licensing-schema' ); ?></span></a></span>
		</div>
	</p>

	<p class="imalisch_license_type">
		<input type="radio" name="imalisch_settings_license" id="imalisch_settings_custom" value="custom" <?php checked( $option, 'custom', true ); ?>>
		<label for="imalisch_settings_custom">
			<?php esc_html_e( 'Custom License', 'image-licensing-schema' ); ?>
		</label>
		<button class="imalisch-button-link button-link"><small><?php esc_html_e( 'show details', 'image-licensing-schema' ); ?></small></button>
		<div class="imalisch_toggle_license_details">
			<span class="description"><?php esc_attr_e( 'Use this option if your work is not available under Creative Commons licenses. In that case, you need to select a License Policy page below.', 'image-licensing-schema' ); ?></span>
			<br /><span class="description"><a href="<?php echo $url; ?>" target="_blank"><?php esc_html_e( 'More information', 'image-licensing-schema' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens a new tab)', 'image-licensing-schema' ); ?></span></a></span>
		</div>
	</p>
	<?php
}

function imalisch_settings_select_page_callback() {
	$options = get_option( 'imalisch_settings_page' );
	if ( isset( $options ) && ! empty( $options ) ) {
		$option = esc_html( $options );
	} else {
		$option = '';			   
	}
	?>
	<p>
		<label for="imalisch_settings_page">
			<?php esc_html_e( 'Choose an existing page of your site', 'image-licensing-schema' ); ?>
			<a href="<?php echo admin_url( '/post-new.php?post_type=page' ); ?>" target="_blank" class="imalisch_external_link"><?php esc_html_e( 'or create a new one', 'image-licensing-schema' ); ?></a>
		</label>
	</p>
	<p>
		<?php
		wp_dropdown_pages(
			array(
				'name' => 'imalisch_settings_page',
				'echo' => 1,
				'show_option_none' => esc_attr__( '&mdash; Select &mdash;', 'image-licensing-schema' ),
				'option_none_value' => '',
				'selected' => $option
			)
		);
		?>
	</p>
	<p>
		<span class="description">
			<?php esc_html_e( 'It allows Google Images and other services to add a direct link to your Image Licensing Policy.', 'image-licensing-schema' ); ?>
			<br />
			<?php esc_html_e( 'It’s necessary if you have selected a custom licence.', 'image-licensing-schema' ); ?>
			<br />
			<?php esc_html_e( 'It can be a section in your Legal Notice page.', 'image-licensing-schema' ); ?>
			<br />
			<?php esc_html_e( 'And even if you choose an existing license type, it’s recommended to set up a Licensing Policy Page for your website.', 'image-licensing-schema' ); ?>
		</span>
	</p>
	<?php
}

function imalisch_register_meta_boxes() {
	$post_types = get_post_types( array( 'public' => true ) );
	if ( $post_types ) {
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'imalisch-meta-box',
				__( 'Manage images licensing', 'image-licensing-schema' ),
				'imalisch_meta_box_callback',
				'post',
				'side'
			);
		}
	}
}
add_action( 'add_meta_boxes', 'imalisch_register_meta_boxes' );

function imalisch_meta_box_callback( $post ) {
	$existing_urls = (array) get_post_meta( $post->ID, '_imalisch_post_license', true );
	$options = get_option( 'imalisch_settings_license' );
	if ( isset( $options ) && ! empty( $options ) ) {
		$option = esc_html( $options );
	} else {
		$option = '';			   
	}

	libxml_use_internal_errors( true );
	$html = new DOMDocument();
	$html->loadHTML( mb_convert_encoding( $post->post_content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	$html->encoding = 'UTF-8';
	$imgs = $html->getElementsByTagName( 'img' );
	$anti_dupes = array();
	$i = 1;
	?>
	<p><?php esc_html_e( 'You can change default license for all your website’s images using Media Settings. "Custom" license type/url is also managed in Media Settings.', 'image-licensing-schema' ); ?></p>
	<p><?php esc_html_e( 'Here is the list of the images currently used in this post (save and refresh if you added more images). You can change their settings.', 'image-licensing-schema' ); ?></p>
	<?php if ( $imgs ) : ?>
		<div class="imalisch_metabox_list_wrapper">
		<?php foreach ( $imgs as $img ) : ?>
			<?php
			$src = $img->getAttribute( 'src' );
			if ( in_array( $src, $anti_dupes ) ) : continue; endif;
			$anti_dupes[$i] = $src;
			$key = array_search( $src, array_column( $existing_urls, 'image_url' ) );
			if ( ! empty( $existing_urls[$src] ) ) {
				$current_license = $existing_urls[$src];
			} else {
				$current_license = $option;
			}
			?>
			<div class="imalisch_metabox_list_item" style="margin: 5px 0; border-bottom: 1px solid #ccc; min-height: 150px; overflow: hidden;">
				<img class="imalisch_metabox_list_item_image" src="<?php echo esc_url( $src ); ?>" alt="" style="float: right; padding-left: 1em; max-width: 90px; max-height: 120px; width: auto; height: auto;" />
				<?php
				/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
				$url = __( 'https://creativecommons.org/publicdomain/zero/1.0/', 'image-licensing-schema' );
				?>
				<input type="radio" name="imalisch_post_license[<?php echo $src; ?>]" id="imalisch_settings_cc0_<?php echo $i; ?>" value="<?php echo esc_url( $url ); ?>" <?php checked( $current_license, $url, true ); ?>>
				<label for="imalisch_settings_cc0_<?php echo $i; ?>"><?php esc_html_e( 'CCO Public Domain', 'image-licensing-schema' ); ?></label>
				<br />
				<?php
				/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
				$url = __( 'https://creativecommons.org/licenses/by/4.0/', 'image-licensing-schema' );
				?>
				<input type="radio" name="imalisch_post_license[<?php echo $src; ?>]" id="imalisch_settings_cc_by_<?php echo $i; ?>" value="<?php echo esc_url( $url ); ?>" <?php checked( $current_license, $url, true ); ?>>
				<label for="imalisch_settings_cc_by_<?php echo $i; ?>"><?php esc_html_e( 'CC BY', 'image-licensing-schema' ); ?></label>
				<br />
				<?php
				/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
				$url = __( 'https://creativecommons.org/licenses/by-sa/4.0/', 'image-licensing-schema' );
				?>
				<input type="radio" name="imalisch_post_license[<?php echo $src; ?>]" id="imalisch_settings_cc_by_sa_<?php echo $i; ?>" value="<?php echo esc_url( $url ); ?>" <?php checked( $current_license, $url, true ); ?>>
				<label for="imalisch_settings_cc_by_sa_<?php echo $i; ?>"><?php esc_html_e( 'CC BY-SA', 'image-licensing-schema' ); ?></label>
				<br />
				<?php
				/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
				$url = __( 'https://creativecommons.org/licenses/by-nc/4.0/', 'image-licensing-schema' );
				?>
				<input type="radio" name="imalisch_post_license[<?php echo $src; ?>]" id="imalisch_settings_cc_by_nc_<?php echo $i; ?>" value="<?php echo esc_url( $url ); ?>" <?php checked( $current_license, $url, true ); ?>>
				<label for="imalisch_settings_cc_by_nc_<?php echo $i; ?>"><?php esc_html_e( 'CC BY-NC', 'image-licensing-schema' ); ?></label>
				<br />
				<?php
				/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
				$url = __( 'https://creativecommons.org/licenses/by-nc-sa/4.0/', 'image-licensing-schema' );
				?>
				<input type="radio" name="imalisch_post_license[<?php echo $src; ?>]" id="imalisch_settings_cc_by_nc_sa_<?php echo $i; ?>" value="<?php echo esc_url( $url ); ?>" <?php checked( $current_license, $url, true ); ?>>
				<label for="imalisch_settings_cc_by_nc_sa_<?php echo $i; ?>"><?php esc_html_e( 'CC BY-NC-SA', 'image-licensing-schema' ); ?></label>
				<br />
				<?php
				/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
				$url = __( 'https://creativecommons.org/licenses/by-nd/4.0/', 'image-licensing-schema' );
				?>
				<input type="radio" name="imalisch_post_license[<?php echo $src; ?>]" id="imalisch_settings_cc_by_nd_<?php echo $i; ?>" value="<?php echo esc_url( $url ); ?>" <?php checked( $current_license, $url, true ); ?>>
				<label for="imalisch_settings_cc_by_nd_<?php echo $i; ?>"><?php esc_html_e( 'CC BY-ND', 'image-licensing-schema' ); ?></label>
				<br />
				<?php
				/* Translators: This URL may be available in your language, follow the link and see the language switcher on the left of Creative Commons page. Use the localized version of this page if your language is available. */
				$url = __( 'https://creativecommons.org/licenses/by-nc-nd/4.0/', 'image-licensing-schema' );
				?>
				<input type="radio" name="imalisch_post_license[<?php echo $src; ?>]" id="imalisch_settings_cc_by_nc_nd_<?php echo $i; ?>" value="<?php echo esc_url( $url ); ?>" <?php checked( $current_license, $url, true ); ?>>
				<label for="imalisch_settings_cc_by_nc_nd_<?php echo $i; ?>"><?php esc_html_e( 'CC BY-NC-ND', 'image-licensing-schema' ); ?></label>
				<br />
				<input type="radio" name="imalisch_post_license[<?php echo $src; ?>]" id="imalisch_settings_custom_<?php echo $i; ?>" value="custom" <?php checked( $current_license, 'custom', true ); ?>>
				<label for="imalisch_settings_custom_<?php echo $i; ?>"><?php esc_html_e( 'Custom License', 'image-licensing-schema' ); ?></label>
				<br />
			</div>
		<?php $i++; endforeach; ?>
		</div>
	<?php else : ?>
	<?php endif; ?>
	<?php
}

function imalisch_meta_box_save( $post_id ) {
	if ( isset( $_POST['imalisch_post_license'] ) && ! empty( $_POST['imalisch_post_license'] ) ) {
		$new_values = array();
		$data = ( array ) $_POST['imalisch_post_license'];
		foreach ( $data as $key => $value ) {
			$new_values[esc_url( $key )] = esc_html( $value );
		}
		update_post_meta( $post_id, '_imalisch_post_license', $new_values );
	}
}
add_action('save_post', 'imalisch_meta_box_save');