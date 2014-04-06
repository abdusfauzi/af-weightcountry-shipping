<?php
/**
 * Plugin Name: AF Weight/Country Shipping
 * Plugin URI: http://www.af-plugins.com/af-plugins/af-weightcountry-shipping
 * Description: WooCommerce extension for Weight/Country shipping. Original plugin from https://wordpress.org/plugins/oik-weightcountry-shipping/
 * Version: 1.0.5
 * Author: Abdus Fauzi
 * Author URI: http://abdusfauzi.com
 * License: GPL2

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    The license for this software can likely be found here:
    http://www.gnu.org/licenses/gpl-2.0.html
*/

add_action( 'plugins_loaded', 'init_af_shipping', 0 );

function init_af_shipping() {

	if ( ! class_exists( 'WC_Shipping_Method' ) ) return;

	class af_Shipping extends WC_Shipping_Method {

		function __construct() {
			$this->id           = 'af_shipping'; // Retain the original code rather than use  'af_shipping';
			$this->method_title = __( 'Weight/Country', 'woocommerce' );

			$this->admin_page_heading     = __( 'Weight and country based shipping', 'woocommerce' );
			$this->admin_page_description = __( 'Define shipping by weight and country', 'woocommerce' );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_admin_options' ) );

			$this->init();
            $this->display_state_groups();
			$this->display_country_groups();
		}

		function init() {
			$this->init_form_fields();
			$this->init_settings();

			$this->enabled          = $this->get_option('enabled');
			$this->title            = $this->get_option('title');
            $this->availability     = 'specific';
            $this->state_group_no   = $this->settings['state_group_no'];
			$this->country_group_no = $this->get_option('country_group_no');
            $this->states           = $this->settings['states'];
            $this->countries 	   = $this->get_option('countries');
			$this->type             = 'order';
			$this->tax_status       = $this->get_option('tax_status');
			$this->fee              = $this->get_option('fee');
			$this->options		  = isset( $this->settings['options'] ) ? $this->settings['options'] : '';
			$this->options		  = (array) explode( "\n", $this->options );

            if (empty($this->countries)) {
                $this->availability = $this->settings['availability'] = 'all';
            }
		}

		function init_form_fields() {

            $woocommerce = function_exists('WC') ? WC() : $GLOBALS['woocommerce'];

			$this->form_fields = array(
				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable this shipping method', 'woocommerce' ),
					'default'     => 'no',
				),
				'title'           => array(
					'title'       => __( 'Method Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Regular Shipping', 'woocommerce' ),
				),
				'tax_status'  => array(
					'title'       => __( 'Tax Status', 'woocommerce' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'taxable',
					'options'     => array(
						'taxable' => __( 'Taxable', 'woocommerce' ),
						'none'    => __( 'None', 'woocommerce' ),
					),
				),
				'fee'         => array(
					'title'       => __( 'Handling Fee', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Fee excluding tax, e.g. 3.50. Leave blank to disable.', 'woocommerce' ),
					'default'     => '',
				),
				'options'     => array(
					'title'       => __( 'Shipping Rates', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Set your weight based rates in ' . get_option( 'woocommerce_weight_unit' ) . ' for country groups (one per line). Example: <code>Max weight|Cost|country group number</code>. Example: <code>10|6.95|3</code>. For decimal, use a dot not a comma.', 'woocommerce' ),
					'default'     => '',
				),
                'state_group_no' => array(
                    'title' 	  => __( 'Number of states groups', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'Number of groups of states sharing delivery rates (hit "Save changes" button after you have changed this setting).' ),
                    'default' 	=> '3',
                ),
				'country_group_no' => array(
					'title' 	  => __( 'Number of country groups', 'woocommerce' ),
					'type' 	   => 'text',
					'description' => __( 'Number of groups of countries sharing delivery rates (hit "Save changes" button after you have changed this setting).' ),
					'default' 	=> '3',
				),

                // Didn't re-add sync-countries option since this has updated in WooCommerce 2.1 Herb 2014/03/27
                // @TODO Need to use network_admin_url( "/wp-admin/admin.php?page=woocommerce_settings&tab=general"
                // 'sync_countries' => array(
                //    'title' 		=> __( 'Add countries to allowed', 'woocommerce' ),
                //    'type' 			=> 'checkbox',
                //    'label' 		=> __( 'Countries added to country groups will be automatically added to the Allowed Countries in the General settings tab.
                //                      This makes sure countries defined in country groups are visible on checkout.
                //                      Note: Deleting a country from the country group will not delete the country from Allowed Countries.', 'woocommerce' ),
                //    'default' 		=> 'no',
                // ),
			);
		}

        /*
        * Displays state group selects in shipping method's options
        */
        function display_state_groups() {

            $woocommerce = function_exists('WC') ? WC() : $GLOBALS['woocommerce'];

            $number = $this->state_group_no;

            $base_country = $woocommerce->countries->get_base_country();

            for($counter = 1; $number >= $counter; $counter++) {

                $this->form_fields['S'.$counter] =  array(
                    'title'     => sprintf(__( 'State Group %s', 'woocommerce' ), $counter),
                    'type'      => 'multiselect',
                    'class'     => 'chosen_select',
                    'css'       => 'width: 450px;',
                    'default'   => '',
                    'options'   => $woocommerce->countries->get_states( $base_country )
                    );
            }
        }

        /*
        * Displays country group selects in shipping method's options
        */
        function display_country_groups() {

    		$woocommerce = function_exists('WC') ? WC() : $GLOBALS['woocommerce'];

            $shippingCountries = method_exists($woocommerce->countries, 'get_shipping_countries')
                                        ? $woocommerce->countries->get_shipping_countries()
                                        : $woocommerce->countries->countries;

            $number = $this->country_group_no;

            for($counter = 1; $number >= $counter; $counter++) {

                $this->form_fields['C'.$counter] =  array(
                        'title'     => sprintf(__( 'Country Group %s', 'woocommerce' ), $counter),
                        'type'      => 'multiselect',
                        'class'     => 'chosen_select',
                        'css'       => 'width: 450px;',
                        'default'   => '',
                        'options'   => $shippingCountries
                );
            }
        }

    	function calculate_shipping( $package = array() ) {
    		global $woocommerce;

            $group      = $this->get_countrygroup($package);
            $rates      = $this->get_rates_by_countrygroup( $group );
            $weight     = $woocommerce->cart->cart_contents_weight;
            $final_rate = $this->pick_smallest_rate($rates, $weight);

            if ($group[0] == 'S') {
                $destination = $package['destination']['state'];
            } else {
                $destination = $package['destination']['country'];
            }

            if($final_rate === false) return false;

            $taxable    = ($this->tax_status == 'taxable') ? true : false;

            if($this->fee > 0 && $destination) $final_rate = $final_rate + $this->fee;

                $rate = array(
                'id'        => $this->id,
                'label'     => $this->title,
                'cost'      => $final_rate,
                'taxes'     => '',
                'calc_tax'  => 'per_order'
                );

            $this->add_rate( $rate );
        }


        /*
        * Retrieves the number of state/country group for state selected by user on checkout
        */
        function get_countrygroup($package = array()) {
            $country_group = null;
            $counter = 1;

            while(is_array($this->settings['S'.$counter])) {
                if (in_array($package['destination']['state'], $this->settings['S'.$counter])) $country_group = 'S'.$counter;

                $counter++;
            }

            if (isset($country_group)) return $country_group;

            $counter = 1;

            while(is_array($this->settings['C'.$counter])) {
                if (in_array($package['destination']['country'], $this->settings['C'.$counter])) $country_group = 'C'.$counter;

                $counter++;
            }

            return $country_group;
        }


        /*
        * Retrieves all rates available for selected country group
        */
        function get_rates_by_countrygroup($country_group = null) {
            $countrygroup_rate = null;

            $rates = array();
            if ( sizeof( $this->options ) > 0) foreach ( $this->options as $option => $value ) {

                $rate = preg_split( '~\s*\|\s*~', trim( $value ) );

                if ( sizeof( $rate ) !== 3 )  {
                    continue;
                } else {
                    $rates[] = $rate;

                }
            }

            foreach($rates as $key) {
                $groups = explode(",", $key[2]);
                foreach($groups as $group_key)
                {
                    if ($group_key == $country_group) {
                        $countrygroup_rate[] = $key;
                    }
                }
            }
            return $countrygroup_rate;
        }

        /*
        * Picks the right rate from available rates based on cart weight
        */
        function pick_smallest_rate($rates,$weight) {

            if ($weight == 0) return 0; // no shipping for cart without weight

            if ( sizeof($rates) > 0 ) {
                foreach($rates as $key => $value) {
                    if($weight <= $value[0]) {
                        $postage[] = $value[1];
                    }
                    $postage_all_rates[] = $value[1];
                }
            }

            if ( sizeof( $postage ) > 0) {
                return min ($postage );
            } else {
                if (sizeof( $postage_all_rates) > 0 ) return max( $postage_all_rates );
            }

            return false;
        }

        function etz($etz) {

            if(empty($etz) || !is_numeric($etz)) {
                return 0.00;
            }
        }

        /**
         *   For help and how to use go <a href="http://www.andyswebdesign.ie/blog/free-woocommerce-weight-and-country-based-shipping-extension-plugin/" target="_blank">here</a>', 'woocommerce');
         */
        public function admin_options() {
            ?>
        	<h3><?php _e('Weight and Country based shipping', 'woocommerce'); ?></h3>
        	<p><?php _e('Lets you calculate shipping based on Country and weight of the cart.', 'woocommerce' ); ?>
            <br /><?php _e( 'Lets you set unlimited weight bands on per country basis and group countries that share same delivery cost/bands.', 'woocommerce' ); ?>
            </p>
        	<table class="form-table">
        	<?php
        		// Generate the HTML for the settings form.
        		$this->generate_settings_html();
        	?>
    		</table><!--/.form-table-->
        	<?php
        }

    } // end af_Shipping
}


/**
 * Implement 'woocommerce_shipping_methods' filter for af-weightcountry-shipping
 */
function add_af_shipping( $methods ) {
	$methods[] = 'af_Shipping';
	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_af_shipping' );
