<?php
/**
 * WP Bootstrap Navwalker
 *
 * @package WP-Bootstrap-Navwalker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Docy_Mobile_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * Start Level.
	 *
	 * @param mixed $output Passed by reference. Used to append additional content.
	 * @param int   $depth  (default: 0) Depth of page. Used for padding.
	 * @param array $args   (default: array()) Arguments.
	 *
	 * @return void
	 * @see    Walker::start_lvl()
	 * @since  3.0.0
	 *
	 * @access public
	 */
	public function start_lvl( &$output, $depth = 0, $args = [] ) {
		$indent = str_repeat( "\t", $depth );
		if ( 0 === $depth ) {
			$output .= '<i class="arrow_carrot-down_alt2 mobile_dropdown_icon"></i> </div>';
			$output .= "\n$indent<ul class=\" dropdown-menu\" >\n";
		}
		if ( 1 === $depth ) {
			$output .= '<i class="arrow_carrot-down_alt2 mobile_dropdown_icon"></i> </div>';
			$output .= '' . "\n$indent<ul class=\" dropdown-menu\" >\n";
		}
		if ( 2 === $depth ) {
			$output .= "\n$indent<ul class=\" dropdown-menu\" >\n";
		}
		if ( 3 === $depth ) {
			$output .= "\n$indent<ul class=\" dropdown-menu\" >\n";
		}
	}

	/**
	 * Start El.
	 *
	 * @param mixed $output Passed by reference. Used to append additional content.
	 * @param mixed $item   Menu item data object.
	 * @param int   $depth  (default: 0) Depth of menu item. Used for padding.
	 * @param array $args   (default: array()) Arguments.
	 * @param int   $id     (default: 0) Menu item ID.
	 *
	 * @return void
	 * @see    Walker::start_el()
	 * @since  3.0.0
	 *
	 * @access public
	 */
	public function start_el( &$output, $item, $depth = 0, $args = [], $id = 0 ) {
		$menu_description = '';
		$is_description   = '';

		if ( ! empty( $item->description ) ) {
			$menu_description = '<span class="menu-item-description">' . esc_attr( $item->description ) . '</span>';
			$is_description   = 'has-menu-description ';
		}

		// Get menu item image
		$menu_image = '';
		$menu_item_image_id = get_post_meta( $item->ID, '_menu_item_image', true );
		if ( $menu_item_image_id ) {
			$image_url = wp_get_attachment_image_url( $menu_item_image_id, 'thumbnail' );
			if ( $image_url ) {
				$menu_image = '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $item->title ) . '" class="menu-item-image" />';
			}
		}

		$url      = $item->url;
		$url_hash = strpos( $url, '#' );

		$indent      = ( $depth ) ? str_repeat( "\t", $depth ) : '';
		$value       = '';
		$class_names = $value;
		$classes     = empty( $item->classes ) ? [] : (array) $item->classes;
		$classes[]   = $is_description . 'menu-item-' . $item->ID;

		// Add class if menu item has image
		if ( $menu_item_image_id ) {
			$classes[] = 'has-menu-image';
		}

		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );
		if ( $args->has_children ) {
			$class_names .= ' dropdown submenu';
		}

		if ( in_array( 'current-menu-item', $classes, true ) ) {
			$class_names .= ' active';
		}
		if ( in_array( 'menu-item', $classes, true ) ) {
			$class_names .= ' nav-item';
		}

		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';
		$id          = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args );
		$id          = $id ? ' id="' . esc_attr( $id ) . '"' : '';
		$output      .= $indent . '<li itemscope="itemscope" ' . $id . $value . $class_names . '>';
		$atts        = [];
		if ( empty( $item->attr_title ) ) {
			$atts['title'] = ! empty( $item->title ) ? strip_tags( $item->title ) : '';
		} else {
			$atts['title'] = $item->attr_title;
		}
		$atts['target'] = ! empty( $item->target ) ? $item->target : '';
		$atts['rel']    = ! empty( $item->xfn ) ? $item->xfn : '';
		// If item has_children add atts to a.
		if ( $args->has_children && 0 === $depth || $args->has_children && 1 === $depth ) {
			$atts['class'] = 'nav-link';
			$atts['href']  = ! empty( $item->url ) ? $item->url : '';
		} else {
			$atts['class'] = 'nav-link';
			$atts['href']  = ! empty( $item->url ) ? $item->url : '';
		}
		$atts       = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args );
		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value      = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}
		$item_output = $args->before;
		/*
		 * Glyphicons/Font-Awesome
		 * ===========
		 * Since the the menu item is NOT a Divider or Header we check the see
		 * if there is a value in the attr_title property. If the attr_title
		 * property is NOT null we apply it as the class name for the glyphicon.
		 */

		if ( ! empty( $item->attr_title ) ) {
			$item_output .= '<div class="nav-link-wrap"> <a' . $attributes . '>';
		} else {
			$item_output .= '<div class="nav-link-wrap"> <a' . $attributes . '>';
		}

		// Output image if exists
		$item_output .= $menu_image;

		// Wrap title and description in a container if both exist
		if ( $menu_item_image_id && ! empty( $item->description ) ) {
			$item_output .= '<span class="menu-item-content">';
		}

		$item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after . $menu_description;

		// Close wrapper if opened
		if ( $menu_item_image_id && ! empty( $item->description ) ) {
			$item_output .= '</span>';
		}

		$item_output .= ( $args->has_children && 1 === $depth ) ? '</a>' : esc_attr( $item->attr_title ) . '</a>';
		$item_output .= $args->after;
		$output      .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}

	/**
	 * Traverse elements to create list from elements.
	 *
	 * Display one element if the element doesn't have any children otherwise,
	 * display the element and its children. Will only traverse up to the max
	 * depth and no ignore elements under that depth.
	 *
	 * This method shouldn't be called directly, use the walk() method instead.
	 *
	 * @param mixed $element           Data object.
	 * @param mixed $children_elements List of elements to continue traversing.
	 * @param mixed $max_depth         Max depth to traverse.
	 * @param mixed $depth             Depth of current element.
	 * @param mixed $args              Arguments.
	 * @param mixed $output            Passed by reference. Used to append additional content.
	 *
	 * @return null Null on failure with no changes to parameters.
	 * @since  2.5.0
	 *
	 * @access public
	 * @see    Walker::start_el()
	 */
	public function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) {
		if ( ! $element ) {
			return;
		}
		$id_field = $this->db_fields['id'];
		// Display this element.
		if ( is_object( $args[0] ) ) {
			$args[0]->has_children = ! empty( $children_elements[ $element->$id_field ] );
		}
		parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );
	}
}
