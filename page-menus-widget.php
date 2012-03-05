<?php
/*
 * Plugin Name: Page Menus Widget
 * Plugin URI: http://www.joedonahue.org/projects/page-menus-widget/
 * Description: Display custom menu widgets on specific pages
 * Version: 1.3
 * Author: Joe Donahue
 * Author URI: http://www.joedonahue.org/
 * License: GPL2+
*/

function add_page_menus() {
	register_widget('Page_Menus_Widget');
}
add_action('widgets_init', 'add_page_menus');

function selective_display_2($itemID, $children_elements, $strict_sub = false) {
	global $wpdb;

	if ( ! empty($children_elements[$itemID]) ) {
		foreach ( $children_elements[$itemID] as &$childchild ) {
			$childchild->display = 1;
			if ( ! empty($children_elements[$childchild->ID]) && ! $strict_sub ) {
				selective_display_2($childchild->ID, $children_elements);
			}
		}
	}
	
}

//http://wordpress.stackexchange.com/questions/2802/display-a-only-a-portion-of-the-menu-tree-using-wp-nav-menu/2930#2930
class Page_Menus_Related_Sub_Items_Walker extends Walker_Nav_Menu
{	
	var $ancestors = array();
	var	$selected_children = 0;
	var $direct_path = 0;
	var $include_parent = 0;
	var	$start_depth = 0;
    var $page_id = 90;
	
	function display_element( $element, &$children_elements, $max_depth, $depth=0, $args, &$output ) {
		
		if ( !$element )
			return;
		
		$id_field = $this->db_fields['id'];

		//display this element
		if ( is_array( $args[0] ) )
			$args[0]['has_children'] = ! empty( $children_elements[$element->$id_field] );
		$cb_args = array_merge( array(&$output, $element, $depth), $args);
		if ( is_object( $args[0] ) ) {
	        $args[0]->has_children = ! empty( $children_elements[$element->$id_field] );
	    }    

		$display = ( isset($element->display) ) ? $element->display : 0;

		if ( ( ($this->selected_children && $display) || !$this->selected_children ) && ( ($this->start_depth && $depth >= $this->start_depth) || !$this->start_depth ) ) {
			if ( ($args[0]->only_related && ($element->menu_item_parent == 0 || (in_array($element->menu_item_parent, $this->ancestors) || $display)))
				|| (!$args[0]->only_related && ($display || !$args[0]->filter_selection) ) )
					call_user_func_array(array(&$this, 'start_el'), $cb_args);
		}

		$id = $element->$id_field;
	    
		// descend only when the depth is right and there are children for this element
		if ( ($max_depth == 0 || $max_depth > $depth+1 ) && isset( $children_elements[$id]) ) {
			
			foreach( $children_elements[ $id ] as $child ){

				$current_element_markers = array( 'current-menu-item', 'current-menu-parent', 'current-menu-ancestor', 'current_page_item' );
							
				$descend_test = array_intersect( $current_element_markers, $child->classes );

				if ( $args[0]->strict_sub || !in_array($child->menu_item_parent, $this->ancestors) && !$display )
						$temp_children_elements = $children_elements;
									
				if ( !isset($newlevel) ) {
					$newlevel = true;
					//start the child delimiter
					$cb_args = array_merge( array(&$output, $depth), $args);

					if ( ( ($this->selected_children && $display) || !$this->selected_children ) && ( ($this->start_depth && $depth >= $this->start_depth) || !$this->start_depth ) ) {	
						if ( ($args[0]->only_related && ($element->menu_item_parent == 0 || (in_array($element->menu_item_parent, $this->ancestors) || $display)))
							|| (!$args[0]->only_related && ($display || !$args[0]->filter_selection) ) )
									call_user_func_array(array(&$this, 'start_lvl'), $cb_args);
					}
				}												

				if ( $args[0]->only_related && !$args[0]->filter_selection && ( !in_array($child->menu_item_parent, $this->ancestors) && !$display && !$this->direct_path )
					|| ( $args[0]->strict_sub && empty( $descend_test ) && !$this->direct_path ) )
							unset ( $children_elements );		

				if ( ( $this->direct_path && !empty( $descend_test ) ) || !$this->direct_path ) {	
					$this->display_element( $child, $children_elements, $max_depth, $depth + 1, $args, $output );
				}

				if ($args[0]->strict_sub || !in_array($child->menu_item_parent, $this->ancestors) && !$display)
						$children_elements = $temp_children_elements;				
			}
			unset( $children_elements[ $id ] );
		}

		if ( isset($newlevel) && $newlevel ){
			//end the child delimiter
			$cb_args = array_merge( array(&$output, $depth), $args);
			if ( ( ($this->selected_children && $display) || !$this->selected_children ) && ( ($this->start_depth && $depth >= $this->start_depth) || !$this->start_depth ) ) {	
				if ( ($args[0]->only_related && ($element->menu_item_parent == 0 || (in_array($element->menu_item_parent, $this->ancestors) || $display)))
					|| (!$args[0]->only_related && ($display || !$args[0]->filter_selection) ) )
							call_user_func_array(array(&$this, 'end_lvl'), $cb_args);
			}
		}

		//end this element
		$cb_args = array_merge( array(&$output, $element, $depth), $args);
		if ( ( ($this->selected_children && $display) || !$this->selected_children ) && ( ($this->start_depth && $depth >= $this->start_depth) || !$this->start_depth ) ) {
			if ( ($args[0]->only_related && ($element->menu_item_parent == 0 || (in_array($element->menu_item_parent, $this->ancestors) || $display)))
				|| (!$args[0]->only_related && ($display || !$args[0]->filter_selection) ) )
						call_user_func_array(array(&$this, 'end_el'), $cb_args);
		}
	}
	
	function walk( $elements, $max_depth) {
		$post_id = $GLOBALS['post']->ID;
		
		$args = array_slice(func_get_args(), 2);

		if ( ! empty($args[0]->include_parent) )
			$this->include_parent = 1;

		if ( ! empty($args[0]->start_depth) )
			$this->start_depth = $args[0]->start_depth;

		if ( $args[0]->filter == 1 )
			$this->direct_path = 1;
		elseif ( $args[0]->filter == 2 )
			$this->selected_children = 1;

		$output = '';

		if ($max_depth < -1) //invalid parameter
			return $output;

		if (empty($elements)) //nothing to walk
			return $output;

		$id_field = $this->db_fields['id'];
		$parent_field = $this->db_fields['parent'];
		
		// flat display
		if ( -1 == $max_depth ) {
			$empty_array = array();
			foreach ( $elements as $e )
				$this->display_element( $e, $empty_array, 1, 0, $args, $output );
			return $output;
		}

		/*
		 * need to display in hierarchical order
		 * separate elements into two buckets: top level and children elements
		 * children_elements is two dimensional array, eg.
		 * children_elements[10][] contains all sub-elements whose parent is 10.
		 */
		$top_level_elements = array();
		$children_elements  = array();
		foreach ( $elements as $e) {
			if ( 0 == $e->$parent_field )
				$top_level_elements[] = $e;
			else
				$children_elements[ $e->$parent_field ][] = $e;
		}

		/*
		 * when none of the elements is top level
		 * assume the first one must be root of the sub elements
		 */
		if ( empty($top_level_elements) ) {

			$first = array_slice( $elements, 0, 1 );
			$root = $first[0];

			$top_level_elements = array();
			$children_elements  = array();
			foreach ( $elements as $e) {
				if ( $root->$parent_field == $e->$parent_field )
					$top_level_elements[] = $e;
				else
					$children_elements[ $e->$parent_field ][] = $e;
			}
		}

		if ( $args[0]->only_related || $this->include_parent || $this->selected_children ) {
			foreach ( $elements as &$el ) {
				if ( $this->selected_children )
				{
					if ( in_array('current-menu-item',$el->classes) )
							$args[0]->filter_selection = $el->ID;
							
				}
				elseif ( $args[0]->only_related ) 
				{
					if ( in_array('current-menu-item',$el->classes) ) {
						$el->display = 1;
						selective_display_2($el->ID, $children_elements);
						
						$ancestors = array();
						$menu_parent = $el->menu_item_parent;
			      		while ( $menu_parent && ! in_array( $menu_parent, $ancestors ) ) {
			                    $ancestors[] = (int) $menu_parent;
			                    $temp_menu_paret = get_post_meta($menu_parent, '_menu_item_menu_item_parent', true);
			                    $menu_parent = $temp_menu_paret;
			            }
			            $this->ancestors = $ancestors;
					}
				}
				if ( $this->include_parent ) {				
					if ( $el->ID == $args[0]->filter_selection )
							$el->display = 1;	
				}

			}
		}	
		
		$strict_sub_arg = ( $args[0]->strict_sub ) ? 1 : 0;
		if ( $args[0]->filter_selection || $this->selected_children )
				$top_parent = selective_display_2($args[0]->filter_selection, $children_elements, $strict_sub_arg);			
		
		$current_element_markers = array( 'current-menu-item', 'current-menu-parent', 'current-menu-ancestor', 'current_page_item' );
        
		foreach ( $top_level_elements as $e ) {				
			
			if ( $args[0]->only_related ) {

				$temp_children_elements = $children_elements;
				
				// descend only on current tree
				$descend_test = array_intersect( $current_element_markers, $e->classes );
				if ( empty( $descend_test ) && !$this->direct_path )  
					unset ( $children_elements );

				if ( ( $this->direct_path && !empty( $descend_test ) ) || ( !$this->direct_path ) ) {	
					$this->display_element( $e, $children_elements, $max_depth, 0, $args, $output );
				}

				$children_elements = $temp_children_elements;

			} elseif ( (! empty ($top_parent) && $top_parent == $e->ID ) || empty($top_parent) ) {
				$this->display_element( $e, $children_elements, $max_depth, 0, $args, $output );
			}
		}

		return $output;
	}

}

/**
 * Advanced Menu Widget class
 */
 class Page_Menus_Widget extends WP_Widget {

	function Page_Menus_Widget() {
		$widget_ops = array( 'description' => 'Add custom menus as a widget on specific pages.' );
		parent::WP_Widget( 'page_menus', 'Page Menus', $widget_ops );
	}

	function widget($args, $instance) {
		
		$only_related_walker = ( $instance['only_related'] == 2 || $instance['only_related'] == 3 || 1 == 1 )? new Page_Menus_Related_Sub_Items_Walker : new Walker_Nav_Menu;
		$strict_sub = $instance['only_related'] == 3 ? 1 : 0;
		$only_related = $instance['only_related'] == 2 || $instance['only_related'] == 3 ? 1 : 0;
		$selected_page = isset($instance['selected_page']) ? $instance['selected_page'] : 0;
		$depth = $instance['depth'] ? $instance['depth'] : 0;		
		$container = isset( $instance['container'] ) ? $instance['container'] : 'div';
		$container_id = isset( $instance['container_id'] ) ? $instance['container_id'] : '';
		$menu_class = isset( $instance['menu_class'] ) ? $instance['menu_class'] : 'menu';

		$custom_widget_class  = isset( $instance['custom_widget_class'] ) ? trim($instance['custom_widget_class']) : '';
		$include_parent = !empty($instance['include_parent']) ? 1 : 0;
		$start_depth = !empty($instance['start_depth']) ? absint($instance['start_depth']) : 0;

		// Get menu
		$nav_menu = wp_get_nav_menu_object( $instance['nav_menu'] );

		if ( !$nav_menu )
			return;

		$post_id = $GLOBALS['post']->ID;
		$instance['title'] = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		
		$menu = wp_nav_menu( array( 'echo' => false, 'fallback_cb' => '', 'menu' => $nav_menu, 'walker' => $only_related_walker, 'depth' => $depth, 'only_related' => $only_related, 'strict_sub' => $strict_sub, 'filter_selection' => $filter_selection, 'container' => $container,'container_id' => $container_id,'menu_class' => $menu_class, 'before' => $before, 'after' => $after, 'link_before' => $link_before, 'link_after' => $link_after, 'filter' => $filter, 'include_parent' => $include_parent, 'start_depth' => $start_depth ) );
		$menu_items = substr_count($menu,'class="menu-item ');
		
		if ($menu_items && ($selected_page == $post_id)) {
				
			echo "<li class='widget-container'>";		
				
			if ( $custom_widget_class ) {
				echo str_replace ('class="', 'class="' . "$custom_widget_class ");
			} 
			
			if ( !empty($instance['title']) ) 
				echo $args['before_title'] . $instance['title'] . $args['after_title'];
	
			echo $menu;
			
			echo $args['after_widget'];
			
			echo "</li> <!-- end page menus widget -->";			
		}
		
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( stripslashes($new_instance['title']) );		
		$instance['nav_menu'] = (int) $new_instance['nav_menu'];
		$instance['selected_page'] = (int) $new_instance['selected_page'];
		$instance['depth'] = (int) $new_instance['depth'];
		$instance['only_related'] = !$new_instance['filter_selection'] ? (int) $new_instance['only_related'] : 0;
					
		$instance['container'] = $new_instance['container'];
		$instance['container_id'] = $new_instance['container_id'];
		$instance['menu_class'] = $new_instance['menu_class'];

		
		$instance['include_parent'] = !empty($new_instance['include_parent']) ? 1 : 0;
		$instance['custom_widget_class'] = $new_instance['custom_widget_class'];
		$instance['start_depth'] = absint( $new_instance['start_depth'] );

		return $instance;
	}

	function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$nav_menu = isset( $instance['nav_menu'] ) ? $instance['nav_menu'] : '';
		$only_related = isset( $instance['only_related'] ) ? (int) $instance['only_related'] : 1;
		$selected_page = isset( $instance['selected_page'] ) ? (int) $instance['selected_page'] : 1;
		$depth = isset( $instance['depth'] ) ? (int) $instance['depth'] : 0;		
		$container = isset( $instance['container'] ) ? $instance['container'] : 'div';
		$container_id = isset( $instance['container_id'] ) ? $instance['container_id'] : '';
		$menu_class = isset( $instance['menu_class'] ) ? $instance['menu_class'] : 'menu';

		
		$custom_widget_class = isset( $instance['custom_widget_class'] ) ? $instance['custom_widget_class'] : '';
		$start_depth = isset($instance['start_depth']) ? absint($instance['start_depth']) : 0;
				
		$menus = get_terms( 'nav_menu', array( 'hide_empty' => false ) );
		//$pages = get_terms( 'pages', array( 'hide_empty' => false ) );
		$pages = get_pages();
		
		// If no menus exists, direct the user to go and create some.
		if ( !$menus ) {
			echo '<p>'. sprintf( __('No menus have been created yet. <a href="%s">Create some</a>.'), admin_url('nav-menus.php') ) .'</p>';
			return;
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:') ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" />
		</p>
	
		<p>
			<label for="<?php echo $this->get_field_id('nav_menu'); ?>"><?php _e('Select Menu:'); ?></label>
			<select id="<?php echo $this->get_field_id('nav_menu'); ?>" name="<?php echo $this->get_field_name('nav_menu'); ?>">
		    <?php
			foreach ( $menus as $menu ) {
				$selected = $nav_menu == $menu->term_id ? ' selected="selected"' : '';
				echo '<option'. $selected .' value="'. $menu->term_id .'">'. $menu->name .'</option>';
			}
		    ?>
			</select>
		</p>
		
		<p><label for="<?php echo $this->get_field_id('selected_page'); ?>"><?php _e('Page Assignment:'); ?></label>
		<select name="<?php echo $this->get_field_name('selected_page'); ?>" id="<?php echo $this->get_field_id('selected_page'); ?>" class="widefat">
			<option value="1"<?php selected( $selected_page, 1 ); ?>><?php _e('Display all'); ?></option>
		    <?php
			foreach ( $pages as $page ) {
				$selected = $selected_page == $page->ID ? ' selected="selected"' : '';
				echo '<option'. $selected .' value="'. $page->ID .'">'. $page->post_title .'</option>';
			}
		    ?>
		</select>
		</p>
			
		<p><label for="<?php echo $this->get_field_id('only_related'); ?>"><?php _e('Show hierarchy:'); ?></label>
		<select name="<?php echo $this->get_field_name('only_related'); ?>" id="<?php echo $this->get_field_id('only_related'); ?>" class="widefat">
			<option value="1"<?php selected( $only_related, 1 ); ?>><?php _e('Display all'); ?></option>
			<option value="2"<?php selected( $only_related, 2 ); ?>><?php _e('Only related sub-items'); ?></option>
			<option value="3"<?php selected( $only_related, 3 ); ?>><?php _e( 'Only strictly related sub-items' ); ?></option>
		</select>
		</p>

		<p><input id="<?php echo $this->get_field_id('include_parent'); ?>" name="<?php echo $this->get_field_name('include_parent'); ?>" type="checkbox" <?php checked(isset($instance['include_parent']) ? $instance['include_parent'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('include_parent'); ?>"><?php _e('Include parents'); ?></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('container'); ?>"><?php _e('Container:') ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('container'); ?>" name="<?php echo $this->get_field_name('container'); ?>" value="<?php echo $container; ?>" />
			<small><?php _e( 'Whether to wrap the ul, and what to wrap it with.' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('menu_class'); ?>"><?php _e('Menu Class:') ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('menu_class'); ?>" name="<?php echo $this->get_field_name('menu_class'); ?>" value="<?php echo $menu_class; ?>" />
			<small><?php _e( 'CSS class to use for the ul element which forms the menu.' ); ?></small>						
		</p>	
		<?php
	}
}