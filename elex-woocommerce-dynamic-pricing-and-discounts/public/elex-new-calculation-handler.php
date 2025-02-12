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
class Elex_NewCalculationHandler {


	public $debug_mode = false;

	public function __construct() {
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
					if ($parent_product) {
						$xa_cart_categories[$_pid] = elex_dp_is_wc_version_gt_eql('2.7') ? $parent_product->get_category_ids() : elex_dp_get_category_ids($parent_product);
						$xa_cart_tags[$_pid]       = xa_get_tag_ids($_pid);
					} else {
						$xa_cart_categories[$_pid] = elex_dp_is_wc_version_gt_eql('2.7') ? $prod->get_category_ids() : elex_dp_get_category_ids($prod);
						$xa_cart_tags[$_pid]       = xa_get_tag_ids($prod);
					}
					
				} else {
					$xa_cart_categories[$_pid] = elex_dp_is_wc_version_gt_eql('2.7') ? $prod->get_category_ids() : elex_dp_get_category_ids($prod);
					$xa_cart_tags[$_pid]       = xa_get_tag_ids($prod);
				}
				foreach ($xa_cart_categories[$_pid] as $_cid) {
					$xa_cart_categories_items[$_cid] = isset($xa_cart_categories_items[$_cid]) ? ( $xa_cart_categories_items[$_cid] + 1 ) : 1;
					$xa_cart_categories_units[$_cid] = isset($xa_cart_categories_units[$_cid]) ? ( $xa_cart_categories_units[$_cid] + $_qnty ) : $_qnty;
				}
				foreach ($xa_cart_tags[$_pid] as $_tid) {
					$xa_cart_tags_items[$_tid] = isset($xa_cart_tags_items[$_tid]) ? ( $xa_cart_tags_items[$_tid] + 1 ) : 1;
					$xa_cart_tags_units[$_tid] = isset($xa_cart_tags_units[$_tid]) ? ( $xa_cart_tags_units[$_tid] + $_qnty ) : $_qnty;
				}
			}
			if ($xa_hooks) {
				add_filter($xa_hooks['woocommerce_get_price_hook_name'], array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);         // update sale price on product page
			}
			add_filter('woocommerce_product_variation_get_price', array($this, 'elex_dp_getDiscountedPriceForProduct'), 22, 2);    // update sale price on product page
		}
		add_action('save_post_product', array($this,'elex_dp_clear_product_transient'), 10, 2);
		add_action('woocommerce_update_product', array($this,'elex_dp_clear_product_transient'), 10, 1);
		add_action('woocommerce_after_cart_item_quantity_update', array($this,'elex_dp_clear_cart_transient'), 10, 3 );

		/*Fix Start: This code is to solve ->empty array of categories problem from wpml plugin when cart language is changed .  */
		/*  this will reload the page only one after customer changes the site language so as the wpml plugin gives translated ids  */
		if (empty($xa_cart_categories_items)   && class_exists('SitePress') && is_cart()) {
			header('Refresh:0');
			exit;
		}
		/*Fix End     */
	}

	public function elex_dp_clear_cart_transient( $cart_item_key, $quantity, $old_quantity) {
		$cart = WC()->cart->get_cart();
		$users = wp_get_current_user();
		$user_role = isset($users->roles[0]) ? $users->roles[0] : 'guest';
	
		foreach ($cart as $cart_item) {
			$product_id = isset( $cart_item['variation_id'] ) && !empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			delete_transient("elex_dp_product_data_{$product_id}_{$user_role}");
		}
	}
	
	public function elex_dp_clear_product_transient( $post_id, $post = null) {
		global $wpdb;
		if (!$post_id) {
			return;
		}
		$product = wc_get_product($post_id);
		$variation_ids = ( $product && $product->is_type('variable') ) ? $product->get_children() : [];
	
		// Include parent and variation IDs
		$product_ids = array_merge([$post_id], $variation_ids);
	
		foreach ($product_ids as $id) {
			$transient_key_pattern = 'elex_dp_product_data_' . $id . '%';
	
			// Delete transients directly from the database
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
					'_transient_' . $transient_key_pattern,
					'_transient_timeout_' . $transient_key_pattern
				)
			);
		}
	
		// Clear the object cache
		wp_cache_flush();
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
	public function elex_dp_getDiscountedPriceForProduct( $old_price = '', $product = null, $pid = null) {
		static $cached_prices = [];
	   $users = wp_get_current_user();
	   $user_role = isset( $users->roles[0] ) ?  $users->roles[0] : 'guest';


		if (!$product || $old_price === null) {
			return $old_price ;
		}
	
		$product_id = $product->get_id();
		$cached_prices[$product_id] = $cached_prices[$product_id] ?? [];
	
		global $xa_hooks, $xa_cart_quantities, $xa_common_flat_discount;
	
		$apply_discount = apply_filters('xa_give_discount_on_addon_prices', true);
		if (( !is_shop() && !is_product() && !is_product_tag() && !is_product_category() && !did_action('woocommerce_before_calculate_totals') )
			|| ( !$apply_discount && did_action('woocommerce_before_calculate_totals') )
		) {
			return $old_price;
		}
	
		if ( !is_cart() && !is_checkout() && !empty( get_transient('elex_dp_product_data_' . $product_id . '_' . $user_role) ) ) {
			$discounted_price = get_transient('elex_dp_product_data_' . $product_id . '_' . $user_role);
			return $discounted_price;
		}
	
		$this->manageHooks(false);
		$regular_price = $product->get_regular_price();
		$pid = $pid ?: elex_dp_get_pid($product);
	
		if (!$pid) {
			$this->manageHooks(true);
			return $old_price;
		}
	
		if (in_array(current_filter(), [$xa_hooks['woocommerce_get_sale_price_hook_name'], 'woocommerce_product_variation_get_sale_price']) && empty($old_price)) {
			$old_price = $regular_price;
		}
	
		$discounted_price = $old_price;
		$parent_id = ( $product->get_type() == 'variation' ) ? $product->get_parent_id() : $pid;
		$current_quantity = $xa_cart_quantities[$pid] ?? $xa_cart_quantities[$parent_id] ?? 0;

		if (is_shop() || is_product_category() || is_product() || is_product_tag()) {
			$current_quantity++;
		}
	
		$objRulesValidator = new Elex_RulesValidator();
		$valid_rules = $objRulesValidator->elex_dp_getValidRulesForProduct($product, $pid, $current_quantity, $discounted_price, $product->get_weight());
	
		if ($this->debug_mode) {
			error_log("Valid Rules for PID={$pid}, Quantity={$current_quantity}: " . print_r($valid_rules, true));
		}
	
		foreach ($valid_rules ?: [] as $rule_key => $rule) {
			$discounted_price = $objRulesValidator->elex_dp_execute_rule($discounted_price, $rule_key, $rule, $current_quantity, $pid, spl_object_hash($product));
			if (!in_array($rule['rule_type'], ['BOGO_category_rules', 'buy_get_free_rules', 'bogo_tag_rules'])) {
				$product->set_price($discounted_price);
			}
		}
		
		set_transient("elex_dp_product_data_{$product_id}_{$user_role}", $discounted_price);
	
		$this->manageHooks(true);
		return ( $regular_price == $discounted_price ) ? $regular_price : $discounted_price;
	}
   
	  // Helper method to manage hooks
	public function manageHooks( $add) {
		global $xa_hooks;
   
		$action = $add ? 'add_filter' : 'remove_filter';
		$priority = 22;
	   
		$filters = [
		$xa_hooks['woocommerce_get_price_hook_name'],
		$xa_hooks['woocommerce_get_sale_price_hook_name'],
		'woocommerce_product_variation_get_price',
		'woocommerce_product_variation_get_sale_price'
		];
   
		foreach ($filters as $filter) {
			$action($filter, [$this, 'elex_dp_getDiscountedPriceForProduct'], $priority, 2);
		}
	}

	public function elex_dp_getDiscountedPriceHTML( $price, $product) {
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

	public function elex_dp_getDiscountedPriceHTML_for_simple_product( $price, $product) {
		// hooked to get_price_html filter
		return $price;
	}

	public function elex_dp_getDiscountedPriceHTML_for_group_product( $price, $product) {
		// hooked to get_price_html filter
		$tax_display_mode = get_option('woocommerce_tax_display_shop');
		$child_prices     = array();

		foreach ($product->get_children() as $child_id) {
			$child = wc_get_product($child_id);
			//$child_prices[] = 'incl' === $tax_display_mode ? wc_get_price_including_tax($child) : wc_get_price_excluding_tax($child);
			if ($child) {
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
			$is_free = ( 0 == $min_price && 0 == $max_price );

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

	public function elex_dp_getDiscountedPriceHTML_for_variable_product( $price, $product) {
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
