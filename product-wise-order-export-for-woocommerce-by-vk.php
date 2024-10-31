<?php 
/**
 * Plugin Name: Product Quantity Report By Order for Woocommerce
 * Description: List and export product quantity report by each order at one place.
 * Version: 1.0.0
 * Author: Vishal Kakadiya
 * Author URI: https://profiles.wordpress.org/vishalkakadiya
 * 
 */


if( !defined('ABSPATH') ){
	exit;
}


/**
 * Check if WooCommerce is already activated.
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	class VK_Product_Wise_Order_Export_For_WooCommerce {

		private $from_date = '';
		private $to_date = '';
		private $order_status = array();

		/**
		 * Constructor
		 */
		function __construct() {

			/* Sanitize post data */
			add_action( 'init', array( $this, 'owpe_sanitize_post_data' ) );

			/* Export CSV */
			add_action( 'admin_init', array( $this, 'owpe_csv_export' ) );

			/* Admin menu settings page */
			add_action( 'admin_menu', array( $this, 'owpe_admin_actions' ) );

			/* Admin scripts */
			add_action( 'admin_enqueue_scripts', array( $this, 'owpe_admin_scripts' ) );
		}


		/*
		 * Admin scripts
		 */
		function owpe_admin_scripts() {
			
			wp_enqueue_script( 'jquery-ui-datepicker' ); 
			wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');

			wp_enqueue_style( 'vk-wcu-admin-custom', plugin_dir_url( __FILE__ ) . 'assets/css/admin-custom.css' );
			wp_enqueue_script( 'vk-wcu-admin-custom', plugin_dir_url( __FILE__ ) . 'assets/js/admin-custom.js', '', '', false );
		}


		/*
		 *  Backend settings page
		 */
		function owpe_admin_actions() {
			add_menu_page( 
				'WooCommerce Order Wise Product Quantity Export', 
				'Order Wise Product Quantity', 
				'edit_pages', 
				'order_wise_product_quantity', 
				array( $this, 'owpe_quantity_report' ) 
			);
		}


		function owpe_sanitize_post_data() {
			if ( isset( $_POST['from_date'] ) && ! empty( $_POST['from_date'] ) ) {
				$this->from_date = sanitize_text_field( $_POST['from_date'] );
			}
			if ( isset( $_POST['to_date'] ) && ! empty( $_POST['to_date'] ) ) {
				$this->to_date = sanitize_text_field( $_POST['to_date'] );
			}
			if ( isset( $_POST['order_status'] ) ) { 
				$this->order_status = $_POST['order_status'];
			}
		}


		/*
		 *  Filter form
		 */
		function owpe_quantity_report() { ?>

			<div class="wpqo-main">
				<h1 class="sl-vc-settings-heading"><?php _e( 'WooCommerce Product Quantity By Order Export', 'woocommerce' ); ?></h1>
				<p class="sl-vc-settings-sub-heading"><?php _e( 'Check and export product qunatity report by all orders at same time.' , 'vc-pg-custom-select-custom-design' ); ?></p>

				<form method="post">
					<table id="table-form" cellspacing="23">
						<tr>
							<td><?php _e( 'From Date: ', 'woocommerce' ); ?></td>
							<td colspan="2"><input type="text" class="wpwo-datepicker" name="from_date" value="<?php echo $this->from_date;?>" id="wpwo-from-date" required /></td>
						</tr>
						<tr>
							<td><?php _e( 'To Date: ', 'woocommerce' ); ?></td>
							<td colspan="2"><input type="text" class="wpwo-datepicker" name="to_date" value="<?php echo $this->to_date;?>" id="wpwo-to-date" required /></td>
						</tr>
						<tr>
							<td><?php _e( 'Order Status: ', 'woocommerce' ); ?></td>
							<td colspan="2">
								<?php
									$order_statuses = array(
										'wc-completed'	=> 'Completed',
										'wc-cancelled'	=> 'Cancelled',
										'wc-pending'	=> 'Pending payment',
										'wc-processing' => 'Processing',
										'wc-on-hold'	=> 'On Hold',
										'wc-refunded'	=> 'Refunded',
										'wc-failed'		=> 'Failed'
									);
									
									foreach ( $order_statuses as $order_status_key => $order_status_name ) {
										$checked = '';
										if ( in_array( $order_status_key, $this->order_status ) ) {
											$checked = 'checked="checked"';
										}
										echo '<input type="checkbox" name="order_status[]" '.$checked.' value="'.$order_status_key.'" id="'.$order_status_key.'" />';
										echo '<label for="'.$order_status_key.'">' . __( $order_status_name, 'woocommerce' ) . '</label><br />';
									}
								?>
							</td>
						</tr>
						<tr>
							<td></td>
							<td><input name="wpwo_display_report" type="submit" class="button button-primary" value="Generate Report" /></td>
							<td><input name="wpwo_export_report" type="submit" class="button button-success" value="Export Report" /></td>
						</tr>
					</table>
				</form><?php

				if ( isset ( $_POST['wpwo_display_report'] ) ) { 
					self::owpe_generate_report_table();
				}
			?>
			</div><?php
		}


		/*
		 * Get data from db as user filter 
		 */
		function owpe_get_csv_data() {

			$args = array( 
				'post_type'		=> 'shop_order', 
				'post_status'	=> $this->order_status, 
				'order'			=> 'asc',
				'orderby'		=> 'id',
				'date_query'	=> array(
					array(
						'after'     => $this->from_date,
						'before'	=> $this->to_date			
					)
				)
			);

			// Order query
			$the_query = new WP_Query( $args );

			$order_ids = $order_dates = array();
			if ( $the_query->have_posts() ) : 
				while ( $the_query->have_posts() ) : $the_query->the_post(); 
					$order_id = get_the_ID();
					$order_details = new WC_Order( $order_id );
					$order_ids[ $order_id ] = 0;

					$items = $order_details->get_items();
					$order_items[ $order_id ] = $items;
				endwhile; 
				wp_reset_postdata();
			endif;

			$product_rows = array();
			if ( ! empty ( $order_items ) ) {
				foreach ( $order_items as $order_id => $items ) {
					foreach ( $items as $item ) {
						if ( ! isset ( $product_rows[ $item['product_id'] ] ) ) {
							$product_rows[ $item['product_id'] ]['product_id'] = $item['product_id'];
							$product_rows[ $item['product_id'] ]['orders'] = $order_ids;
							$product_rows[ $item['product_id'] ]['title'] = $item['name'];
						} 
						$product_rows[ $item['product_id'] ]['orders'][ $order_id ] += $item['qty'];
					}
				}
			}

			return array(
				'order_ids'		=> $order_ids,
				'product_rows'	=> $product_rows
			);
		}


		/*
		 * Generate report table
		 */
		function owpe_generate_report_table() { 

			if ( isset ( $_POST['wpwo_display_report'] ) ) { 
				$order_data = self::owpe_get_csv_data();
				$order_ids = $order_data['order_ids'];
				$product_rows = $order_data['product_rows']; 

				if ( ! empty ( $product_rows ) ) { ?>
					<div id="table-records">
						<table>
							<tr>
								<th><?php _e( '<strong>Product ID</strong> ', 'woocommerce-simply-order-export' ); ?></th>
								<th><?php _e( '<strong>Product / Order ID:</strong> ', 'woocommerce-simply-order-export' ); ?></th>
								<th><?php _e( '<strong>Total:</strong>', 'woocommerce-simply-order-export' ); ?></th>
								<?php 
									foreach ( $order_ids as $order_id => $order_value ) { 
										echo '<th>'. $order_id . '</th>';
									}
								?>
							</tr>
							<?php
								$product_rows = self::owpe_msort( $product_rows, array( 'title' ) );
								foreach ( $product_rows as $product ) { 
									$product_record = '';
									$product_record .= '<tr>';
									$product_record .= '<td><strong>' . $product['product_id'] . '</strong></td>';
									$product_record .= '<td><strong>' . $product['title'] . '</strong></td>';
									$product_record .= '<td><strong>' . array_sum( $product['orders'] ) . '</strong></td>';

									$quantity_data = '';
									foreach ( $product['orders'] as $qty ) {
										$product_record .= '<td>'. $qty .'</td>';
									}
									$product_record .= '</tr>';
									echo $product_record;
								} ?>
						</table>
					</div><?php
				} else {
					echo 'No orders found for this filter.';
				}
			}
		}


		/*
		 *  Export report list
		 */
		function owpe_csv_export() {

			if ( isset ( $_POST['wpwo_export_report'] ) ) { 

				$order_data = self::owpe_get_csv_data();
				$order_ids = $order_data['order_ids'];
				$product_rows = $order_data['product_rows'];

				$csv_fields = array();
				$csv_fields[] = __( 'Product ID ', 'woocommerce-simply-order-export' );
				$csv_fields[] = __( 'Product Name / Order ID: ', 'woocommerce-simply-order-export' );
				$csv_fields[] = __( 'Total: ', 'woocommerce-simply-order-export' );
				$csv_fields = array_merge( $csv_fields, array_keys( $order_ids ) );

				$output_filename = 'product-order-report-date-'.date( 'Y-m-d' ).'-time-'.date( 'H-i-s' ).'.csv';
				$output_handle = @fopen( 'php://output', 'w' );

				header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
				header( 'Content-Description: File Transfer' );
				header( 'Content-type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=' . $output_filename );
				header( 'Expires: 0' );
				header( 'Pragma: public' );	

				// Insert header row
				fputcsv( $output_handle, $csv_fields );

				$product_rows = self::owpe_msort( $product_rows, array( 'title' ) );

				foreach ( $product_rows as $product_row ) {
					$row = array();
					$row[0] = $product_row['product_id'];
					$row[1] = $product_row['title'];
					$row[2] = array_sum( $product_row['orders'] );
					$row = array_merge( $row, $product_row['orders'] );

					fputcsv( $output_handle, $row );
				}
				fclose( $output_handle ); 
				die();
			}
		}


		/*
		 * Sort array by specific array value
		 */
		function owpe_msort($array, $key, $sort_flags = SORT_REGULAR) {
			if (is_array($array) && count($array) > 0) {
				if (!empty($key)) {
					$mapping = array();
					foreach ($array as $k => $v) {
						$sort_key = '';
						if (!is_array($key)) {
							$sort_key = $v[$key];
						} else {
							foreach ($key as $key_key) {
								$sort_key .= $v[$key_key];
							}
							$sort_flags = SORT_STRING;
						}
						$mapping[$k] = $sort_key;
					}
					asort($mapping, $sort_flags);
					$sorted = array();
					foreach ($mapping as $k => $v) {
						$sorted[] = $array[$k];
					}
					return $sorted;
				}
			}
			return $array;
		}

	}

	new VK_Product_Wise_Order_Export_For_WooCommerce();
}
