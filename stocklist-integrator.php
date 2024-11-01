<?php
/*
 * Plugin Name: Stocklist Integrator
 * Plugin URI: https://www.hexon.nl/services/stocklist-integrator/
 * Description: Add a stocklist to your website. Works with multiple providers (Autotelex, Hexon, RDC / InMotiv, UCC, VWE)
 * Version: 1.0.0
 * Author: Hexon BV
 * Author URI: https://www.hexon.nl
 * Text Domain: stocklist-integrator
 *
 *  Copyright 2023 Hexon BV
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 2, as
 *  published by the Free Software Foundation.
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  You should have received a copy of the GNU General Public License
 *  long with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if( !defined( 'STOCKLIST_INTEGRATOR_VERSION' ) ) {
	define( 'STOCKLIST_INTEGRATOR_VERSION', '1.0.0' );
}

class StocklistIntegrator {
	static $instance = false;

	static $providers = array(
		4236 => array( 'name' => 'Autotelex', 'domain' => 'https://www.voorraadmodule.nl'),
		1048 => array( 'name' => 'Hexon BV', 'domain' => 'https://www.voorraadmodule.nl'),
		4600 => array( 'name' => 'UCC', 'domain' => 'https://www.ucc-voorraad.nl'),
		1056 => array( 'name' => 'RDC / InMotiv', 'domain' => 'https://webshop.inmotiv.nl'),
		4471 => array( 'name' => 'VWE', 'domain' => 'https://voorraadmodule.vwe-advertentiemanager.nl')
	);

	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'plugin_activation' ));
		register_uninstall_hook( __FILE__, array( $this, 'plugin_uninstall' ));
		add_shortcode( 'stocklist', array( $this, 'shortcode_stocklist' ));
		add_shortcode( 'carousel', array( $this, 'shortcode_carousel' ));
		add_shortcode( 'quicksearch', array( $this, 'shortcode_quicksearch' ));
		add_action( 'admin_menu', array( $this, 'setup_admin_menu' ));
		add_action( 'admin_init', array( $this, 'register_settings' ));
		add_action( 'admin_init', array( $this, 'admin_maybe_redirect' ));
		load_plugin_textdomain( 'stocklist-integrator', false, dirname( plugin_basename( __FILE__ ) ) );
	}

	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function plugin_activation() {
		add_option( 'stocklist_integrator_activation_redirect', true );
	}

	public function plugin_uninstall() {
		delete_option( 'stocklist_integrator_domain' );
	}

	public function shortcode_stocklist ( $atts = array() ) {
		if(is_admin()) {
			// Do not render the stocklist in the admin site
			return;
		}

		if(empty($atts['code'])) {
			return 'Error: no code specified';
		}

		$data = array(
			'svm_sellang' => $this->get_language($atts),
		);
		$options = array(
			'carousel' => false,
			'quick_search' => false,
		);
		return $this->showSVM($atts['code'], $data, $options, 'default');
	}

	public function shortcode_carousel ( $atts = array() ) {
		if(is_admin()) {
			// Do not render the stocklist in the admin site
			return;
		}

		if(empty($atts['code'])) {
			return 'Error: no code specified';
		}

		if(empty($atts['direction']) || !in_array($atts['direction'], ['horizontal', 'vertical'])) {
			$direction = false;
		} else {
			$direction = $atts['direction'];
		}
		if(empty($atts['amount'])) {
			$amount = false;
		} else {
			$amount = $atts['amount'];
		}

		$data = array(
			'svm_sellang' => $this->get_language($atts),
		);
		$options = array(
			'carousel' => true,
			'carouselOptions' => array(
				'direction' => $direction,
				'amount' => $amount,
			),
			'quick_search' => false,
		);
		return $this->showSVM($atts['code'], $data, $options, 'carousel');
	}

	public function shortcode_quicksearch ( $atts = array() ) {
		if(is_admin()) {
			// Do not render the stocklist in the admin site
			return;
		}

		if(empty($atts['code'])) {
			return 'Error: no code specified';
		}

		$data = array(
			'svm_sellang' => $this->get_language($atts),
		);
		$options = array(
			'carousel' => false,
			'quick_search' => true,
		);
		return $this->showSVM($atts['code'], $data, $options, 'quick_search');
	}

	private function showSVM($code, $data, $config, $type) {
		$options = get_option( 'stocklist_integrator_options', false );
		if(!$options || !isset($options['provider']) || !isset(static::$providers[$options['provider']])) {
			return 'Error: the stocklist integrator plugin has not been configured yet';
		}
		$provider = static::$providers[$options['provider']];
		$domain = $provider['domain'];

		$s = '<div id="svm-canvas"></div>' . PHP_EOL;

		$s .= '<script type="text/javascript">' . PHP_EOL;
		$s .= '(function svm_init(){' . PHP_EOL;
		$s .= 'h=document.getElementsByTagName(\'head\')[0];s=document.createElement(\'script\');'. PHP_EOL;
		$s .= 's.type=\'text/javascript\';s.src="'. $domain .'/js/svm.js?t="+Date.now();s.onload=function(){ '. PHP_EOL;
		foreach($data as $key => $value) {
			$s .= 'svm.saveUrlGetData({key: '. json_encode($key) .', value: '. json_encode($value) .'});' . PHP_EOL;
		}
		$s .= 'vm=svm.create(\''. $code .'\',\''. $domain .'/\',false, '. json_encode($config) .', \''. $type .'\');' . PHP_EOL;
		$s .= 'vm.init();};h.appendChild(s);})();' . PHP_EOL;
		$s .= '</script>' . PHP_EOL;
		return $s;
	}

	protected function get_language($atts) {
		if(!empty($atts['lang'])) {
			return $atts['lang'];
		}

		if(function_exists('pll_current_language')) {
			return pll_current_language('slug');
		}

		return substr(get_bloginfo( 'language' ), 0, 2);
	}

	/**
 	 * Add ourselves to the settings menu
	 */
	public function setup_admin_menu() {
		add_options_page(
			'Stocklist Integrator',
			'Stocklist Integrator',
			'manage_options',
			'stocklist_integrator',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Redirect to settings page after activating the plugin
	 */
	public function admin_maybe_redirect() {
		if(get_option('stocklist_integrator_activation_redirect', false)) {
			delete_option('stocklist_integrator_activation_redirect');
			wp_redirect('options-general.php?page=stocklist_integrator');
			exit;
		}
	}

	public function register_settings() {
		register_setting( 'stocklist_integrator_options', 'stocklist_integrator_options', array('type' => 'array') );
		add_settings_section('stocklist_integrator_main', __('Settings', 'stocklist-integrator'), array($this, 'section_text'), 'stocklist_integrator');
		add_settings_field('stocklist_integrator_provider', __('Stocklist provider', 'stocklist-integrator'), array($this, 'setting_string'), 'stocklist_integrator', 'stocklist_integrator_main', array( 'label_for' => 'stocklist_integrator_provider' ) );
	}

	public function section_text() {
		print('<p>');
		_e('Please select your provider in the dropdown below. More settings are available in the configuration screen provided by the stocklist module.', 'stocklist-integrator');
		print('</p>');
	}

	public function setting_string() {
		$options = get_option('stocklist_integrator_options');
		print('<select id="stocklist_integrator_provider" name="stocklist_integrator_options[provider]">');
		print('<option value="">');
		_e('Choose provider', 'stocklist-integrator');
		print('</option>');
		foreach(static::$providers as $id => $provider) {
			print('<option value="'. $id .'"');
			if($options['provider'] == $id) {
				print(' selected');
			}
			print('>');
			print(htmlentities($provider['name']));
			print('</option>');
		}
		print('</select>');
	}

	/**
 	 * Display the settings page
	 */
	public function admin_page() {
		print('<div class="wrap">');
		print('<h1>Stocklist Integrator</h1>');
		print('<h2>');
		_e('Introduction', 'stocklist-integrator');
		print('</h2>');
		print('<p>');
		_e('The stocklist integrator is a Wordpress plugin that enables you to quickly and easily insert a stocklist module into your Wordpress site. You wil need a fully configured stocklist with one of the supported providers (see settings below).', 'stocklist-integrator');
		print('</p>');

		print('<form method="post" action="options.php">');
		settings_fields( 'stocklist_integrator_options' );
		do_settings_sections( 'stocklist_integrator' );
		submit_button();
		print('</form></div>');

		print('<h2>');
		_e('How to use', 'stocklist-integrator');
		print('</h2>');
		print('<p>');
		_e('You can use the following shortcode:', 'stocklist-integrator');
		print('</p>');
		print('<p><tt>[stocklist code="abcd"]</tt></p>');
		print('<p>');
		_e('Replace <tt>abcd</tt> with the code of your stocklist. Insert the shortcode on the page where you want the stocklist to appear.', 'stocklist-integrator');
		print('</p>');

		print('<h2>');
		_e('Carousel', 'stocklist-integrator');
		print('</h2>');
		print('<p>');
		_e('To display a carousel use the following shortcode:', 'stocklist-integrator');
		print('</p>');
		print('<p><tt>[carousel code="abcd" amount="5"]</tt></p>');
		print('<p>');
		_e('Replace <tt>abcd</tt> with the code of your stocklist. Amount is the number of vehicles to be displayed.', 'stocklist-integrator');
		print('</p>');

		print('<h2>');
		_e('Search form', 'stocklist-integrator');
		print('</h2>');
		print('<p>');
		_e('To add a search form use the following shortcode:', 'stocklist-integrator');
		print('</p>');
		print('<p><tt>[quicksearch code="abcd"]</tt></p>');
		print('<p>');
		_e('Replace <tt>abcd</tt> with the code of your stocklist. You can configure the form in the stocklist configurator.', 'stocklist-integrator');
		print('</p>');
	}
}

$StocklistIntegrator = StocklistIntegrator::getInstance();

