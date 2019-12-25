<?php
/*
 * Plugin Name: Woocommerce CSV Import
 * Author: IPS
 * description: Import all Product from CSV file to Woocommerce
 * version: 1.0
*/

Class CSVImportWOO{
	
	protected static $_instance = null;
	
	/*
	 * Instance for Singleton method
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	
	/*
	 * Autoload to run plugin code
	 */
	public function __construct() {
		add_action( 'admin_init', array($this,'pluginCode'), 13 );
	}

	
	/**
	 * Run the plugin code and function.
	 */
	public function pluginCode(){
		
		/** Display verbose errors */
		define( 'IMPORT_DEBUG', true );
		ini_set('memory_limit','512M');
		ini_set('display_erros',1);
		error_reporting(E_ALL);
		set_time_limit(0);
		
		if ( ! class_exists( 'WP_Importer' ) ) {
			$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
			if ( file_exists( $class_wp_importer ) )
				require $class_wp_importer;
		}
			
		$this->load_files();
		$Corcrm_Import = new Corcrm_Import();
		register_importer('corcrm', __('Corcrm CSV'), __('Import product data from a Corcrm CSV export file.'), array ($Corcrm_Import, 'dispatch'));
			
		
		
	}
	
	/**
 	 * Include files for the code
	 */
	public function load_files(){
		include_once('classes/class.corcrmImporter.php');
		include_once('classes/class.createProduct.php');
		include_once('classes/class.getProductCsv.php');
	}
	
}

/*
 * Load CSV ImportWOO
 */
CSVImportWOO::instance();