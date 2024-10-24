<?php
defined( "ABSPATH" ) || exit;

if ( ! class_exists( 'MWTSA_Process_Query' ) ) {

	class MWTSA_Process_Query {

		public static function process_wpforo_search_term_action( $args, $items_count, $posts, $sql ) {
			$process = new self();

			if ( apply_filters( 'mwtsa_wpforo_do_not_save_search', false, $args['needle'] ) ) {
				return;
			}

			$process->process_search_term( $args['needle'], $items_count );
		}

		public static function process_rest_api_search_term_action() {
			$process = new self();

			$custom_search_value = $process->get_custom_search_value();

			if ( apply_filters( 'mwtsa_rest_api_do_not_save_search', $custom_search_value === '', $custom_search_value ) ) {
				return;
			}

			$result_count = apply_filters( 'mwtsa_rest_api_result_count', 0, $custom_search_value );

			if ( $result_count === 0 ) {
				$args = array(
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
					'post_type'      => 'any',
					'offset'         => 0,
					'fields'         => 'ids',
					's'              => $custom_search_value,
				);

				$posts = get_posts( apply_filters( 'mwtsa_rest_api_posts_count_query_args', $args ) );

				if ( $posts ) {
					$result_count = count( $posts );
				}
			}

			$process->process_search_term( $custom_search_value, $result_count );
		}

		public static function process_search_term_action() {
			global $wp_query;

			$process = new self();

			$custom_search_value = $process->get_custom_search_value();

			if ( apply_filters( 'mwtsa_do_not_save_search', ( ! is_search() && $custom_search_value == '' ) || is_admin(), $custom_search_value ) ) {
				return;
			}

			$search_term = $custom_search_value != '' ? $custom_search_value : get_search_query();

			$process->process_search_term( $search_term, apply_filters( 'mwtsa_result_count', $wp_query->found_posts, $search_term ) );
		}

		public function get_custom_search_value() {
			$exclude_custom_search_params = MWTSA_Options::get_option( 'mwtsa_custom_search_url_params' );

			if ( empty( $exclude_custom_search_params ) ) {
				return '';
			}

			$custom_search_params = array_map( 'trim', explode( ',', $exclude_custom_search_params ) );
			foreach ( $custom_search_params as $param ) {
				if ( ! empty( $_REQUEST[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return sanitize_text_field( wp_unslash( $_REQUEST[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			}

			return '';
		}

		public function process_search_term( $search_term, $count ) {

			$exclude_search_for_roles = MWTSA_Options::get_option( 'mwtsa_exclude_search_for_role' );
			$current_user_roles       = mwt_get_current_user_roles();

			$exclude_search_for_roles_after_logout = MWTSA_Options::get_option( 'mwtsa_exclude_search_for_role_after_logout' );

			if ( ! empty( $exclude_search_for_roles_after_logout ) ) {
				$current_user_cookie = MWTSA_Cookies::get_cookie_value();

				if ( isset( $current_user_cookie['is_excluded'] ) && $current_user_cookie['is_excluded'] == 1 ) {
					return false;
				}
			}

			if ( is_array( $exclude_search_for_roles ) ) {
				$matching_roles = array_intersect( $exclude_search_for_roles, $current_user_roles );

				if ( count( $matching_roles ) > 0 ) {
					return false;
				}
			}

			$exclude_search_for_ips = MWTSA_Options::get_option( 'mwtsa_exclude_searches_from_ip_addresses' );

			$client_ip = mwt_get_current_user_ip();

			if ( ! empty( $exclude_search_for_ips ) ) {
				$ips_list     = array();
				$excluded_ips = explode( ',', $exclude_search_for_ips );

				foreach ( $excluded_ips as $ip ) {
					$ip = trim( $ip );

					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						$ips_list[] = $ip;
					}
				}

				if ( in_array( $client_ip, $ips_list ) ) {
					return false;
				}
			}

			$exclude_if_contains = MWTSA_Options::get_option( 'mwtsa_exclude_if_string_contains' );

			if ( ! empty( $exclude_if_contains ) ) {
				$match_against = array_map( 'trim', explode( ',', $exclude_if_contains ) );

				preg_match( '/(' . implode( '|', $match_against ) . ')/i', $search_term, $matches );

				if ( count( $matches ) > 0 ) {
					return false;
				}
			}

			if ( apply_filters( 'mwtsa_extra_exclude_conditions', false, $search_term ) ) {
				return false;
			}

			$country = '';

			if ( ! empty( MWTSA_Options::get_option( 'mwtsa_save_search_country' ) ) ) {
				//http://ip-api.com/json/24.48.0?fields=49154
				// IP-API integration according to the documentation at http://ip-api.com/docs/api:json
				$request        = wp_remote_get( 'http://ip-api.com/json/' . $client_ip . '?fields=49155' );
				$ip_details_get = wp_remote_retrieve_body( $request );
				if ( ! empty( $ip_details_get ) ) {
					$ip_details = json_decode( $ip_details_get );

					if ( $ip_details->status != 'fail' ) {
						$country = strtolower( $ip_details->countryCode );
					}
				}
			}

			$user_id = 0;

			if ( ! empty( MWTSA_Options::get_option( 'mwtsa_save_search_by_user' ) ) && is_user_logged_in() ) {
				$user    = wp_get_current_user();
				$user_id = $user->ID;
			}

			if ( ! empty( $search_term ) ) {

				$minimum_length_term = MWTSA_Options::get_option( 'mwtsa_minimum_characters' );

				if ( ! empty( $minimum_length_term ) && strlen( $search_term ) < $minimum_length_term ) {
					return false;
				}

				if ( apply_filters( 'mwtsa_exclude_term', false, $search_term ) ) {
					return false;
				}

				$this->save_search_term( $search_term, $count, $country, $user_id );
			}

			return true;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $mwtsa->terms_table_name and $mwtsa->history_table_name are hardcoded.
		public function save_search_term( $term, $found_posts, $country = '', $user_id = 0 ) {
			global $wpdb, $mwtsa;

			//make sure db is up to date
			// TODO: move this away
			MWTSA_Install::activate_single_site();

			//1. add/update term string
			$existing_term = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				"SELECT *
				FROM `$mwtsa->terms_table_name`
				WHERE term = %s
				LIMIT 1
				", $term
			) );

			$exclude_doubled_search_for = MWTSA_Options::get_option( 'mwtsa_exclude_doubled_search_for_interval' );

			$current_user_cookie = MWTSA_Cookies::get_cookie_value();

			$term_id = null;

			if ( empty ( $existing_term ) ) {
				$success = $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					"INSERT INTO `$mwtsa->terms_table_name` (`term`, `total_count`)
					VALUES (%s, %d)",
					sanitize_text_field( $term ),
					1
				) );

				if ( $success ) {
					$term_id = $wpdb->insert_id;
				}
			} else {

				if ( ! empty ( $exclude_doubled_search_for ) ) {
					if ( isset( $current_user_cookie['search'] ) && isset( $current_user_cookie['search'][ $existing_term->id ] ) && ( $current_user_cookie['search'][ $existing_term->id ] + ( 60 * $exclude_doubled_search_for ) ) > time() ) {
						return false;
					}
				}

				$total_count = $existing_term->total_count + 1;

				$success = $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					"UPDATE `$mwtsa->terms_table_name`
					SET total_count = %d
					WHERE term = %s
					LIMIT 1
					", $total_count, $term
				) );

				if ( $success ) {
					$term_id = $existing_term->id;
				}
			}

			do_action( 'mwtsa_after_term_save', $term, $term_id, ! empty( $existing_term ) );

			//2. add term timestamp + posts_count - ON term_id
			if ( ! empty( $term_id ) ) {

				$history_term_id = null;

				if ( ! empty ( $exclude_doubled_search_for ) ) {
					$current_user_cookie['search'][ $term_id ] = time();
					MWTSA_Cookies::set_cookie_value( $current_user_cookie, ( 86400 * 7 ) );
				}

				$success = $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					"INSERT INTO `$mwtsa->history_table_name` (`term_id`, `datetime`, `count_posts`, `country`, `user_id`)
					VALUES (%d, UTC_TIMESTAMP(), %d, %s, %d)",
					$term_id,
					$found_posts,
					$country,
					$user_id
				) );

				if ( $success ) {
					$history_term_id = $wpdb->insert_id;
				}

				do_action( 'mwtsa_after_history_term_save', $history_term_id, $term_id, $found_posts, $country, $user_id );
			}

			return $success;
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}