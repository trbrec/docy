<?php
/**
* Liquid Themes Theme Framework
* The Liquid_Admin_Dashboard base class
*/

if ( !defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

class Docy_admin_dashboard extends Docy_admin_page {
	protected string $id;
	protected string $page_title;
	protected string $menu_title;

	/**
	 * [__construct description]
	 * @method __construct
	 */
	public function __construct() {

		$this->id = 'docy';
		$this->page_title = esc_html__( 'Docy Dashboard', 'docy' );
		$this->menu_title = esc_html__( 'Register/Verify', 'docy' );
		$this->position = '50';

		parent::__construct();
	}

	/**
	 * [display description]
	 * @method display
	 * @return [type]  [description]
	 */
	public function display() {
		include_once( get_template_directory() . '/inc/admin/dashboard/dashboard.php' );
	}

	/**
	 * [save description]
	 * @method save
	 * @return [type] [description]
	 */
	public function save() {

	}
}
new Docy_admin_dashboard;
