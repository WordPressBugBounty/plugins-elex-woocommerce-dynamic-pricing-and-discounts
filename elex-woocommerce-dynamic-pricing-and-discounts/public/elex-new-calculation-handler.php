<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
require_once 'elex-rules-validator.php';
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of new_calculation_handler
 *
 * @author Akshay
 */
class Elex_NewCalculationHandler
{

	public $debug_mode = false;

	public function __construct()
	{
		$dummy_settings['product_rules_on_off']          = 'enable';
		$dummy_settings['combinational_rules_on_off']    = 'enable';
		$dummy_settings['category_rules_on_off']         = 'enable';
		$dummy_settings['cart_rules_on_off']             = 'enable';
		$dummy_settings['buy_and_get_free_rules_on_off'] = 'enable';
		$dummy_settings['BOGO_category_rules_on_off']    = 'enable';
		$dummy_settings['bogo_tag_rules_on_off']         = 'enable';
		$dummy_settings['price_table_on_off']            = 'enable';

		$dummy_settings['auto_add_free_product_on_off'] = '';
		$dummy_settings['pricing_table_qnty_shrtcode']  = 'nos.';
		$dummy_settings['show_discount_in_line_item']   = 'yes';
		$dummy_settings['pricing_table_position']       = 'woocommerce_before_add_to_cart_button';
		$dummy_settings['show_on_sale']                 = 'no';
		$dummy_settings['execution_order']              = array(
			'product_rules',
			'category_rules'

		);
		global $woocommerce;
		global $xa_dp_rules;
		global $xa_dp_setting;
		global $xa_cart_quantities;
		global $xa_cart_weight;
		global $xa_cart_price;
		global $xa_cart_categories;
		global $xa_cart_tags;
		global $xa_hooks;
		global $xa_cart_categories_items;
		global $xa_cart_categories_units;
		global $xa_cart_tags_items;
		global $xa_cart_tags_units;
		global $current_user;
		global $customer;
		global $xa_variation_parentid;
		global $xa_first_match_rule_executed;


		if (WC() && WC()->customer) {
			$current_user = wp_get_current_user();
			$customer     = new WC_Customer($current_user->ID);
		}

		$xa_first_match_rule_executed = false;
		$xa_variation_parentid        = array();
		$xa_cart_quantities           = array();
		$xa_cart_weight               = array();
		$xa_cart_price                = array();
		$xa_cart_categories           = array();
		$xa_cart_categories_items     = array();
		$xa_cart_categories_units     = array();
		$xa_cart_tags                 = array();
		$xa_cart_tags_items           = array();
		$xa_cart_tags_units           = array();
		$xa_dp_rules                  = get_option('xa_dp_rules', array());
		$xa_dp_setting                = get_option('xa_dynamic_pricing_setting', $dummy_settings);
		if (!is_admin() && !defined('DOING_CRON') && $woocommerce->cart) {

			////Removing Free Products Which are Automatically Added by Dynamic Pricing
			//            $cart_item_data = $woocommerce->cart->get_cart();
			//            foreach ($cart_item_data as $key => $hash) {
			//                if (strpos($key, 'FreeForRule') !== false) {            //remove free products
			//                    $woocommerce->cart->remove_cart_item($key);
			//                    continue;
			//                }
			//            }
			//////////////////////////////////////
			foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) {
				$product = $values['data'];
				if (strpos($cart_item_key, 'FreeForRule') !== false) {
					continue;
				}
				$id                        = $product->get_id();
				$xa_cart_quantities[$id] =  !empty($values['quantity']) ? $values['quantity'] : 0;
			}
			if ($xa_hooks) {
				//$xa_cart_quantities = $woocommerce->cart->get_cart_item_quantities();
				remove_filter($xa_hooks['woocommerce_get_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22);
			}
			remove_filter('woocommerce_product_variation_get_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22);
			foreach ($xa_cart_quantities as $_pid => $_qnty) {
				$prod                  = wc_get_product($_pid);
				$xa_cart_weight[$_pid] = $prod->get_weight();
				$xa_cart_price[$_pid]  = $prod->get_price('edit');
				if ($prod->is_type('variation')) {
					$parent_id                 = elex_dp_is_wc_version_gt_eql('2.7') ? $prod->get_parent_id() : $prod->parent->id;
					$parent_product            = wc_get_product($parent_id);
					if($parent_product){
						$xa_cart_categories[$_pid] = elex_dp_is_wc_version_gt_eql('2.7') ? $parent_product->get_category_ids() : elex_dp_get_category_ids($parent_product);
						$xa_cart_tags[$_pid]       = xa_get_tag_ids($_pid);
					}else{
						$xa_cart_categories[$_pid] = elex_dp_is_wc_version_gt_eql('2.7') ? $prod->get_category_ids() : elex_dp_get_category_ids($prod);
						$xa_cart_tags[$_pid]       = xa_get_tag_ids($prod);
					}
					
				} else {
					$xa_cart_categories[$_pid] = elex_dp_is_wc_version_gt_eql('2.7') ? $prod->get_category_ids() : elex_dp_get_category_ids($prod);
					$xa_cart_tags[$_pid]       = xa_get_tag_ids($prod);
				}
				foreach ($xa_cart_categories[$_pid] as $_cid) {
					$xa_cart_categories_items[$_cid] = isset($xa_cart_categories_items[$_cid]) ? ($xa_cart_categories_items[$_cid] + 1) : 1;
					$xa_cart_categories_units[$_cid] = isset($xa_cart_categories_units[$_cid]) ? ($xa_cart_categories_units[$_cid] + $_qnty) : $_qnty;
				}
				foreach ($xa_cart_tags[$_pid] as $_tid) {
					$xa_cart_tags_items[$_tid] = isset($xa_cart_tags_items[$_tid]) ? ($xa_cart_tags_items[$_tid] + 1) : 1;
					$xa_cart_tags_units[$_tid] = isset($xa_cart_tags_units[$_tid]) ? ($xa_cart_tags_units[$_tid] + $_qnty) : $_qnty;
				}
			}
			if ($xa_hooks) {
				add_filter($xa_hooks['woocommerce_get_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);         // update sale price on product page
			}
			add_filter('woocommerce_product_variation_get_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
		}
		/*Fix Start: This code is to solve ->empty array of categories problem from wpml plugin when cart language is changed .  */
		/*  this will reload the page only one after customer changes the site language so as the wpml plugin gives translated ids  */
		if (empty($xa_cart_categories_items)   && class_exists('SitePress') && is_cart()) {
			header('Refresh:0');
			exit;
		}
		/*Fix End     */
	}

	/**
	 * Finds valid rules for this product and return discounted price based on (all rules,first match,best discount)
	 *
	 * @param float $old_price  (price over which discount needs to be applied)
	 * @param wc_product $product (object of product for which we need discounted price)
	 * @param integer $pid (id of product)
	 *
	 * @return $discounted_price
	 */
	public function elex_dp_getDiscountedPriceForProduct($old_price = '', $product = null, $pid = null)
	{
		//error_log($product->get_id()." filter=".current_filter());
		global $elex_dp_cached_prices;
		if (!$elex_dp_cached_prices) {
			$elex_dp_cached_prices = array();
		}

		if (!isset($elex_dp_cached_prices[$product->get_id()])) {
			$elex_dp_cached_prices[$product->get_id()] = array();
		}

		// if (isset($elex_dp_cached_prices[$product->get_id()][current_filter()])) {
		// 	return $elex_dp_cached_prices[$product->get_id()][current_filter()];
		// }
		// print("<pre>".print_r(debug_backtrace(),true)."</pre>");

		global $xa_dp_setting;
		$xa_add_on_true  = true;
		$xa_add_on_false = false;
		if (is_plugin_active('woocommerce-product-addons/woocommerce-product-addons.php')) {
			if ($xa_dp_setting['xa_product_add_on_option'] == 'disable') {
				$xa_add_on_true  = false;
				$xa_add_on_false = true;
			}
		}

		//Code added to make discount apply in custom woocommerce pages.
		$product_filters = array('woocommerce_product_get_price', 'woocommerce_product_variation_get_price', 'woocommerce_product_variation_get_sale_price', 'woocommerce_product_get_sale_price');

		if (apply_filters('xa_give_discount_on_addon_prices', true) == $xa_add_on_false && did_action('woocommerce_before_calculate_totals') || doing_action('woocommerce_before_calculate_totals')) {   // added this code for compatiblity with woocommerce product addons
			// $elex_dp_cached_prices[$product->get_id()][current_filter()] = $old_price;
			return $old_price;
		}
		if (!is_shop() && !is_product() && !is_product_tag() &&  !is_product_category() && !did_action('woocommerce_before_calculate_totals') && apply_filters('xa_give_discount_on_addon_prices', true) == $xa_add_on_true && !in_array(current_filter(), $product_filters)) {   // added this code for compatiblity with woocommerce product addons
			// $elex_dp_cached_prices[$product->get_id()][current_filter()] = $old_price;
			return $old_price;
		}
		global $xa_hooks, $xa_common_flat_discount, $xa_cart_price, $executed_pids;
		remove_filter($xa_hooks['woocommerce_get_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22);
		remove_filter($xa_hooks['woocommerce_get_sale_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22);    // update sale price on product page
		remove_filter('woocommerce_product_variation_get_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22);
		remove_filter('woocommerce_product_variation_get_sale_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22);    // update sale price on product page

		$regular_price = $product->get_regular_price();

		if (empty($pid)) {
			$pid = elex_dp_get_pid($product);
		}

		if ($pid == false) {
			add_filter($xa_hooks['woocommerce_get_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);         // update sale price on product page
			add_filter($xa_hooks['woocommerce_get_sale_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
			add_filter('woocommerce_product_variation_get_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
			add_filter('woocommerce_product_variation_get_sale_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
			$elex_dp_cached_prices[$product->get_id()][current_filter()] = $old_price;
			return $old_price;
		}
		if (((current_filter() == $xa_hooks['woocommerce_get_sale_price_hook_name']) || (current_filter() == 'woocommerce_product_variation_get_sale_price')) && empty($old_price)) {  // if sale price is empty then old price is regular_price
			$old_price = $regular_price;
		}
		$discounted_price = $old_price;
		$weight           = $product->get_weight();
		if (!empty($old_price) && (!empty($product) || !empty($pid))) {

			global $xa_cart_quantities;
			$parent_id = $pid;
			if ($product->get_type() == 'variation') {
				$parent_id = elex_dp_is_wc_version_gt_eql('2.7') ? $product->get_parent_id() : $product->parent->id;
			}
			if (isset($xa_cart_quantities[$pid]) || isset($xa_cart_quantities[$parent_id])) {
				$current_quantity = isset($xa_cart_quantities[$pid]) ? $xa_cart_quantities[$pid] : $xa_cart_quantities[$parent_id];
			} else {
				$current_quantity = 0;
			}
			if ($current_quantity == 0  && class_exists('SitePress')) {
				global $sitepress;
				$trid    = $sitepress->get_element_trid($pid);
				$trans   = $sitepress->get_element_translations($trid);
				$all_ids = array();
				foreach ($trans as $lan) {
					$all_ids[] = $lan->element_id;
				}
				foreach ($all_ids as $_pid) {
					if (!empty($xa_cart_quantities[$_pid])) {
						$current_quantity = $xa_cart_quantities[$_pid];
						break;
					}
				}
			}
			if (is_shop() || is_product_category() || is_product() || is_product_tag()) {
				$current_quantity++;
			}
			$objRulesValidator = new Elex_RulesValidator();

			$valid_rules = $objRulesValidator->elex_dp_getValidRulesForProduct($product, $pid, $current_quantity, $discounted_price, $weight);

			if ($this->debug_mode) {
				error_log(str_repeat('-', 380));
				error_log('Valid Rules for Pid=' . $pid . ' and qnty=' . $current_quantity . ' Valid Rules= ' . print_r($valid_rules, true));
			}
			$mode = !empty($xa_dp_setting['mode']) ? $xa_dp_setting['mode'] : 'first_match';
			// if (!empty($executed_pids[$pid]) && $mode == 'best_discount' && !$product->is_type('variation')) {
			// 	$product->set_price($executed_pids[$pid]);
			// 	add_filter($xa_hooks['woocommerce_get_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);         // update sale price on product page
			// 	add_filter($xa_hooks['woocommerce_get_sale_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
			// 	add_filter('woocommerce_product_variation_get_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
			// 	add_filter('woocommerce_product_variation_get_sale_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
			// 	$elex_dp_cached_prices[$product->get_id()][current_filter()] = $executed_pids[$pid];
			// 	return $executed_pids[$pid];
			// }
			if (is_array($valid_rules) && $valid_rules) {
				$valid_flat_discount = [];
				foreach ($valid_rules as $rule_type_colon_rule_no => $rule) {
					//this section supports repeat execution for product rules
					if (isset($rule['repeat_rule']) && $rule['repeat_rule'] == 'yes' && !empty($rule['max']) && !empty($rule['min'])) {
						if ($rule['rule_type'] == 'product_rules') {
							if ($product->get_type() == 'variation') {
								$pparent_id = elex_dp_is_wc_version_gt_eql('2.7') ? $product->get_parent_id() : $product->parent->id;

								if (is_array($rule['product_id']) && in_array($pparent_id, $rule['product_id'])) {
									$pparent_product  = new WC_Product_Variable($pparent_id);
									$variations       = $pparent_product->get_children();
									$current_quantity = 0;
									foreach ($variations as $value) {
										if (isset($xa_cart_quantities[$value])) {
											$current_quantity += $xa_cart_quantities[$value];
										}
									}
									if (isset($xa_cart_quantities[$pparent_id])) {
										$current_quantity += $xa_cart_quantities[$value];
									}
									if (is_shop() || is_product_category() || is_product() || is_product_tag()) {
										$current_quantity++;
									}
								}
							}
						}
						$times       = intval($current_quantity / $rule['max']);
						$total_price = 0;
						$repeat_qnty = (float) $rule['max'];
						if ($this->debug_mode) {
							error_log(str_repeat('>', 208));
							error_log('Executing Rule(Repeat=True):' . $rule_type_colon_rule_no);
						}
						if (!empty($rule['discount_type']) && $rule['discount_type'] == 'Flat Discount') {
							$xa_common_flat_discount[$rule['rule_type'] . ':' . $rule['rule_no'] . ':' . $pid] = floatval($rule['value']) * floatval($times);
							if (!empty($rule['adjustment'])) {
								$adjusted_qnty    = !empty($objRulesValidator->rule_based_quantity[$rule['rule_type'] . ':' . $rule['rule_no']]) ? $objRulesValidator->rule_based_quantity[$rule['rule_type'] . ':' . $rule['rule_no']] : $current_quantity;
								$discounted_price = $discounted_price + ((float) $rule['adjustment'] / $adjusted_qnty);
							}
						} else {
							$object_hash = spl_object_hash($product);
							$object_hash = $object_hash . $pid;
							$r_price     = $objRulesValidator->elex_dp_execute_rule($discounted_price, $rule_type_colon_rule_no, $rule, $repeat_qnty, $pid, $object_hash);
							//error_log("$total_price = $r_price * $times * $repeat_qnty");
							$total_price    = $r_price * $times * $repeat_qnty;
							$remaining_qnty = $current_quantity - ($times * $repeat_qnty);
							if ($remaining_qnty > 0) {
								$price = isset($xa_cart_price[$pid]) ? $xa_cart_price[$pid] : 0;
								$total_price = $total_price + ($remaining_qnty * $price);
							}
							$discounted_price = $total_price / $current_quantity;
						}
					} else {
						if ($this->debug_mode) {
							error_log(str_repeat('>', 208));
							error_log('Executing Rule:' . $rule_type_colon_rule_no);
						}
						//fix for best discount mode flat discount is not getting calculated]

						// if (!empty($rule['discount_type']) && $rule['discount_type'] == 'Flat Discount') { // removed variation condition because variations were getting executed like non flat discounts
						// 	if ($rule['rule_type'] == 'product_rules') {
						// 		$xa_common_flat_discount[$rule['rule_type'] . ':' . $rule['rule_no'] . ':' . $pid] = floatval($rule['value']);
						// 	} elseif ($rule['rule_type'] == 'category_rules') {
						// 		$cid = !empty($rule['selected_cids']) ? current($rule['selected_cids']) : '';
						// 		$xa_common_flat_discount[$rule['rule_type'] . ':' . $rule['rule_no'] . ':' . $cid] = floatval($rule['value']);
						// 	} else {
						// 		$xa_common_flat_discount[$rule['rule_type'] . ':' . $rule['rule_no']] = floatval($rule['value']);
						// 	}
						// 	if (!empty($rule['adjustment'])) {
						// 		$adjusted_qnty    = !empty($objRulesValidator->rule_based_quantity[$rule['rule_type'] . ':' . $rule['rule_no']]) ? $objRulesValidator->rule_based_quantity[$rule['rule_type'] . ':' . $rule['rule_no']] : $current_quantity;
						// 		$discounted_price = $discounted_price + ((float) $rule['adjustment'] / $adjusted_qnty);
						// 	}
						// } else {

							$object_hash      = spl_object_hash($product);
							$object_hash      = $object_hash . $pid;
							$discounted_price = $objRulesValidator->elex_dp_execute_rule($discounted_price, $rule_type_colon_rule_no, $rule, $current_quantity, $pid, $object_hash);
						}
					// }
					if ($rule['rule_type'] != 'BOGO_category_rules' && $rule['rule_type'] != 'buy_get_free_rules' && $rule['rule_type'] != 'bogo_tag_rules') {
						$product->set_price($discounted_price);
					}

					//Calculating the valid flat discounts.
					$key = $rule['rule_type'] . ':' . $rule['rule_no'] . ':' . $pid;
					if($xa_common_flat_discount && array_key_exists($key, $xa_common_flat_discount)){
						$valid_flat_discount[$key] = $key;
					}
				}
				if($xa_common_flat_discount){
					foreach($xa_common_flat_discount as $key => $value){
						$key_parts = explode(':', $key);
						$current_pid = end($key_parts);
						if(!array_key_exists($key, $valid_flat_discount) && ($current_pid == $pid)){
							unset($xa_common_flat_discount[$key]);
						}
					}
				}
			} else {
				foreach ($xa_common_flat_discount as $key => $value) {
					$key_parts = explode(':', $key);
					$current_pid = end($key_parts);
					if ($current_pid == $pid) {
						unset($xa_common_flat_discount[$key]);
					}
				}
			}
		}
		$elex_dp_cached_prices[$product->get_id()][current_filter()] = $discounted_price;
		add_filter($xa_hooks['woocommerce_get_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);         // update sale price on product page
		add_filter($xa_hooks['woocommerce_get_sale_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
		add_filter('woocommerce_product_variation_get_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
		add_filter('woocommerce_product_variation_get_sale_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
		if (((current_filter() == $xa_hooks['woocommerce_get_sale_price_hook_name']) || (current_filter() == 'woocommerce_product_variation_get_sale_price')) && ($regular_price == $discounted_price)) {  // if sale price is empty then old price is regular_price
			return '';
		}
		return $discounted_price;
	}

	public function elex_dp_getDiscountedPriceHTML($price, $product)
	{
		// hooked to get_price_html filter
		if ($product->is_type('simple') || $product->is_type('variation')) {
			return $this->elex_dp_getDiscountedPriceHTML_for_simple_product($price, $product);
		} elseif ($product->is_type('variable')) {
			return $this->elex_dp_getDiscountedPriceHTML_for_variable_product($price, $product);
		} elseif ($product->is_type('grouped')) {
			return $this->elex_dp_getDiscountedPriceHTML_for_group_product($price, $product);
		}
		return $price;
	}

	public function elex_dp_getDiscountedPriceHTML_for_simple_product($price, $product)
	{
		// hooked to get_price_html filter
		return $price;
	}

	public function elex_dp_getDiscountedPriceHTML_for_group_product($price, $product)
	{
		// hooked to get_price_html filter
		$tax_display_mode = get_option('woocommerce_tax_display_shop');
		$child_prices     = array();

		foreach ($product->get_children() as $child_id) {
			$child = wc_get_product($child_id);
			//$child_prices[] = 'incl' === $tax_display_mode ? wc_get_price_including_tax($child) : wc_get_price_excluding_tax($child);
			if($child) {
				if ($child->is_type('variable')) {
					$prices = $child->get_variation_prices(true);

					if (empty($prices['price'])) {
						return '';
					}
					foreach ($prices['price'] as $pid => $old_price) {
						$prices['price'][$pid] = $this->elex_dp_getDiscountedPriceForProduct($old_price, wc_get_product($pid), $pid);
					}
					asort($prices['price']);
					$min_price      = current($prices['price']);
					$child_prices[] = $min_price;
				} else {
					$child_prices[] = 'incl' === $tax_display_mode ? wc_get_price_including_tax($child) : wc_get_price_excluding_tax($child);
				}
			}
		}
		if (!empty($child_prices)) {
			$min_price = min($child_prices);
			$max_price = max($child_prices);
		} else {
			$min_price = '';
			$max_price = '';
		}

		if ('' !== $min_price) {
			$price   = $min_price !== $max_price ? sprintf(_x('%1$s&ndash;%2$s', 'Price range: from-to', 'woocommerce'), wc_price($min_price), wc_price($max_price)) : wc_price($min_price);
			$is_free = (0 == $min_price && 0 == $max_price);

			if ($is_free) {
				$price = apply_filters('woocommerce_grouped_free_price_html', __('Free!', 'woocommerce'), $product);
			} else {
				$price = apply_filters('woocommerce_grouped_price_html', $price . $product->get_price_suffix(), $product, $child_prices);
			}
		} else {
			$price = apply_filters('woocommerce_grouped_empty_price_html', '', $product);
		}

		return $price;
	}

	public function elex_dp_getDiscountedPriceHTML_for_variable_product($price, $product)
	{
		// hooked to get_price_html filter
		$prices               = array();
		$childrens            = array();
		$available_variations = $product->get_available_variations();

		foreach ($available_variations as $variation) {
			$childrens[] = $variation['variation_id'];
		}
		$tax_display_mode = get_option('woocommerce_tax_display_shop');
		foreach ($childrens as $_pid) {
			$pd = wc_get_product($_pid);
			if (!empty($pd)) {
				$prices['price'][$_pid]         = 'incl' === $tax_display_mode ? wc_get_price_including_tax($pd) : wc_get_price_excluding_tax($pd);
				$prices['regular_price'][$_pid] = 'incl' === $tax_display_mode ? wc_get_price_including_tax($pd, array('qty'   => '1', 'price' => $pd->get_regular_price())) : wc_get_price_excluding_tax($pd, array('qty'   => '1', 'price' => $pd->get_regular_price()));
				if ($prices['price'][$_pid] < $prices['regular_price'][$_pid]) {
					$prices['sale_price'][$_pid] = $prices['price'][$_pid];
				}
			}
		}
		if (empty($prices['price'])) {
			return '';
		}
		//        foreach ($prices['price'] as $pid => $old_price) {
		//            $prices['price'][$pid] = $this->elex_dp_getDiscountedPriceForProduct($old_price, wc_get_product($pid), $pid);
		//        }
		asort($prices['price']);
		$min_price     = current($prices['price']);
		$max_price     = end($prices['price']);
		$regular_price = current($prices['regular_price']);
		if ($min_price !== $max_price) {
			$price = wc_format_price_range($min_price, $max_price) . $product->get_price_suffix();
		} elseif ($regular_price != $max_price) {
			$price = wc_format_sale_price($regular_price, $max_price) . $product->get_price_suffix();
		} else {
			$price = wc_price($min_price) . $product->get_price_suffix();
		}
		return apply_filters('eha_variable_sale_price_html', $price, $min_price, $max_price, $regular_price, 0);
	}
}
