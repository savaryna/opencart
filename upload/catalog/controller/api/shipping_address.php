<?php
namespace Opencart\catalog\controller\api;
/**
 * Class Shipping Address
 *
 * @package Opencart\Catalog\Controller\Api
 */
class ShippingAddress extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('api/shipping_address');

		$json = [];

		if ($this->request->get['route'] == 'api/shipping_address') {
			$this->load->controller('api/cart');
		}

		if ($this->cart->hasShipping()) {
			// Add keys for missing post vars
			$keys = [
				'shipping_firstname',
				'shipping_lastname',
				'shipping_company',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_postcode',
				'shipping_city',
				'shipping_zone_id',
				'shipping_country_id'
			];

			foreach ($keys as $key) {
				if (!isset($this->request->post[$key])) {
					$this->request->post[$key] = '';
				}
			}

			if (!oc_validate_length($this->request->post['shipping_firstname'], 1, 32)) {
				$json['error']['shipping_firstname'] = $this->language->get('error_firstname');
			}

			if (!oc_validate_length($this->request->post['shipping_lastname'], 1, 32)) {
				$json['error']['shipping_lastname'] = $this->language->get('error_lastname');
			}

			if (!oc_validate_length($this->request->post['shipping_address_1'], 3, 128)) {
				$json['error']['shipping_address_1'] = $this->language->get('error_address_1');
			}

			if (!oc_validate_length($this->request->post['shipping_city'], 2, 128)) {
				$json['error']['shipping_city'] = $this->language->get('error_city');
			}

			$this->load->model('localisation/country');

			$country_info = $this->model_localisation_country->getCountry((int)$this->request->post['shipping_country_id']);

			if ($country_info && $country_info['postcode_required'] && !oc_validate_length($this->request->post['shipping_postcode'], 2, 10)) {
				$json['error']['postcode'] = $this->language->get('error_postcode');
			}

			if (!$country_info || $this->request->post['shipping_country_id'] == '') {
				$json['error']['country'] = $this->language->get('error_country');
			}

			if ($this->request->post['shipping_zone_id'] == '') {
				$json['error']['zone'] = $this->language->get('error_zone');
			}

			// Custom field validation
			$this->load->model('account/custom_field');

			$custom_fields = $this->model_account_custom_field->getCustomFields((int)$this->config->get('config_customer_group_id'));

			foreach ($custom_fields as $custom_field) {
				if ($custom_field['location'] == 'address') {
					if ($custom_field['required'] && empty($this->request->post['shipping_custom_field'][$custom_field['custom_field_id']])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
					} elseif (($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !oc_validate_regex($this->request->post['shipping_custom_field'][$custom_field['custom_field_id']], $custom_field['validation'])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_regex'), $custom_field['name']);
					}
				}
			}
		} else {
			$json['error']['warning'] = $this->language->get('error_shipping');
		}

		if (!$json) {
			if ($country_info) {
				$country = $country_info['name'];
				$iso_code_2 = $country_info['iso_code_2'];
				$iso_code_3 = $country_info['iso_code_3'];
				$address_format = $country_info['address_format'];
			} else {
				$country = '';
				$iso_code_2 = '';
				$iso_code_3 = '';
				$address_format = '';
			}

			$this->load->model('localisation/zone');

			$zone_info = $this->model_localisation_zone->getZone($this->request->post['shipping_zone_id']);

			if ($zone_info) {
				$zone = $zone_info['name'];
				$zone_code = $zone_info['code'];
			} else {
				$zone = '';
				$zone_code = '';
			}

			$this->session->data['shipping_address'] = [
				'address_id'     => $this->request->post['shipping_address_id'],
				'firstname'      => $this->request->post['shipping_firstname'],
				'lastname'       => $this->request->post['shipping_lastname'],
				'company'        => $this->request->post['shipping_company'],
				'address_1'      => $this->request->post['shipping_address_1'],
				'address_2'      => $this->request->post['shipping_address_2'],
				'postcode'       => $this->request->post['shipping_postcode'],
				'city'           => $this->request->post['shipping_city'],
				'zone_id'        => $this->request->post['shipping_zone_id'],
				'zone'           => $zone,
				'zone_code'      => $zone_code,
				'country_id'     => (int)$this->request->post['shipping_country_id'],
				'country'        => $country,
				'iso_code_2'     => $iso_code_2,
				'iso_code_3'     => $iso_code_3,
				'address_format' => $address_format,
				'custom_field'   => $this->request->post['shipping_custom_field'] ?? []
			];

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
