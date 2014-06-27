<?php

/*
*  Meta box - regions
*
*  This template file is used when editing a block and creates the interface for editing region display.
*/

// global
global $post, $wpdb;
$table = $wpdb->base_prefix . $this->table_name;
	
// vars
//$options = apply_filters('blocks/block/get_options', array(), $post->ID);

//$blocks_regions = get_post_meta($post->ID, 'blocks_regions', true);

// get regions_pages

$blog_id = get_current_blog_id();
$sql = "SELECT GROUP_CONCAT(page SEPARATOR '\n') as pages, region
		FROM $table
		WHERE block_id = {$post->ID}
		AND blog_id = {$blog_id}
		GROUP BY region";
$regions_pages = $wpdb->get_results($sql);

$regions = get_option('sidebars_widgets', array());
// ignore non-sidebars
unset($regions['wp_inactive_widgets']);
unset($regions['array_version']);

foreach ($regions as $region_name => $widgets)
{
	$regions[$region_name] = "";
}
foreach ($regions_pages as $region_pages)
{
	$regions[$region_pages->region] = $region_pages->pages;
}

wp_nonce_field( 'save_blocks_regions', 'blocks_nonce' );
?>
<table class="blocks_input widefat" id="blocks_regions">
	<tr>
		<td class="label">
			<label for=""><?php _e("Region", "blocks"); ?></label>
			<p class="description"><?php _e("Inherited from theme-defined sidebars.",'blocks'); ?></p>
		</td>

		<td>
			<select id="blocks_region_select" name="blocks_region_select">
			<?php foreach ($regions as $region_name => $pages): ?>
				<option value='<?php echo $region_name; ?>'><?php echo $region_name; ?></option>
			<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<td class="label">
			<label for=""><?php _e("Pages", "blocks"); ?></label>
			<p class="description">
				<?php _e("List pages where this block will appear. Multiple pages should go on multiple lines. <br /><br />You may use the <strong>*</strong> character at the end of a path as a wildcard or &lt;front&gt; to designate the homepage."); ?>
			</p>
		</td>
		<td>
			<?php foreach ($regions as $region_name => $pages): ?>
			<textarea class="tall" id="blocks_regions[<?php echo $region_name; ?>]" name="blocks_regions[<?php echo $region_name; ?>]" style="display: none;"><?php echo $pages; ?></textarea>
			<?php endforeach; ?>
		</td?
	</tr>
</table>

<script>
	jQuery(function() {
		var selector = jQuery('#blocks_region_select');
		selector.change(function() {
			jQuery('#blocks_regions textarea').hide();
			jQuery('#blocks_regions\\[' + selector.find(':selected').val() + '\\]').show();
		});

		jQuery('#blocks_regions\\[' + selector.find(':selected').val() + '\\]').show();
	});
</script>
