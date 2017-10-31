<?php
/**
 * AfterShip API Orders Class
 *
 * Handles requests to the /orders endpoint
 *
 * @author      AfterShip
 * @category    API
 * @package     AfterShip/API
 * @since       1.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class AfterShip_API_Orders extends AfterShip_API_Resource
{

	/** @var string $base the route base */
	protected $base = '/orders';

	/**
	 * Register the routes for this class
	 *
	 * GET /orders
	 * GET /orders/count
	 * GET|PUT /orders/<id>
	 * GET /orders/<id>/notes
	 *
	 * @since 2.1
	 * @param array $routes
	 * @return array
	 */
	public function register_routes($routes)
	{

		# GET /orders
		$routes[$this->base] = array(
			array(array($this, 'get_orders'), AfterShip_API_Server::READABLE),
		);

		# GET /orders/count
		$routes[$this->base . '/count'] = array(
			array(array($this, 'get_orders_count'), AfterShip_API_Server::READABLE),
		);

		# GET|PUT /orders/<id>
		$routes[$this->base . '/(?P<id>\d+)'] = array(
			array(array($this, 'get_order'), AfterShip_API_Server::READABLE),
			array(array($this, 'edit_order'), AfterShip_API_Server::EDITABLE | AfterShip_API_Server::ACCEPT_DATA),
		);

		# GET /orders/<id>/notes
		$routes[$this->base . '/(?P<id>\d+)/notes'] = array(
			array(array($this, 'get_order_notes'), AfterShip_API_Server::READABLE),
		);

		# GET /orders/ping
		$routes[$this->base . '/ping'] = array(
			array(array($this, 'ping'), AfterShip_API_Server::READABLE),
		);

		return $routes;
	}

	/**
	 * Get all orders
	 *
	 * @since 2.1
	 * @param string $fields
	 * @param array $filter
	 * @param string $status
	 * @param int $page
	 * @return array
	 */
	public function get_orders($fields = null, $filter = array(), $status = null, $page = 1)
	{

		if (!empty($status))
			$filter['status'] = $status;

		$filter['page'] = $page;

		$query = $this->query_orders($filter);

		$orders = array();

		foreach ($query->posts as $order_id) {

			if (!$this->is_readable($order_id))
				continue;

			$orders[] = current($this->get_order($order_id, $fields));
		}

		$this->server->add_pagination_headers($query);

		return array('orders' => $orders);
	}


	/**
	 * Get the order for the given ID
	 *
	 * @since 2.1
	 * @param int $id the order ID
	 * @param array $fields
	 * @return array
	 */
	public function get_order($id, $fields = null)
	{

		// ensure order ID is valid & user has permission to read
		$id = $this->validate_request($id, 'shop_order', 'read');

		if (is_wp_error($id))
			return $id;

		$order = new WC_Order($id);

		$order_post = get_post($id);

		$order_data = array(
			'id' => (method_exists($order, 'get_id'))? $order->get_id() : $order->id,
			'order_number' => $order->get_order_number(),
			'created_at' => $this->server->format_datetime($order_post->post_date_gmt),
			'updated_at' => $this->server->format_datetime($order_post->post_modified_gmt),
//			'completed_at' => $this->server->format_datetime($order->completed_date, true),
//			'status' => $order->status,
//			'currency' => $order->order_currency,
//			'total' => wc_format_decimal($order->get_total(), 2),
//			'subtotal' => wc_format_decimal($this->get_order_subtotal($order), 2),
//			'total_line_items_quantity' => $order->get_item_count(),
//			'total_tax' => wc_format_decimal($order->get_total_tax(), 2),
//			'total_shipping' => wc_format_decimal($order->get_total_shipping(), 2),
//			'cart_tax' => wc_format_decimal($order->get_cart_tax(), 2),
//			'shipping_tax' => wc_format_decimal($order->get_shipping_tax(), 2),
//			'total_discount' => wc_format_decimal($order->get_total_discount(), 2),
//			'cart_discount' => wc_format_decimal($order->get_cart_discount(), 2),
//			'order_discount' => wc_format_decimal($order->get_order_discount(), 2),
//			'shipping_methods' => $order->get_shipping_method(),
//			'payment_details' => array(
//				'method_id' => $order->payment_method,
//				'method_title' => $order->payment_method_title,
//				'paid' => isset($order->paid_date),
//			),
			'billing_address' => array(
				'first_name' => $order->billing_first_name,
				'last_name' => $order->billing_last_name,
				'company' => $order->billing_company,
				'address_1' => $order->billing_address_1,
				'address_2' => $order->billing_address_2,
				'city' => $order->billing_city,
				'state' => $order->billing_state,
				'postcode' => $order->billing_postcode,
				'country' => $order->billing_country,
				'email' => $order->billing_email,
				'phone' => $order->billing_phone,
			),
			'shipping_address' => array(
				'first_name' => $order->shipping_first_name,
				'last_name' => $order->shipping_last_name,
				'company' => $order->shipping_company,
				'address_1' => $order->shipping_address_1,
				'address_2' => $order->shipping_address_2,
				'city' => $order->shipping_city,
				'state' => $order->shipping_state,
				'postcode' => $order->shipping_postcode,
				'country' => $order->shipping_country,
			),
			'note' => $order->customer_note,
//			'customer_ip' => $order->customer_ip_address,
//			'customer_user_agent' => $order->customer_user_agent,
//			'customer_id' => $order->customer_user,
//			'view_order_url' => $order->get_view_order_url(),
			'line_items' => array(),
//			'shipping_lines' => array(),
//			'tax_lines' => array(),
//			'fee_lines' => array(),
//			'coupon_lines' => array(),
		);

		// add line items
		foreach ($order->get_items() as $item_id => $item) {

//			$product = $order->get_product_from_item($item);

			$order_data['line_items'][] = array(
				'id' => $item_id,
//				'subtotal' => wc_format_decimal($order->get_line_subtotal($item), 2),
//				'total' => wc_format_decimal($order->get_line_total($item), 2),
//				'total_tax' => wc_format_decimal($order->get_line_tax($item), 2),
//				'price' => wc_format_decimal($order->get_item_total($item), 2),
				'quantity' => (int)$item['qty'],
//				'tax_class' => (!empty($item['tax_class'])) ? $item['tax_class'] : null,
				'name' => $item['name'],
//				'product_id' => (isset($product->variation_id)) ? $product->variation_id : (method_exists($product, 'get_id'))? $product->get_id() : $product->id,
//				'sku' => is_object($product) ? $product->get_sku() : null,
			);
		}

		/*
		// add shipping
		foreach ($order->get_shipping_methods() as $shipping_item_id => $shipping_item) {

			$order_data['shipping_lines'][] = array(
				'id' => $shipping_item_id,
				'method_id' => $shipping_item['method_id'],
				'method_title' => $shipping_item['name'],
				'total' => wc_format_decimal($shipping_item['cost'], 2),
			);
		}

		// add taxes
		foreach ($order->get_tax_totals() as $tax_code => $tax) {

			$order_data['tax_lines'][] = array(
				'code' => $tax_code,
				'title' => $tax->label,
				'total' => wc_format_decimal($tax->amount, 2),
				'compound' => (bool)$tax->is_compound,
			);
		}

		// add fees
		foreach ($order->get_fees() as $fee_item_id => $fee_item) {

			$order_data['fee_lines'][] = array(
				'id' => $fee_item_id,
				'title' => $fee_item['name'],
				'tax_class' => (!empty($fee_item['tax_class'])) ? $fee_item['tax_class'] : null,
				'total' => wc_format_decimal($order->get_line_total($fee_item), 2),
				'total_tax' => wc_format_decimal($order->get_line_tax($fee_item), 2),
			);
		}

		// add coupons
		foreach ($order->get_items('coupon') as $coupon_item_id => $coupon_item) {

			$order_data['coupon_lines'][] = array(
				'id' => $coupon_item_id,
				'code' => $coupon_item['name'],
				'amount' => wc_format_decimal($coupon_item['discount_amount'], 2),
			);
		}
		*/

		// aftership add
		$options = get_option('aftership_option_name');
		$plugin = $options['plugin'];
		if ($plugin == 'aftership') {

//            $result = array();
//            foreach($this->aftership_fields as $field){
//                $id = $field['id'];
//                $result[substr($id,10)] = get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_' . $field['id'], true);
//            }
//            $order_data['aftership']['woocommerce']['trackings'][] = $result;

			$order_data['aftership']['woocommerce']['trackings'][] = array(
				'tracking_provider' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_aftership_tracking_provider', true),
				'tracking_number' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_aftership_tracking_number', true),
				'tracking_ship_date' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_aftership_tracking_shipdate', true),
				'tracking_postal_code' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_aftership_tracking_postal', true),
				'tracking_account_number' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_aftership_tracking_account', true),
                'tracking_key' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_aftership_tracking_key', true),
                'tracking_destination_country' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_aftership_tracking_destination_country', true),
			);
		}
		if ($tn == NULL) {
			// Handle old Shipping Tracking plugin
			$tn = get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_tracking_number', true);
			if ($tn == NULL) {
				// Handle new Shipping Tracking plugin version higher than 1.6.4
				$order_data['aftership']['woocommerce']['trackings'][] = array(
					'tracking_number' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_wc_shipment_tracking_items', true)[0]['tracking_number'],
					'tracking_provider' => get_post_meta((method_exists($order, 'get_id'))? $order->get_id() : $order->id, '_wc_shipment_tracking_items', true)[0]['custom_tracking_provider'],
				);
			} else {
				$order_data['aftership']['woocommerce']['trackings'][] = array(
					'tracking_number' => $tn,
				);
			}
		}
		// aftership add finish

		return array('order' => apply_filters('aftership_api_order_response', $order_data, $order, $fields, $this->server));
	}

	/**
	 * Get the total number of orders
	 *
	 * @since 2.1
	 * @param string $status
	 * @param array $filter
	 * @return array
	 */
	public function get_orders_count($status = null, $filter = array())
	{

		if (!empty($status))
			$filter['status'] = $status;

		$query = $this->query_orders($filter);

		if (!current_user_can('read_private_shop_orders'))
			return new WP_Error('aftership_api_user_cannot_read_orders_count', __('You do not have permission to read the orders count', 'aftership'), array('status' => 401));

		return array('count' => (int)$query->found_posts);
	}

	/**
	 * Edit an order
	 *
	 * API v1 only allows updating the status of an order
	 *
	 * @since 2.1
	 * @param int $id the order ID
	 * @param array $data
	 * @return array
	 */
	public function edit_order($id, $data)
	{

		$id = $this->validate_request($id, 'shop_order', 'edit');

		if (is_wp_error($id))
			return $id;

		$order = new WC_Order($id);

		if (!empty($data['status'])) {

			$order->update_status($data['status'], isset($data['note']) ? $data['note'] : '');
		}

		return $this->get_order($id);
	}

	/**
	 * Delete an order
	 *
	 * @TODO enable along with POST in 2.2
	 * @param int $id the order ID
	 * @param bool $force true to permanently delete order, false to move to trash
	 * @return array
	 */
	public function delete_order($id, $force = false)
	{

		$id = $this->validate_request($id, 'shop_order', 'delete');

		return $this->delete($id, 'order', ('true' === $force));
	}

	/**
	 * Get the admin order notes for an order
	 *
	 * @since 2.1
	 * @param int $id the order ID
	 * @param string $fields fields to include in response
	 * @return array
	 */
	public function get_order_notes($id, $fields = null)
	{

		// ensure ID is valid order ID
		$id = $this->validate_request($id, 'shop_order', 'read');

		if (is_wp_error($id))
			return $id;

		$args = array(
			'post_id' => $id,
			'approve' => 'approve',
			'type' => 'order_note'
		);

		remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'), 10, 1);

		$notes = get_comments($args);

		add_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'), 10, 1);

		$order_notes = array();

		foreach ($notes as $note) {

			$order_notes[] = array(
				'id' => $note->comment_ID,
				'created_at' => $this->server->format_datetime($note->comment_date_gmt),
				'note' => $note->comment_content,
				'customer_note' => get_comment_meta($note->comment_ID, 'is_customer_note', true) ? true : false,
			);
		}

		return array('order_notes' => apply_filters('aftership_api_order_notes_response', $order_notes, $id, $fields, $notes, $this->server));
	}

	/**
	 * Helper method to get order post objects
	 *
	 * @since 2.1
	 * @param array $args request arguments for filtering query
	 * @return WP_Query
	 */
	private function query_orders($args)
	{

		function aftership_wpbo_get_woo_version_number()
		{
			// If get_plugins() isn't available, require it
			if (!function_exists('get_plugins'))
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');

			// Create the plugins folder and file variables
			$plugin_folder = get_plugins('/' . 'woocommerce');
			$plugin_file = 'woocommerce.php';

			// If the plugin version number is set, return it
			if (isset($plugin_folder[$plugin_file]['Version'])) {
				return $plugin_folder[$plugin_file]['Version'];

			} else {
				// Otherwise return null
				return NULL;
			}
		}

		$woo_version = aftership_wpbo_get_woo_version_number();

		if ($woo_version >= 2.2) {
			// set base query arguments
			$query_args = array(
				'fields' => 'ids',
				'post_type' => 'shop_order',
				//			'post_status' => 'publish',
				'post_status' => array_keys(wc_get_order_statuses())
			);
		} else {
			// set base query arguments
			$query_args = array(
				'fields' => 'ids',
				'post_type' => 'shop_order',
				'post_status' => 'publish',
			);
		}

		// add status argument
		if (!empty($args['status'])) {

			$statuses = explode(',', $args['status']);

			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'shop_order_status',
					'field' => 'slug',
					'terms' => $statuses,
				),
			);

			unset($args['status']);
		}

		$query_args = $this->merge_query_args($query_args, $args);

        return new WP_Query($query_args);
	}

	/**
	 * Helper method to get the order subtotal
	 *
	 * @since 2.1
	 * @param WC_Order $order
	 * @return float
	 */
	private function get_order_subtotal($order)
	{

		$subtotal = 0;

		// subtotal
		foreach ($order->get_items() as $item) {

			$subtotal += (isset($item['line_subtotal'])) ? $item['line_subtotal'] : 0;
		}

		return $subtotal;
	}

	/**
	 * Get the total number of orders
	 *
	 * @since 2.1
	 * @param string $status
	 * @param array $filter
	 * @return array
	 */
	public function ping()
	{
		return "pong";
	}

}
