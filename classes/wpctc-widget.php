<?php

/**
 * Created by PhpStorm.
 * User: benohead
 * Date: 09.05.14
 * Time: 13:55
 */
class WPCTC_Widget extends WP_Widget
{

    function __construct()
    {
        parent::__construct(
            'wpctc_widget',
            __('Category Tag Cloud', 'wpctc_widget_domain'),
            array('description' => __('WP Category Tag Cloud', 'wpctc_widget_domain'),)
        );
    }

    private function get_child_categories($cat_id)
    {
        $args = array(
            'type' => 'post',
            'child_of' => $cat_id,
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => FALSE,
            'hierarchical' => 1,
            'taxonomy' => 'category',
        );
        $child_categories = get_categories($args);

        $category_list = array();

        if (!empty ($child_categories)) {
            foreach ($child_categories as $child_category) {
                $category_list[] = $child_category->term_id;
            }
        }

        return $category_list;
    }

    private function get_child_categories_list($categories)
    {
        $category_list = array();
        foreach ($categories as $cat_id) {
            $category_list[] = $cat_id;
            $category_list = array_merge($category_list, $this->get_child_categories($cat_id));
        }
        return $category_list;
    }

    public function widget($args, $instance)
    {
        global $wpdb;

        $md5 = md5(print_r(array_merge($args, $instance), true));
        $cache_id = 'wp_ctc_cache_' . $args['widget_id'];

        if (isset($instance['cache']) && $instance['cache'] === "1") {
            $current_time = time();
            $wp_ctc_cache = get_option($cache_id, array("", 0, ""));
            if ($wp_ctc_cache[1] > $current_time && $wp_ctc_cache[2] == $md5) {
                echo $wp_ctc_cache[0];
                error_log("Returning cached widget: " . $cache_id."(".$md5.")");
                return;
            }
        }

        ob_start();

        $title = apply_filters('widget_title', $instance['title']);
        echo $args['before_widget'];
        if (!empty($title))
            echo $args['before_title'] . $title . $args['after_title'];

        if (isset($instance['child_categories']) && $instance['child_categories'] === "1" && isset($instance['category_id']) && count($instance['category_id']) > 0) {
            $instance['category_id'] = $this->get_child_categories_list($instance['category_id']);
        }

        $tags = $wpdb->get_results
            ("
			SELECT DISTINCT tt2.term_id AS tag_id
			FROM $wpdb->posts as posts
				INNER JOIN $wpdb->term_relationships as tr1 ON posts.ID = tr1.object_ID
				INNER JOIN $wpdb->term_taxonomy as tt1 ON tr1.term_taxonomy_id = tt1.term_taxonomy_id
				INNER JOIN $wpdb->term_relationships as tr2 ON posts.ID = tr2.object_ID
				INNER JOIN $wpdb->term_taxonomy as tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
				INNER JOIN $wpdb->term_relationships as tr3 ON posts.ID = tr3.object_ID
				INNER JOIN $wpdb->term_taxonomy as tt3 ON tr3.term_taxonomy_id = tt3.term_taxonomy_id
			WHERE posts.post_status = 'publish'
                AND tt1.taxonomy = 'category'" .
                (isset($instance['category_id']) && count($instance['category_id']) > 0 ? " AND tt1.term_id IN (" . implode(",", $instance['category_id']) . ")" : "") . "
                AND tt2.taxonomy = '" . $instance['taxonomy'] . "'
                AND tt3.taxonomy = 'post_tag'" .
                (isset($instance['tag_id']) && count($instance['tag_id']) > 0 ? " AND tt3.term_id IN (" . implode(",", $instance['tag_id']) . ")" : "") . "
        ");

        $includeTags = '';
        if (count($tags) > 0) {
            foreach ($tags as $tag) {
                if (count($instance['tag_id']) > 0 && !in_array($tag->tag_id, $instance['tag_id'])) continue;
                $includeTags = $tag->tag_id . ',' . $includeTags;
            }
        }
        $cloud_args = array(
            'smallest' => $instance['format'] == 'price' ? '100' : $instance['smallest'],
            'largest' => $instance['format'] == 'price' ? '100' : $instance['largest'],
            'unit' => '%',
            'number' => $instance['number'],
            'format' => $instance['format'] == 'price' ? 'flat' : $instance['format'],
            'orderby' => $instance['order_by'],
            'order' => $instance['order'],
            'include' => null,
            'topic_count_text_callback' => default_topic_count_text,
            'link' => 'view',
            'taxonomy' => $instance['taxonomy'],
            'echo' => $instance['format'] != 'array',
        );
        if (strlen($includeTags > 0)) {
            $cloud_args['include'] = $includeTags;
        }
        ?>
    <div
        id="tagcloud"
        class='wpctc-<?php echo $args['widget_id']; ?> <?php echo ($instance['format'] == 'price') ? "wpctc-tag-links" : ""; ?> <?php echo (isset($instance['opacity']) && $instance['opacity'] === "1") ? "wpctc-opacity" : ""; ?>'>
        <?php
        if ($instance['format'] != 'array') {
            wp_tag_cloud($cloud_args);
        } else {
            $tags = wp_tag_cloud($cloud_args);
            ?>
            <canvas id="<?php echo $args['widget_id']; ?>_canvas" class="tagcloud-canvas"
                    data-tagcloud-color="<?php echo $instance['color']; ?>"
                    data-cloud-zoom=<?php echo $instance['zoom']; ?>>
            </canvas>
            </div>
            <div id="<?php echo $args['widget_id']; ?>_canvas_tags">
            <ul>
                <?php foreach ($tags as $tag) { ?>
                    <li><?php echo($tag); ?></li>
                <?php } ?>
            </ul>
        <?php
        }
        ?>
        </div>
        <?php
        if (isset($instance['color']) && !empty($instance['color'])) {
            ?>
            <style type="text/css">
                <?php echo ".wpctc-".$args['widget_id']; ?>
                a {
                    color: <?php echo $instance['color']; ?> !important;
                }
            </style>
        <?php
        }
        echo $args['after_widget'];

        $output = ob_get_clean();

        if (isset($instance['cache']) && $instance['cache'] === "1") {
            $timeout = isset($instance['timeout']) && is_numeric($instance['timeout']) ? $instance['timeout'] : 60;
            update_option($cache_id, array($output, $current_time + $timeout, $md5));
            error_log("Caching widget for " . $timeout . " seconds: " . $cache_id."(".$md5.")");
        }

        echo $output;
    }

    public function form($instance)
    {
        $title = (!empty($instance['title'])) ? strip_tags($instance['title']) : '';
        $category_id = isset($instance['category_id']) ? $instance['category_id'] : array();
        $child_categories = isset($instance['child_categories']) ? $instance['child_categories'] : "0";
        $opacity = isset($instance['opacity']) ? $instance['opacity'] : "0";
        $cache = isset($instance['cache']) ? $instance['cache'] : "0";
        $tag_id = isset($instance['tag_id']) ? $instance['tag_id'] : array();
        $order_by = isset($instance['order_by']) && strlen($instance['order_by']) > 0 ? $instance['order_by'] : 'name';
        $order = isset($instance['order']) && strlen($instance['order']) > 0 ? $instance['order'] : 'ASC';
        $format = isset($instance['format']) && strlen($instance['format']) > 0 ? $instance['format'] : 'flat';
        $number = isset($instance['number']) && (is_int($instance['number']) || ctype_digit($instance['number'])) ? $instance['number'] : 0;
        $taxonomy = isset($instance['taxonomy']) && strlen($instance['taxonomy']) > 0 ? $instance['taxonomy'] : 'post_tag';
        $zoom = isset($instance['zoom']) && is_numeric($instance['zoom']) ? $instance['zoom'] : 1;
        $timeout = isset($instance['timeout']) && is_numeric($instance['timeout']) ? $instance['timeout'] : 60;
        $smallest = isset($instance['smallest']) && (is_int($instance['smallest']) || ctype_digit($instance['smallest'])) ? $instance['smallest'] : 75;
        $largest = isset($instance['largest']) && (is_int($instance['largest']) || ctype_digit($instance['largest'])) ? $instance['largest'] : 200;
        $color = (!empty($instance['color'])) ? strip_tags($instance['color']) : '';
        if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            $color = '';
        }
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>"/>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('taxonomy'); ?>"><?php _e('Display:'); ?></label><br/>
            <select id="<?php echo $this->get_field_id('taxonomy'); ?>"
                    name="<?php echo $this->get_field_name('taxonomy'); ?>" class="widefat">
                <?php
                $taxonomies = get_taxonomies('', 'objects');
                foreach ($taxonomies as $field) {
                    ?>
                    <option
                        value="<?php echo($field->name); ?>" <?php selected($field->name, $taxonomy); ?>><?php echo $field->label; ?></option>
                <?php
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Max displayed items:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('number'); ?>"
                   name="<?php echo $this->get_field_name('number'); ?>" type="text"
                   value="<?php echo esc_attr($number); ?>"/>
            <small><em><?php _e('0 means display all'); ?></em></small>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('category_id'); ?>"><?php _e('Categories:'); ?></label><br/>
            <select id="<?php echo $this->get_field_id('category_id'); ?>"
                    name="<?php echo $this->get_field_name('category_id'); ?>[]" size=3 multiple="multiple"
                    class="widefat"
                    style="height: auto;">
                <?php
                $categories = get_categories(array('hide_empty' => 0));

                if ($categories) {
                    foreach ($categories as $category) {
                        $category->name = wp_specialchars($category->name);
                        ?>
                        <option value="<?php echo($category->term_id); ?>"
                        <?php
                        if (in_array($category->term_id, $category_id)) {
                            echo("selected='selected'");
                        }
                       echo ">$category->name</option>";
                    }
                }
                ?>
            </select>
        </p>
        <p>
            <input id="<?php echo $this->get_field_id('child_categories'); ?>"
                   name="<?php echo $this->get_field_name('child_categories'); ?>" type="checkbox"
                   class="widefat"
                   style="height: auto;"
                   value="1"
                <?php echo checked($child_categories, "1"); ?>>
            <label
                for="<?php echo $this->get_field_id('child_categories'); ?>"><?php _e('Include children'); ?></label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('tag_id'); ?>"><?php _e('Tags:'); ?></label><br/>
            <select id="<?php echo $this->get_field_id('tag_id'); ?>"
                    name="<?php echo $this->get_field_name('tag_id'); ?>[]" size=3 multiple="multiple" class="widefat"
                    style="height: auto;">
                <?php
                $tags = get_tags(array('hide_empty' => 0));

                if ($tags) {
                    foreach ($tags as $tag) {
                        $tag->name = wp_specialchars($tag->name);
                        ?>
                        <option value="<?php echo($tag->term_id); ?>"
                        <?php
                        if (in_array($tag->term_id, $tag_id)) {
                            echo("selected='selected'");
                        }
                       echo ">$tag->name</option>";
                    }
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('order_by'); ?>"><?php _e('Order By:'); ?></label><br/>
            <select id="<?php echo $this->get_field_id('order_by'); ?>"
                    name="<?php echo $this->get_field_name('order_by'); ?>" class="widefat">
                <?php
                $taxonomies = array('name' => __('Name'), 'count' => __('Count'));
                foreach ($taxonomies as $field_id => $field_name) {
                    ?>
                    <option
                        value="<?php echo($field_id); ?>" <?php selected($field_id, $order_by); ?>><?php echo $field_name; ?></option>
                <?php
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('order'); ?>"><?php _e('Order:'); ?></label><br/>
            <select id="<?php echo $this->get_field_id('order'); ?>"
                    name="<?php echo $this->get_field_name('order'); ?>" class="widefat">
                <?php
                $taxonomies = array('ASC' => __('Ascending'), 'DESC' => __('Descending'), 'RAND' => __('Random'));
                foreach ($taxonomies as $field_id => $field_name) {
                    ?>
                    <option
                        value="<?php echo($field_id); ?>" <?php selected($field_id, $order); ?>><?php echo $field_name; ?></option>
                <?php
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('format'); ?>"><?php _e('Format:'); ?></label><br/>
            <select id="<?php echo $this->get_field_id('format'); ?>"
                    name="<?php echo $this->get_field_name('format'); ?>" class="widefat cloud-type-selector">
                <?php
                $taxonomies = array('flat' => __('Separated by whitespace'), 'price' => __('Price tags'), 'list' => __('UL with a class of wp-tag-cloud'), 'array' => __('3D HTML5 Cloud'));
                foreach ($taxonomies as $field_id => $field_name) {
                    ?>
                    <option
                        value="<?php echo($field_id); ?>" <?php selected($field_id, $format); ?>><?php echo $field_name; ?></option>
                <?php
                }
                ?>
            </select>
        </p>
        <p class="canvas-config">
            <label
                for="<?php echo $this->get_field_id('zoom'); ?>"><?php _e('Initial zoom factor:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('zoom'); ?>"
                   name="<?php echo $this->get_field_name('zoom'); ?>" type="text"
                   value="<?php echo esc_attr($zoom); ?>"/>
        </p>
        <p class="cloud-non-price">
            <label
                for="<?php echo $this->get_field_id('smallest'); ?>"><?php _e('Size of the smallest item (in %):'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('smallest'); ?>"
                   name="<?php echo $this->get_field_name('smallest'); ?>" type="text"
                   value="<?php echo esc_attr($smallest); ?>"/>
        </p>
        <p class="cloud-non-price">
            <label
                for="<?php echo $this->get_field_id('largest'); ?>"><?php _e('Size of the largest item (in %):'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('largest'); ?>"
                   name="<?php echo $this->get_field_name('largest'); ?>" type="text"
                   value="<?php echo esc_attr($largest); ?>"/>
        </p>
        <p>
            <input id="<?php echo $this->get_field_id('cache'); ?>"
                   name="<?php echo $this->get_field_name('cache'); ?>" type="checkbox"
                   class="widefat"
                   style="height: auto;"
                   value="1"
                <?php echo checked($cache, "1"); ?>>
            <label
                for="<?php echo $this->get_field_id('cache'); ?>"><?php _e('Cache cloud'); ?></label>
            <label
                for="<?php echo $this->get_field_id('timeout'); ?>"><?php _e('for'); ?></label>
            <input size="6" id="<?php echo $this->get_field_id('timeout'); ?>"
                   name="<?php echo $this->get_field_name('timeout'); ?>" type="text"
                   value="<?php echo esc_attr($timeout); ?>"/>
            <?php _e('seconds'); ?>
        </p>
        <p>
            <input id="<?php echo $this->get_field_id('opacity'); ?>"
                   name="<?php echo $this->get_field_name('opacity'); ?>" type="checkbox"
                   class="widefat"
                   style="height: auto;"
                   value="1"
                <?php echo checked($opacity, "1"); ?>>
            <label
                for="<?php echo $this->get_field_id('opacity'); ?>"><?php _e('Adapt opacity'); ?></label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('color'); ?>"><?php _e('Font color:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('color'); ?>"
                   name="<?php echo $this->get_field_name('color'); ?>" type="text"
                   value="<?php echo esc_attr($color); ?>"/>
            <small><em><?php _e('Leave empty to use the default theme color.'); ?></em></small>
        <div class="wpctc-color-picker" rel="<?php echo $this->get_field_id('color'); ?>"></div>
        </p>
    <?php
    }

    // Updating widget replacing old instances with new
    function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : __('New title', 'wpctc_widget_domain');
        $instance['category_id'] = $new_instance['category_id'];
        $instance['child_categories'] = $new_instance['child_categories'];
        $instance['opacity'] = $new_instance['opacity'];
        $instance['cache'] = $new_instance['cache'];
        $instance['tag_id'] = $new_instance['tag_id'];
        $instance['order_by'] = isset($new_instance['order_by']) && strlen($new_instance['order_by']) > 0 ? $new_instance['order_by'] : 'name';
        $instance['order'] = isset($new_instance['order']) && strlen($new_instance['order']) > 0 ? $new_instance['order'] : 'ASC';
        $instance['format'] = isset($new_instance['format']) && strlen($new_instance['format']) > 0 ? $new_instance['format'] : 'flat';
        $instance['number'] = isset($new_instance['number']) && (is_int($new_instance['number']) || ctype_digit($new_instance['number'])) ? $new_instance['number'] : 0;
        $instance['taxonomy'] = isset($new_instance['taxonomy']) && strlen($new_instance['taxonomy']) > 0 ? $new_instance['taxonomy'] : 'post_tag';
        $instance['zoom'] = isset($new_instance['zoom']) && is_numeric($new_instance['zoom']) ? $new_instance['zoom'] : 1;
        $instance['timeout'] = isset($new_instance['timeout']) && is_numeric($new_instance['timeout']) ? $new_instance['timeout'] : 60;
        $instance['smallest'] = isset($new_instance['smallest']) && (is_int($new_instance['smallest']) || ctype_digit($new_instance['smallest'])) ? $new_instance['smallest'] : 75;
        $instance['largest'] = isset($new_instance['largest']) && (is_int($new_instance['largest']) || ctype_digit($new_instance['largest'])) ? $new_instance['largest'] : 200;
        $color = (!empty($new_instance['color'])) ? strip_tags($new_instance['color']) : '';
        if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            $color = '';
        }
        $instance['color'] = $color;
        return $instance;
    }
} // Class wpctc_widget ends here

// Register and load the widget
function wpctc_load_widget()
{
    register_widget('wpctc_widget');
}

add_action('widgets_init', 'wpctc_load_widget');