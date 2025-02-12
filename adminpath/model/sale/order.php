<?php
namespace Opencart\Admin\Model\Sale;
/**
 * Class Order
 *
 * @package Opencart\Admin\Model\Sale
 */
class Order extends \Opencart\System\Engine\Model {
	/**
	 * Delete Order
	 *
	 * @param int $return_id
	 *
	 * @return void
	 */
	public function deleteOrder(int $order_id): void {
		$this->deleteProducts($order_id);
		$this->deleteTotals($order_id);
		$this->deleteHistories($order_id);

		$this->db->query("DELETE FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Get Order
	 *
	 * @param int $order_id
	 *
	 * @return array<string, mixed>
	 */
	public function getOrder(int $order_id): array {
		$order_query = $this->db->query("SELECT *, (SELECT `os`.`name` FROM `" . DB_PREFIX . "order_status` `os` WHERE `os`.`order_status_id` = `o`.`order_status_id` AND `os`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') AS `order_status` FROM `" . DB_PREFIX . "order` `o` WHERE `o`.`order_id` = '" . (int)$order_id . "'");

		if ($order_query->num_rows) {
			$this->load->model('localisation/country');

			$country_info = $this->model_localisation_country->getCountry($order_query->row['payment_country_id']);

			if ($country_info) {
				$payment_iso_code_2 = $country_info['iso_code_2'];
				$payment_iso_code_3 = $country_info['iso_code_3'];
			} else {
				$payment_iso_code_2 = '';
				$payment_iso_code_3 = '';
			}

			$this->load->model('localisation/zone');

			$zone_info = $this->model_localisation_zone->getZone($order_query->row['payment_zone_id']);

			if ($zone_info) {
				$payment_zone_code = $zone_info['code'];
			} else {
				$payment_zone_code = '';
			}

			$country_info = $this->model_localisation_country->getCountry($order_query->row['shipping_country_id']);

			if ($country_info) {
				$shipping_iso_code_2 = $country_info['iso_code_2'];
				$shipping_iso_code_3 = $country_info['iso_code_3'];
			} else {
				$shipping_iso_code_2 = '';
				$shipping_iso_code_3 = '';
			}

			$zone_info = $this->model_localisation_zone->getZone($order_query->row['shipping_zone_id']);

			if ($zone_info) {
				$shipping_zone_code = $zone_info['code'];
			} else {
				$shipping_zone_code = '';
			}

			$reward = 0;

			$products = $this->getProducts($order_id);

			foreach ($products as $product) {
				$reward += $product['reward'];
			}

			$this->load->model('customer/customer');

			$affiliate_info = $this->model_customer_customer->getCustomer($order_query->row['affiliate_id']);

			if ($affiliate_info) {
				$affiliate = $affiliate_info['firstname'] . ' ' . $affiliate_info['lastname'];
			} else {
				$affiliate = '';
			}

			$this->load->model('localisation/language');

			$language_info = $this->model_localisation_language->getLanguage($order_query->row['language_id']);

			if ($language_info) {
				$language_code = $language_info['code'];
			} else {
				$language_code = $this->config->get('config_language');
			}

			return [
				'custom_field'          => json_decode($order_query->row['custom_field'], true),
				'payment_zone_code'     => $payment_zone_code,
				'payment_iso_code_2'    => $payment_iso_code_2,
				'payment_iso_code_3'    => $payment_iso_code_3,
				'payment_custom_field'  => json_decode($order_query->row['payment_custom_field'], true),
				'payment_method'        => json_decode($order_query->row['payment_method'], true),
				'shipping_zone_code'    => $shipping_zone_code,
				'shipping_iso_code_2'   => $shipping_iso_code_2,
				'shipping_iso_code_3'   => $shipping_iso_code_3,
				'shipping_custom_field' => json_decode($order_query->row['shipping_custom_field'], true),
				'shipping_method'       => json_decode($order_query->row['shipping_method'], true),
				'reward'                => $reward,
				'affiliate'             => $affiliate,
				'language_code'         => $language_code
			] + $order_query->row;
		} else {
			return [];
		}
	}

	/**
	 * Get Orders
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getOrders(array $data = []): array {
		$sql = "SELECT `o`.`order_id`, CONCAT(`o`.`firstname`, ' ', `o`.`lastname`) AS customer, (SELECT `os`.`name` FROM `" . DB_PREFIX . "order_status` `os` WHERE `os`.`order_status_id` = `o`.`order_status_id` AND `os`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') AS order_status, `o`.`store_name`, `o`.`custom_field`, `o`.`payment_method`, `o`.`payment_custom_field`, `o`.`shipping_method`, `o`.`shipping_custom_field`, `o`.`total`, `o`.`currency_code`, `o`.`currency_value`, `o`.`date_added`, `o`.`date_modified` FROM `" . DB_PREFIX . "order` `o`";

		if (!empty($data['filter_order_status'])) {
			$implode = [];

			$order_statuses = explode(',', $data['filter_order_status']);
			$order_statuses = array_filter($order_statuses);

			foreach ($order_statuses as $order_status_id) {
				$implode[] = "`o`.`order_status_id` = '" . (int)$order_status_id . "'";
			}

			if ($implode) {
				$sql .= " WHERE (" . implode(" OR ", $implode) . ")";
			}
		} elseif (isset($data['filter_order_status_id']) && $data['filter_order_status_id'] !== '') {
			$sql .= " WHERE `o`.`order_status_id` = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
			$sql .= " WHERE `o`.`order_status_id` > '0'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND `o`.`order_id` = '" . (int)$data['filter_order_id'] . "'";
		}

		if (isset($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$sql .= " AND `o`.`store_id` = '" . (int)$data['filter_store_id'] . "'";
		}

		if (!empty($data['filter_customer_id'])) {
			$sql .= " AND `o`.`customer_id` = '" . (int)$data['filter_customer_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND LCASE(CONCAT(`o`.`firstname`, ' ', `o`.`lastname`)) LIKE '" . $this->db->escape('%' . oc_strtolower($data['filter_customer']) . '%') . "'";
		}

		if (!empty($data['filter_email'])) {
			$sql .= " AND LCASE(`o`.`email`) LIKE '" . $this->db->escape('%' . (string)$data['filter_email'] . '%') . "'";
		}

		if (!empty($data['filter_date_from'])) {
			$sql .= " AND DATE(`o`.`date_added`) >= DATE('" . $this->db->escape((string)$data['filter_date_from']) . "')";
		}

		if (!empty($data['filter_date_to'])) {
			$sql .= " AND DATE(`o`.`date_added`) <= DATE('" . $this->db->escape((string)$data['filter_date_to']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$sql .= " AND `o`.`total` = '" . (float)$data['filter_total'] . "'";
		}

		$sort_data = [
			'o.order_id',
			'o.store_name',
			'customer',
			'order_status',
			'o.date_added',
			'o.date_modified',
			'o.total'
		];

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY `o`.`order_id`";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$order_data = [];

		$query = $this->db->query($sql);

		foreach ($query->rows as $key => $result) {
			$order_data[$key] = [
				'custom_field'          => json_decode($result['custom_field'], true),
				'payment_custom_field'  => json_decode($result['payment_custom_field'], true),
				'payment_method'        => json_decode($result['payment_method'], true),
				'shipping_custom_field' => json_decode($result['shipping_custom_field'], true),
				'shipping_method'       => json_decode($result['shipping_method'], true)
			] + $result;
		}

		return $order_data;
	}

	/**
	 * Get Total Orders
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function getTotalOrders(array $data = []): int {
		$sql = "SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order`";

		if (!empty($data['filter_order_status'])) {
			$implode = [];

			$order_statuses = explode(',', $data['filter_order_status']);
			$order_statuses = array_filter($order_statuses);

			foreach ($order_statuses as $order_status_id) {
				$implode[] = "`order_status_id` = '" . (int)$order_status_id . "'";
			}

			if ($implode) {
				$sql .= " WHERE (" . implode(" OR ", $implode) . ")";
			}
		} elseif (isset($data['filter_order_status_id']) && $data['filter_order_status_id'] !== '') {
			$sql .= " WHERE `order_status_id` = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
			$sql .= " WHERE `order_status_id` > '0'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND `order_id` = '" . (int)$data['filter_order_id'] . "'";
		}

		if (isset($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$sql .= " AND `store_id` = '" . (int)$data['filter_store_id'] . "'";
		}

		if (!empty($data['filter_customer_id'])) {
			$sql .= " AND `customer_id` = '" . (int)$data['filter_customer_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND LCASE(CONCAT(`firstname`, ' ', `lastname`)) LIKE '" . $this->db->escape('%' . oc_strtolower($data['filter_customer']) . '%') . "'";
		}

		if (!empty($data['filter_email'])) {
			$sql .= " AND LCASE(`email`) LIKE '" . $this->db->escape('%' . oc_strtolower($data['filter_email']) . '%') . "'";
		}

		if (!empty($data['filter_date_from'])) {
			$sql .= " AND DATE(`date_added`) >= DATE('" . $this->db->escape((string)$data['filter_date_from']) . "')";
		}

		if (!empty($data['filter_date_to'])) {
			$sql .= " AND DATE(`date_added`) <= DATE('" . $this->db->escape((string)$data['filter_date_to']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$sql .= " AND `total` = '" . (float)$data['filter_total'] . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Get Orders By Subscription ID
	 *
	 * @param int $subscription_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getOrdersBySubscriptionId(int $subscription_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE `subscription_id` = '" . (int)$subscription_id . "'");

		return $query->rows;
	}

	/**
	 * Get Total Orders By Language ID
	 * Get Total Orders By Language ID
	 *
	 * @param int $language_id
	 *
	 * @return int
	 */
	public function getTotalOrdersByLanguageId(int $language_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order` WHERE `language_id` = '" . (int)$language_id . "' AND `order_status_id` > '0'");

		return (int)$query->row['total'];
	}

	/**
	 * Get Total Orders By Currency ID
	 *
	 * @param int $currency_id
	 *
	 * @return int
	 */
	public function getTotalOrdersByCurrencyId(int $currency_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order` WHERE `currency_id` = '" . (int)$currency_id . "' AND `order_status_id` > '0'");

		return (int)$query->row['total'];
	}

	/**
	 * Get Total Orders By Subscription ID
	 *
	 * @param int $subscription_id
	 *
	 * @return int
	 */
	public function getTotalOrdersBySubscriptionId(int $subscription_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order` WHERE `subscription_id` = '" . (int)$subscription_id . "'");

		return (int)$query->row['total'];
	}
	/**
	 * Get Total Orders By Store ID
	 *
	 * @param int $store_id
	 *
	 * @return int
	 */
	public function getTotalOrdersByStoreId(int $store_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order` WHERE `store_id` = '" . (int)$store_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Get Total Orders By Order Status ID
	 *
	 * @param int $order_status_id
	 *
	 * @return int
	 */
	public function getTotalOrdersByOrderStatusId(int $order_status_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order` WHERE `order_status_id` = '" . (int)$order_status_id . "' AND `order_status_id` > '0'");

		return (int)$query->row['total'];
	}

	/**
	 * Get Total Orders By Processing Status
	 *
	 * @return int
	 */
	public function getTotalOrdersByProcessingStatus(): int {
		$implode = [];

		$order_statuses = (array)$this->config->get('config_processing_status');

		foreach ($order_statuses as $order_status_id) {
			$implode[] = "`order_status_id` = '" . (int)$order_status_id . "'";
		}

		if ($implode) {
			$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order` WHERE " . implode(" OR ", $implode));

			return (int)$query->row['total'];
		} else {
			return 0;
		}
	}

	/**
	 * Get Total Orders By Complete Status
	 *
	 * @return int
	 */
	public function getTotalOrdersByCompleteStatus(): int {
		$implode = [];

		$order_statuses = (array)$this->config->get('config_complete_status');

		foreach ($order_statuses as $order_status_id) {
			$implode[] = "`order_status_id` = '" . (int)$order_status_id . "'";
		}

		if ($implode) {
			$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order` WHERE " . implode(" OR ", $implode));

			return (int)$query->row['total'];
		} else {
			return 0;
		}
	}

	/**
	 * Delete Products
	 *
	 * @param int $order_id
	 * @param int $order_product_id
	 *
	 * @return void
	 */
	public function deleteProducts(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "'");

		$this->deleteOptions($order_id);
		$this->deleteSubscription($order_id);
	}

	/**
	 * Get Product
	 *
	 * @param int $order_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getProduct(int $order_id, $order_product_id): array {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "' AND `order_product_id` = '" . (int)$order_product_id . "'");

		return $query->row;
	}

	/**
	 * Get Products
	 *
	 * @param int $order_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getProducts(int $order_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY order_product_id ASC");

		return $query->rows;
	}

	/**
	 * Get Total Products By Product ID
	 *
	 * @param int $product_id
	 *
	 * @return int
	 */
	public function getTotalProductsByProductId(int $product_id): int {
		$query = $this->db->query("SELECT SUM(`op`.`quantity`) AS `total` FROM `" . DB_PREFIX . "order_product` `op` LEFT JOIN `" . DB_PREFIX . "order` `o` ON (`op`.`order_id` = `o`.`order_id`) WHERE `op`.`product_id` = '" . (int)$product_id . "' AND `order_status_id` > '0'");

		return (int)$query->row['total'];
	}

	/**
	 * Delete Options
	 *
	 * @param int $order_id
	 * @param int $order_product_id
	 *
	 * @return void
	 */
	public function deleteOptions(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_option` WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Get Options
	 *
	 * @param int $order_id
	 * @param int $order_product_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getOptions(int $order_id, int $order_product_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_option` WHERE `order_id` = '" . (int)$order_id . "' AND `order_product_id` = '" . (int)$order_product_id . "'");

		return $query->rows;
	}

	/**
	 * Delete Subscription
	 *
	 * @param int $order_id
	 * @param int $order_product_id
	 *
	 * @return void
	 */
	public function deleteSubscription(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_subscription` WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Get Subscription
	 *
	 * @param int $order_id
	 * @param int $order_product_id
	 *
	 * @return array<string, mixed>
	 */
	public function getSubscription(int $order_id, int $order_product_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_subscription` WHERE `order_id` = '" . (int)$order_id . "' AND `order_product_id` = '" . (int)$order_product_id . "'");

		return $query->row;
	}

	/**
	 * Delete Totals
	 *
	 * @param int $order_id
	 */
	public function deleteTotals(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Get Totals
	 *
	 * @param int $order_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTotals(int $order_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `sort_order`");

		return $query->rows;
	}

	/**
	 * Get Totals By Code
	 *
	 * @param int   $order_id
	 * @param mixed $code
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTotalsByCode(int $order_id, $code): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . (int)$order_id . "' AND code = '" . $this->db->escape($code) . "' ORDER BY `sort_order`");

		return $query->rows;
	}

	/**
	 * Get Total Sales
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return float
	 */
	public function getTotalSales(array $data = []): float {
		$sql = "SELECT SUM(`total`) AS `total` FROM `" . DB_PREFIX . "order`";

		if (!empty($data['filter_order_status'])) {
			$implode = [];

			$order_statuses = explode(',', $data['filter_order_status']);
			$order_statuses = array_filter($order_statuses);

			foreach ($order_statuses as $order_status_id) {
				$implode[] = "`order_status_id` = '" . (int)$order_status_id . "'";
			}

			if ($implode) {
				$sql .= " WHERE (" . implode(" OR ", $implode) . ")";
			}
		} elseif (isset($data['filter_order_status_id']) && $data['filter_order_status_id'] !== '') {
			$sql .= " WHERE `order_status_id` = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
			$sql .= " WHERE `order_status_id` > '0'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND `order_id` = '" . (int)$data['filter_order_id'] . "'";
		}

		if (isset($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$sql .= " AND `store_id` = '" . (int)$data['filter_store_id'] . "'";
		}

		if (!empty($data['filter_customer_id'])) {
			$sql .= " AND `customer_id` = '" . (int)$data['filter_customer_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND LCASE(CONCAT(`firstname`, ' ', `lastname`)) LIKE '" . $this->db->escape('%' . oc_strtolower($data['filter_customer']) . '%') . "'";
		}

		if (!empty($data['filter_email'])) {
			$sql .= " AND LCASE(`email`) LIKE '" . $this->db->escape('%' . oc_strtolower($data['filter_email']) . '%') . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(`date_added`) = DATE('" . $this->db->escape((string)$data['filter_date_added']) . "')";
		}

		if (!empty($data['filter_date_modified'])) {
			$sql .= " AND DATE(`date_modified`) = DATE('" . $this->db->escape((string)$data['filter_date_modified']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$sql .= " AND `total` = '" . (float)$data['filter_total'] . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Create Invoice No
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function createInvoiceNo(int $order_id): string {
		$order_info = $this->getOrder($order_id);

		if ($order_info && !$order_info['invoice_no']) {
			$query = $this->db->query("SELECT MAX(`invoice_no`) AS `invoice_no` FROM `" . DB_PREFIX . "order` WHERE `invoice_prefix` = '" . $this->db->escape($order_info['invoice_prefix']) . "'");

			if ($query->row['invoice_no']) {
				$invoice_no = $query->row['invoice_no'] + 1;
			} else {
				$invoice_no = 1;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `invoice_no` = '" . (int)$invoice_no . "', `invoice_prefix` = '" . $this->db->escape($order_info['invoice_prefix']) . "' WHERE `order_id` = '" . (int)$order_id . "'");

			return $order_info['invoice_prefix'] . $invoice_no;
		}

		return '';
	}

	/**
	 * Get Reward Total
	 *
	 * @param int $order_id
	 *
	 * @return int
	 */
	public function getRewardTotal(int $order_id): int {
		$query = $this->db->query("SELECT SUM(`reward`) AS `total` FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Delete Order History
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function deleteHistories(int $order_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_history` WHERE `order_id` = '" . (int)$order_id . "'");
	}

	/**
	 * Get Histories
	 *
	 * @param int $order_id
	 * @param int $start
	 * @param int $limit
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getHistories(int $order_id, int $start = 0, int $limit = 10): array {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$query = $this->db->query("SELECT `oh`.`date_added`, `os`.`name` AS `status`, `oh`.`comment`, `oh`.`notify` FROM `" . DB_PREFIX . "order_history` `oh` LEFT JOIN `" . DB_PREFIX . "order_status` `os` ON `oh`.`order_status_id` = `os`.`order_status_id` WHERE `oh`.`order_id` = '" . (int)$order_id . "' AND `os`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' ORDER BY `oh`.`date_added` DESC LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	/**
	 * Get Total Histories
	 *
	 * @param int $order_id
	 *
	 * @return int
	 */
	public function getTotalHistories(int $order_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order_history` WHERE `order_id` = '" . (int)$order_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Get Total Histories By Order Status ID
	 *
	 * @param int $order_status_id
	 *
	 * @return int
	 */
	public function getTotalHistoriesByOrderStatusId(int $order_status_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "order_history` WHERE `order_status_id` = '" . (int)$order_status_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Get Emails By Products Ordered
	 *
	 * @param array<int> $products
	 * @param int        $start
	 * @param int        $end
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getEmailsByProductsOrdered(array $products, int $start, int $end): array {
		$implode = [];

		foreach ($products as $product_id) {
			$implode[] = "`op`.`product_id` = '" . (int)$product_id . "'";
		}

		$query = $this->db->query("SELECT DISTINCT `o`.`email` FROM `" . DB_PREFIX . "order` `o` LEFT JOIN `" . DB_PREFIX . "order_product` `op` ON (`o`.`order_id` = `op`.`order_id`) WHERE (" . implode(" OR ", $implode) . ") AND `o`.`order_status_id` <> '0' LIMIT " . (int)$start . "," . (int)$end);

		return $query->rows;
	}

	/**
	 * Get Total Emails By Products Ordered
	 *
	 * @param array<int> $products
	 *
	 * @return int
	 */
	public function getTotalEmailsByProductsOrdered(array $products): int {
		$implode = [];

		foreach ($products as $product_id) {
			$implode[] = "`op`.`product_id` = '" . (int)$product_id . "'";
		}

		$query = $this->db->query("SELECT COUNT(DISTINCT `o`.`email`) AS `total` FROM `" . DB_PREFIX . "order` `o` LEFT JOIN `" . DB_PREFIX . "order_product` `op` ON (`o`.`order_id` = `op`.`order_id`) WHERE (" . implode(" OR ", $implode) . ") AND `o`.`order_status_id` <> '0'");

		return (int)$query->row['total'];
	}
}
