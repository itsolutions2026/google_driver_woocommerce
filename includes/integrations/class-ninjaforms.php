<?php

namespace IGD;

use NF_Abstracts_Field;

defined( 'ABSPATH' ) || exit;

// Register Field
add_filter( 'ninja_forms_register_fields', function ( $fields ) {
	$fields['integrate_google_drive'] = new NinjaForms();

	return $fields;
} );

// Load Field Templates
add_filter( 'ninja_forms_field_template_file_paths', function ( $file_paths ) {
	$file_paths[] = IGD_INCLUDES . '/integrations/templates/';

	return $file_paths;
} );

// Enqueue Scripts
add_action( 'ninja_forms_enqueue_scripts', function ( $data ) {
	$form_id = $data['form_id'];

	$fields = Ninja_Forms()->form( $form_id )->get_fields();

	foreach ( $fields as $field ) {
		if ( $field->get_setting( 'type' ) == 'integrate_google_drive' ) {
			wp_enqueue_style( 'igd-frontend' );
			wp_enqueue_script( 'igd-frontend' );
			break;
		}
	}

} );

// Create entry folder if needed
add_action( 'ninja_forms_after_submission', function ( $data ) {

	$igd_fields = [];

	foreach ( $data['fields'] as $field ) {
		if ( $field['settings']['type'] == 'integrate_google_drive' ) {
			$igd_fields[] = $field;
		}
	}

	if ( ! empty( $igd_fields ) ) {
		foreach ( $igd_fields as $field ) {
			$value = $field['settings']['value'];


			if ( empty( $value ) ) {
				continue;
			}

			$files = [];

			// Fetch file ids from the value text
			preg_match_all( '/file\/d\/(.*?)\/view/', $value, $matches );

			$file_ids = $matches[1];

			if ( empty( $file_ids ) ) {
				continue;
			}

			foreach ( $file_ids as $file_id ) {
				$files[] = App::instance()->get_file_by_id( $file_id );
			}

			if ( empty( $files ) ) {
				continue;
			}

			$igd_data = json_decode( $field['settings']['igd_data'], true );

			$create_entry_folder   = ! empty( $igd_data['createEntryFolders'] );
			$create_private_folder = ! empty( $igd_data['createPrivateFolder'] );

			if ( ! $create_entry_folder && ! $create_private_folder ) {
				continue;
			}

			$entry_folder_name_template = ! empty( $igd_data['entryFolderNameTemplate'] ) ? $igd_data['entryFolderNameTemplate'] : 'Entry (%entry_id%) - %form_title%';

			$tag_data = [
				'name' => $entry_folder_name_template,
				'form' => [
					'form_title' => $data['settings']['title'],
					'form_id'    => $data['form_id'],
				]
			];

			if ( false !== strpos( $entry_folder_name_template, '%entry_id%' ) ) {
				$entry_post_id = $data['actions']['save']['sub_id'];

				$entry_id                     = get_post_meta( $entry_post_id, '_seq_num', true );
				$tag_data['form']['entry_id'] = $entry_id;
			}

			if ( igd_contains_tags( 'user', $entry_folder_name_template ) ) {
				if ( is_user_logged_in() ) {
					$tag_data['user'] = get_userdata( get_current_user_id() );
				}
			}

			if ( igd_contains_tags( 'post', $entry_folder_name_template ) ) {
				$referrer = wp_get_referer();

				if ( ! empty( $referrer ) ) {
					$post_id = url_to_postid( $referrer );
					if ( ! empty( $post_id ) ) {
						$tag_data['post'] = get_post( $post_id );
						if ( $tag_data['post']->post_type == 'product' ) {
							$tag_data['wc_product'] = wc_get_product( $post_id );
						}
					}
				}
			}

			$extra_tags = [];

			foreach ( $data['fields'] as $tagField ) {
				$key         = $tagField['settings']['key'];
				$field_value = $tagField['settings']['value'];

				if ( is_array( $field_value ) ) {
					$field_value = implode( ',', $field_value );
				}

				$extra_tags[ '%field_' . $key . '%' ] = $field_value;
			}

			$folder_name = igd_replace_template_tags( $tag_data, $extra_tags );

			// Check Private Folders
			$private_folders = ! empty( $igd_data['privateFolders'] );
			if ( $private_folders && is_user_logged_in() ) {
				$folders = get_user_option( 'folders', get_current_user_id() );

				if ( ! empty( $folders ) ) {
					$folders = array_values( array_filter( (array) $folders, function ( $item ) {
						return igd_is_dir( $item );
					} ) );
				} elseif ( $create_private_folder ) {
					$folders = Private_Folders::instance()->create_user_folder( get_current_user_id(), $igd_data );
				}

				if ( ! empty( $folders ) ) {
					$igd_data['folders'] = $folders;
				}

			}

			$upload_folder = ! empty( reset( $igd_data['folders'] ) ) ? reset( $igd_data['folders'] ) : [
				'id'        => 'root',
				'accountId' => '',
			];

			$merge_folders = isset( $igd_data['mergeFolders'] ) ? filter_var( $igd_data['mergeFolders'], FILTER_VALIDATE_BOOLEAN ) : false;

			Uploader::instance( $upload_folder['accountId'] )->create_entry_folder_and_move( $files, $folder_name, $upload_folder, $merge_folders, $create_entry_folder );

		}
	}

} );


class NinjaForms extends NF_Abstracts_Field {
	protected $_name = 'integrate_google_drive';
	protected $_type = 'integrate_google_drive';
	protected $_nicename = 'Google Drive';
	protected $_parent_type = 'textbox';
	protected $_section = 'common';
	protected $_templates = 'integrate_google_drive';
	protected $_icon = 'cloud-upload';
	protected $_test_value = false;
	protected $_settings_all_fields = array(
		'key',
		'label',
		'label_pos',
		'required',
		'classes',
		'manual_key',
		'help',
		'description',
	);

	public function __construct() {

		parent::__construct();

		$settings = [
			'igd_data' => array(
				'name'  => 'igd_data',
				'type'  => 'textarea',
				'value' => '',
				'label' => __( 'Configure Uploader', 'integrate-google-drive' ),
				'group' => 'primary',
				'width' => 'full',
				'help'  => __( 'Configure the file uploader with module builder.', 'integrate-google-drive' ),
			),

			'igd_configure' => array(
				'name'  => 'igd_configure',
				'type'  => 'html',
				'value' => sprintf( '<div class="igd-form-uploader-config">
                    <button type="button" class="igd-form-uploader-trigger igd-form-uploader-trigger-ninjaforms igd-btn btn-primary">
                        <i class="dashicons dashicons-admin-generic"></i>
                        <span>%s</span>
                    </button>
                </div>', __( 'Configure Uploader', 'integrate-google-drive' ) ),
				'group' => 'primary',
				'width' => 'full',
			),
		];

		$this->_settings = array_merge( $this->_settings, $settings );

		add_action( 'nf_admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		if ( ! empty( $_GET['page'] ) && 'ninja-forms' == $_GET['page'] && ! empty( $_GET['form_id'] ) ) {
			include IGD_INCLUDES . '/integrations/templates/fields-integrate_google_drive.html';
		}

	}

	public function admin_enqueue_scripts() {
		Enqueue::instance()->admin_scripts( '', false );
	}

	public function validate( $field, $data ) {
		$errors = parent::validate( $field, $data );
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		// Min Files check
		$igd_data  = ! empty( $field['settings']['igd_data'] ) ? json_decode( $field['settings']['igd_data'], true ) : [];
		$min_files = ! empty( $igd_data['minFiles'] ) ? $igd_data['minFiles'] : 0;

		$files = explode( ' ),', $field['value'] );

		if ( ! empty( $min_files ) && ( empty( $files ) || count( $files ) < $min_files ) ) {
			$errors['slug'] = 'min-files-error';

			// translators: %s: minimum files
			$errors['message'] = sprintf( __( 'Please upload at least %s files.', 'integrate-google-drive' ), $min_files );
		}

		return $errors;


	}

}