<?php
/**
 * @package PublishPress
 * @author PressShack
 *
 * Copyright (c) 2017 PressShack
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! class_exists( 'PP_Module' ) ) {
	/**
	 * PP_Module
	 */
	class PP_Module {

		protected $twig;

		protected $debug = false;

		public $published_statuses = array(
								'publish',
								// 'future',
								'private',
							);

		protected $twigPath;

		public function __construct() {
			if ( ! empty( $this->twigPath ) ) {
				$loader = new Twig_Loader_Filesystem( $this->twigPath );
				$this->twig = new Twig_Environment( $loader, array(
					'debug' => $this->debug,
				) );

				if ( $this->debug ) {
					$this->twig->addExtension( new Twig_Extension_Debug() );
				}
			}
		}

		/**
		 * Returns whether the module with the given name is enabled.
		 *
		 * @since 0.7
		 *
		 * @param string module Slug of the module to check
		 * @return <code>true</code> if the module is enabled, <code>false</code> otherwise
		 */
		public function module_enabled( $slug ) {
			global $publishpress;

			return isset( $publishpress->$slug ) && $publishpress->$slug->module->options->enabled == 'on';
		}

		/**
		 * Gets an array of allowed post types for a module
		 *
		 * @return array post-type-slug => post-type-label
		 */
		public function get_all_post_types() {
			$allowed_post_types = array(
			'post' => __( 'Post' ),
			'page' => __( 'Page' ),
			);
			$custom_post_types = $this->get_supported_post_types_for_module();

			foreach ( $custom_post_types as $custom_post_type => $args ) {
				$allowed_post_types[ $custom_post_type ] = $args->label;
			}

			return $allowed_post_types;
		}

		/**
		 * Cleans up the 'on' and 'off' for post types on a given module (so we don't get warnings all over)
		 * For every post type that doesn't explicitly have the 'on' value, turn it 'off'
		 * If add_post_type_support() has been used anywhere (legacy support), inherit the state
		 *
		 * @param array  $module_post_types Current state of post type options for the module
		 * @param string $post_type_support What the feature is called for post_type_support (e.g. 'pp_calendar')
		 * @return array $normalized_post_type_options The setting for each post type, normalized based on rules
		 *
		 * @since 0.7
		 */
		public function clean_post_type_options( $module_post_types = array(), $post_type_support = null ) {
			$normalized_post_type_options = array();
			$all_post_types               = array_keys( $this->get_all_post_types() );
			foreach ( $all_post_types as $post_type ) {
				if ( (isset( $module_post_types[ $post_type ] ) && $module_post_types[ $post_type ] == 'on') || post_type_supports( $post_type, $post_type_support ) ) {
					$normalized_post_type_options[ $post_type ] = 'on';
				} else {
					$normalized_post_type_options[ $post_type ] = 'off';
				}
			}

			return $normalized_post_type_options;
		}

		/**
		 * Get all of the possible post types that can be used with a given module
		 *
		 * @param object $module The full module
		 * @return array $post_types An array of post type objects
		 *
		 * @since 0.7.2
		 */
		public function get_supported_post_types_for_module( $module = null ) {
			$pt_args = array(
				'_builtin' => false,
				'public' => true,
			);
			$pt_args = apply_filters( 'publishpress_supported_module_post_types_args', $pt_args, $module );

			return get_post_types( $pt_args, 'objects' );
		}

		/**
		 * Collect all of the active post types for a given module
		 *
		 * @param object $module Module's data
		 * @return array $post_types All of the post types that are 'on'
		 *
		 * @since 0.7
		 */
		public function get_post_types_for_module( $module ) {
			return PublishPress\Util::get_post_types_for_module( $module );
		}

		/**
		 * Get all of the currently available post statuses
		 * This should be used in favor of calling $publishpress->custom_status->get_custom_statuses() directly
		 *
		 * @return array $post_statuses All of the post statuses that aren't a published state
		 *
		 * @since 0.7
		 */
		public function get_post_statuses() {
			global $publishpress;

			if ( $this->module_enabled( 'custom_status' ) ) {
				return $publishpress->custom_status->get_custom_statuses();
			} else {
				return $this->get_core_post_statuses();
			}
		}

		/**
		 * Get core's 'draft' and 'pending' post statuses, but include our special attributes
		 *
		 * @since 0.8.1
		 *
		 * @return array
		 */
		protected function get_core_post_statuses() {
			return array(
			(object) array(
				'name'         => __( 'Draft' ),
				'description'  => '',
				'slug'         => 'draft',
				'position'     => 1,
			),
			(object) array(
				'name'         => __( 'Pending Review' ),
				'description'  => '',
				'slug'         => 'pending',
				'position'     => 2,
			),
			);
		}

		/**
		 * Gets the name of the default custom status. If custom statuses are disabled,
		 * returns 'draft'.
		 *
		 * @return str Name of the status
		 */
		public function get_default_post_status() {

			// Check if custom status module is enabled
			$custom_status_module = PublishPress()->custom_status->module->options;

			if ( $custom_status_module->enabled == 'on' ) {
				return $custom_status_module->default_status;
			} else {
				return 'draft';
			}
		}

		/**
		 * Filter to all posts with a given post status (can be a custom status or a built-in status) and optional custom post type.
		 *
		 * @since 0.7
		 *
		 * @param string $slug The slug for the post status to which to filter
		 * @param string $post_type Optional post type to which to filter
		 * @return an edit.php link to all posts with the given post status and, optionally, the given post type
		 */
		public function filter_posts_link( $slug, $post_type = 'post' ) {
			$filter_link = add_query_arg( 'post_status', $slug, get_admin_url( null, 'edit.php' ) );
			if ( $post_type != 'post' && in_array( $post_type, get_post_types( '', 'names' ) ) ) {
				$filter_link = add_query_arg( 'post_type', $post_type, $filter_link );
			}

			return $filter_link;
		}

		/**
		 * Returns the friendly name for a given status
		 *
		 * @since 0.7
		 *
		 * @param string $status The status slug
		 * @return string $status_friendly_name The friendly name for the status
		 */
		public function get_post_status_friendly_name( $status ) {
			global $publishpress;

			$status_friendly_name = '';

			$builtin_stati = array(
			'publish' => __( 'Published', 'publishpress' ),
			'draft'   => __( 'Draft', 'publishpress' ),
			'future'  => __( 'Scheduled', 'publishpress' ),
			'private' => __( 'Private', 'publishpress' ),
			'pending' => __( 'Pending Review', 'publishpress' ),
			'trash'   => __( 'Trash', 'publishpress' ),
			);

			// Custom statuses only handles workflow statuses
			if ( $this->module_enabled( 'custom_status' )
			&& ! in_array( $status, array( 'publish', 'future', 'private', 'trash' ) ) ) {
				$status_object = $publishpress->custom_status->get_custom_status_by( 'slug', $status );
				if ( $status_object && ! is_wp_error( $status_object ) ) {
					$status_friendly_name = $status_object->name;
				}
			} elseif ( array_key_exists( $status, $builtin_stati ) ) {
				$status_friendly_name = $builtin_stati[ $status ];
			}

				return $status_friendly_name;
		}

		/**
		 * Enqueue any resources (CSS or JS) associated with datepicker functionality
		 *
		 * @since 0.7
		 */
		public function enqueue_datepicker_resources() {
			// Add the first day of the week as an available variable to wp_head
			echo '<script type="text/javascript">var pp_week_first_day="' . get_option( 'start_of_week' ) . '";</script>';

			// Datepicker is available WordPress 3.3. We have to register it ourselves for previous versions of WordPress
			wp_enqueue_script( 'jquery-ui-datepicker' );

			// Timepicker needs to come after jquery-ui-datepicker and jquery
			wp_enqueue_script( 'publishpress-timepicker', PUBLISHPRESS_URL . 'common/js/jquery-ui-timepicker-addon.js', array( 'jquery', 'jquery-ui-datepicker' ), PUBLISHPRESS_VERSION, true );
			wp_enqueue_script( 'publishpress-date_picker', PUBLISHPRESS_URL . 'common/js/pp_date.js', array( 'jquery', 'jquery-ui-datepicker', 'publishpress-timepicker' ), PUBLISHPRESS_VERSION, true );

			// Now styles
			wp_enqueue_style( 'jquery-ui-datepicker', PUBLISHPRESS_URL . 'common/css/jquery.ui.datepicker.css', array( 'wp-jquery-ui-dialog' ), PUBLISHPRESS_VERSION, 'screen' );
			wp_enqueue_style( 'jquery-ui-theme', PUBLISHPRESS_URL . 'common/css/jquery.ui.theme.css', false, PUBLISHPRESS_VERSION, 'screen' );

			wp_localize_script(
                'publishpress-date_picker',
                'objectL10ndate',
                array(
                    'date_format' => __('M dd yy', 'publishpress'),
                )
            );
		}

		/**
		 * Checks for the current post type
		 *
		 * @since 0.7
		 * @return string|null $post_type The post type we've found, or null if no post type
		 */
		public function get_current_post_type() {
			return PublishPress\Util::get_current_post_type();
		}

		/**
		 * Wrapper for the get_user_meta() function so we can replace it if we need to
		 *
		 * @since 0.7
		 *
		 * @param int    $user_id Unique ID for the user
		 * @param string $key Key to search against
		 * @param bool   $single Whether or not to return just one value
		 * @return string|bool|array $value Whatever the stored value was
		 */
		public function get_user_meta( $user_id, $key, $string = true ) {
			$response = null;
			$response = apply_filters( 'pp_get_user_meta', $response, $user_id, $key, $string );
			if ( ! is_null( $response ) ) {
				return $response;
			}

			return get_user_meta( $user_id, $key, $string );
		}

		/**
		 * Wrapper for the update_user_meta() function so we can replace it if we need to
		 *
		 * @since 0.7
		 *
		 * @param int               $user_id Unique ID for the user
		 * @param string            $key Key to search against
		 * @param string|bool|array $value Whether or not to return just one value
		 * @param string|bool|array $previous (optional) Previous value to replace
		 * @return bool $success Whether we were successful in saving
		 */
		public function update_user_meta( $user_id, $key, $value, $previous = null ) {
			$response = null;
			$response = apply_filters( 'pp_update_user_meta', $response, $user_id, $key, $value, $previous );
			if ( ! is_null( $response ) ) {
				return $response;
			}

			return update_user_meta( $user_id, $key, $value, $previous );
		}

		/**
		 * Take a status and a message, JSON encode and print
		 *
		 * @since 0.7
		 *
		 * @param string $status Whether it was a 'success' or an 'error'
		 */
		public function print_ajax_response( $status, $message = '' ) {
			header( 'Content-type: application/json;' );
			echo json_encode( array(
				'status' => $status,
				'message' => $message,
			) );

			exit;
		}

		/**
		 * Whether or not the current page is a user-facing PublishPress View
		 *
		 * @todo Think of a creative way to make this work
		 *
		 * @since 0.7
		 *
		 * @param string $module_name (Optional) Module name to check against
		 */
		public function is_whitelisted_functional_view( $module_name = null ) {
			// @todo complete this method
			return true;
		}

		/**
		 * Whether or not the current page is an PublishPress settings view (either main or module)
		 * Determination is based on $pagenow, $_GET['page'], and the module's $settings_slug
		 * If there's no module name specified, it will return true against all PublishPress settings views
		 *
		 * @since 0.7
		 *
		 * @param string $module_name (Optional) Module name to check against
		 * @return bool $is_settings_view Return true if it is
		 */
		public function is_whitelisted_settings_view( $module_name = null ) {
			global $pagenow, $publishpress;

			// All of the settings views are based on admin.php and a $_GET['page'] parameter
			if ( $pagenow != 'admin.php' || ! isset( $_GET['page'] ) ) {
				return false;
			}

			if ( isset( $_GET['page'] ) && $_GET['page'] === 'pp-modules-settings' ) {
				if ( empty( $module_name ) ) {
					return true;
				}

				if ( ! isset( $_GET['module'] ) || $_GET['module'] === 'pp-modules-settings-settings' ) {
					if ( in_array( $module_name, array( 'editorial_comments', 'notifications', 'dashboard' ) ) ) {
						return true;
					}
				}

				$slug = str_replace( '_', '-', $module_name );
				if ( isset( $_GET['module'] ) && $_GET['module'] === 'pp-' . $slug . '-settings' ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Remove term(s) associated with a given object(s). Core doesn't have this as of 3.2
		 *
		 * @see http://core.trac.wordpress.org/ticket/15475
		 *
		 * @author ericmann
		 * @compat 3.3?
		 *
		 * @param int|array    $object_ids The ID(s) of the object(s) to retrieve.
		 * @param int|array    $terms The ids of the terms to remove.
		 * @param string|array $taxonomies The taxonomies to retrieve terms from.
		 * @return bool|WP_Error Affected Term IDs
		 */
		public function remove_object_terms( $object_id, $terms, $taxonomy ) {
			global $wpdb;

			if ( ! taxonomy_exists( $taxonomy ) ) {
				return new WP_Error( 'invalid_taxonomy', __( 'Invalid Taxonomy' ) );
			}

			if ( ! is_array( $object_id ) ) {
				$object_id = array( $object_id );
			}

			if ( ! is_array( $terms ) ) {
				$terms = array( $terms );
			}

			$delete_objects = array_map( 'intval', $object_id );
			$delete_terms   = array_map( 'intval', $terms );

			if ( $delete_terms ) {
				$in_delete_terms   = "'" . implode( "', '", $delete_terms ) . "'";
				$in_delete_objects = "'" . implode( "', '", $delete_objects ) . "'";
				$return            = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id IN ($in_delete_objects) AND term_taxonomy_id IN ($in_delete_terms)", $object_id ) );
				wp_update_term_count( $delete_terms, $taxonomy );
				return true;
			}

			return false;
		}

		/**
		 * Encode all of the given arguments as a serialized array, and then base64_encode
		 * Used to store extra data in a term's description field.
		 *
		 * @since 0.7
		 *
		 * @param array $args The arguments to encode
		 * @return string Arguments encoded in base64
		 */
		public function get_encoded_description( $args = array() ) {
			return base64_encode( maybe_serialize( $args ) );
		}

		/**
		 * If given an encoded string from a term's description field,
		 * return an array of values. Otherwise, return the original string
		 *
		 * @since 0.7
		 *
		 * @param string $string_to_unencode Possibly encoded string
		 * @return array Array if string was encoded, otherwise the string as the 'description' field
		 */
		public function get_unencoded_description( $string_to_unencode ) {
			return maybe_unserialize( base64_decode( $string_to_unencode ) );
		}

		/**
		 * Get the publicly accessible URL for the module based on the filename
		 *
		 * @since 0.7
		 *
		 * @param string $filepath File path for the module
		 * @return string $module_url Publicly accessible URL for the module
		 */
		public function get_module_url( $file ) {
			$module_url = plugins_url( '/', $file );
			return trailingslashit( $module_url );
		}

		/**
		 * Produce a human-readable version of the time since a timestamp
		 *
		 * @param int $original The UNIX timestamp we're producing a relative time for
		 * @return string $relative_time Human-readable version of the difference between the timestamp and now
		 */
		public function timesince( $original ) {
			// array of time period chunks
			$chunks = array(
			array( 60 * 60 * 24 * 365 , 'year' ),
			array( 60 * 60 * 24 * 30 , 'month' ),
			array( 60 * 60 * 24 * 7, 'week' ),
			array( 60 * 60 * 24 , 'day' ),
			array( 60 * 60 , 'hour' ),
			array( 60 , 'minute' ),
			array( 1 , 'second' ),
			);

			$today = time(); /* Current unix time  */
			$since = $today - $original;

			if ( $since > $chunks[2][0] ) {
				$print = date( 'M jS', $original );

				if ( $since > $chunks[0][0] ) { // Seconds in a year
					$print .= ', ' . date( 'Y', $original );
				}

				return $print;
			}

			// $j saves performing the count function each time around the loop
			for ( $i = 0, $j = count( $chunks ); $i < $j; $i++ ) {
				$seconds = $chunks[ $i ][0];
				$name    = $chunks[ $i ][1];

				// finding the biggest chunk (if the chunk fits, break)
				if ( ($count = floor( $since / $seconds )) != 0 ) {
					break;
				}
			}

			return sprintf( _n( "1 $name ago", "$count ${name}s ago", $count ), $count );
		}

		/**
		 * Displays a list of users that can be selected!
		 *
		 * @since 0.7
		 *
		 * @todo Add pagination support for blogs with billions of users
		 *
		 * @param ???
		 * @param ???
		 */
		public function users_select_form( $selected = null, $args = null ) {

			// Set up arguments
			$defaults = array(
			'list_class' => 'pp-users-select-form',
			'input_id'   => 'pp-selected-users',
			);
			$parsed_args = wp_parse_args( $args, $defaults );
			extract( $parsed_args, EXTR_SKIP );

			$args = array(
			'who'     => 'authors',
			'fields'  => array(
				'ID',
				'display_name',
				'user_email',
			),
			'orderby' => 'display_name',
			);
			$args  = apply_filters( 'pp_users_select_form_get_users_args', $args );
			$users = get_users( $args );

			if ( ! is_array( $selected ) ) {
				$selected = array();
			}
			?>

			<?php if ( ! empty( $users ) ) : ?>
			<ul class="<?php echo esc_attr( $list_class ) ?>">
				<?php foreach ( $users as $user ) : ?>
					<?php $checked = (in_array( $user->ID, $selected )) ? 'checked="checked"' : '';
		?>
					<li>
						<label for="<?php echo esc_attr( $input_id . '-' . $user->ID ) ?>">
							<input type="checkbox" id="<?php echo esc_attr( $input_id . '-' . $user->ID ) ?>" name="<?php echo esc_attr( $input_id ) ?>[]" value="<?php echo esc_attr( $user->ID );
		?>" <?php echo $checked;
		?> />
							<span class="pp-user_displayname"><?php echo esc_html( $user->display_name );
		?></span>
							<span class="pp-user_useremail"><?php echo esc_html( $user->user_email );
		?></span>
						</label>
					</li>
				<?php endforeach;
		?>
			</ul>
		<?php endif;
		?>
		<?php

		}

		/**
		 * Adds an array of capabilities to a role.
		 *
		 * @since 0.7
		 *
		 * @param string $role A standard WP user role like 'administrator' or 'author'
		 * @param array  $caps One or more user caps to add
		 */
		public function add_caps_to_role( $role, $caps ) {

			// In some contexts, we don't want to add caps to roles
			if ( apply_filters( 'pp_kill_add_caps_to_role', false, $role, $caps ) ) {
				return;
			}

			global $wp_roles;

			if ( $wp_roles->is_role( $role ) ) {
				$role = get_role( $role );

				foreach ( $caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}

		/**
		 * Add settings help menus to our module screens if the values exist
		 * Auto-registered in PublishPress::register_module()
		 *
		 * @since 0.7
		 */
		public function action_settings_help_menu() {
			$screen = get_current_screen();

			if ( ! method_exists( $screen, 'add_help_tab' ) ) {
				return;
			}

			if ( $screen->id != 'publishpress_page_' . $this->module->settings_slug ) {
				return;
			}

			// Make sure we have all of the required values for our tab
			if ( isset( $this->module->settings_help_tab['id'], $this->module->settings_help_tab['title'], $this->module->settings_help_tab['content'] ) ) {
				$screen->add_help_tab( $this->module->settings_help_tab );

				if ( isset( $this->module->settings_help_sidebar ) ) {
					$screen->set_help_sidebar( $this->module->settings_help_sidebar );
				}
			}
		}

		/**
		 * Upgrade the term descriptions for all of the terms in a given taxonomy
		 */
		public function upgrade_074_term_descriptions( $taxonomy ) {
			$args = array(
			'hide_empty' => false,
			);

			$terms = get_terms( $taxonomy, $args );
			foreach ( $terms as $term ) {
				// If we can detect that this term already follows the new scheme, let's skip it
				$maybe_serialized = base64_decode( $term->description );
				if ( is_serialized( $maybe_serialized ) ) {
					continue;
				}

				$description_args = array();

				// This description has been JSON-encoded, so let's decode it
				if ( 0 === strpos( $term->description, '{' ) ) {
					$string_to_unencode = stripslashes( htmlspecialchars_decode( $term->description ) );
					$unencoded_array    = json_decode( $string_to_unencode, true );
					// Only continue processing if it actually was an array. Otherwise, set to the original string
					if ( is_array( $unencoded_array ) ) {
						foreach ( $unencoded_array as $key => $value ) {
							// html_entity_decode only works on strings but sometimes we store nested arrays
							if ( ! is_array( $value ) ) {
								$description_args[ $key ] = html_entity_decode( $value, ENT_QUOTES );
							} else {
								$description_args[ $key ] = $value;
							}
						}
					}
				} else {
					$description_args['description'] = $term->description;
				}

				$new_description = $this->get_encoded_description( $description_args );
				wp_update_term( $term->term_id, $taxonomy, array(
					'description' => $new_description,
				) );
			}
		}
	}
}// End if().
