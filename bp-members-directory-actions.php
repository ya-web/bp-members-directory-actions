<?php
/*
 * @wordpress-plugin
 * Plugin Name:       BP Members Directory Actions
 * Plugin URI:        https://github.com/telabotanica/bp-members-directory-actions
 * GitHub Plugin URI: https://github.com/telabotanica/bp-members-directory-actions
 * Description:       A BuddyPress plugin that adds capacity to perform bulk actions on members from the members directory
 * Version:           0.1
 * Author:            Tela Botanica
 * Author URI:        https://github.com/telabotanica
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bp-members-directory-actions
 * Domain Path:       /languages
 */

add_action('bp_include', 'bp_mda_init');

function bp_mda_init() {
	// Custom rendering of members directory: checkboxes, actions menu...
	require_once __DIR__ . '/overload-members-directory-template.php';

	// If a bulk action was triggered, display action-specific form
	if (! empty($_REQUEST['bp_mda_bulk_action'])) {
		add_action('bp_mda_action_specific_form', 'bp_mda_add_action_specific_form', 10);
	}

	// Add "select all page items" checkbox before members list
	add_action('bp_before_directory_members_list', 'bp_mda_select_all_page_items_checkbox', 10);
	if (class_exists('bps_widget')) { // BP Profile Search compatibility
		// Add "select all search results" checkbox before members list
		add_action('bp_before_directory_members_list', 'bp_mda_add_select_all_search_results_checkbox', 10);
	}

	// Add filterable actions menu before members list
	add_action('bp_before_directory_members_list', 'bp_mda_add_filterable_actions_menu', 10);
	// Populate actions menu with default values
	add_filter('bp_mda_bulk_actions', 'bp_mda_add_default_bulk_actions');

	// Add a checkbox before every member
	add_action('bp_directory_before_members_item', 'bp_mda_add_checkbox_before_member_item', 10);

	// Add JS on members directory page
	add_action( 'bp_after_directory_members_page', 'bp_mda_load_javascript' );
}

/**
 * Displays a form depending on the chosen bulk action
 */
function bp_mda_add_action_specific_form() {
	// Chosen action
	$action = $_REQUEST['bp_mda_bulk_action'];
	// User IDs of checked items (array) - might be disabled !
	$recipients = array();
	if (isset($_REQUEST['bp_mda_recipients'])) {
		$recipients = $_REQUEST['bp_mda_recipients'];
	}
	// Was the "Select all search results" checkbox present and checked ?
	$allSearchResults = false;
	if (isset($_REQUEST['bp_mda_select_all_search_results'])) {
		$allSearchResults = ($_REQUEST['bp_mda_select_all_search_results'] == "on");
	}

	switch ($action) {
		case "send-message":
		?>
		<h3>Envoi de messages !!</h3>
		<?php break;
		case "buy-cookie":
		?>
		<h3>Je veux un cookie !!</h3>
		<?php break;
		default:
	}
	// Propagate search results if any
	bp_mda_bp_profile_search_proxy();
}

/**
 * Displays a checkbox allowing to check all checkboxes on the current page
 */
function bp_mda_select_all_page_items_checkbox() {
	?>
	<div class="bp_mda_select_all_page_items">
		<input type="checkbox" class="bp_mda_all_page_items_checkbox">
	</div>
	<?php
}

/**
 * Displays the bulk actions menu, including an actions list fed by
 * "bp_mda_bulk_actions" filter chain
 */
function bp_mda_add_filterable_actions_menu() {
	?>
	<div class="bp_mda_bulk_actions_container">
		<form action="" method="POST" class="bp_mda_bulk_actions_form">
			<label class="bp_mda_bulk_actions_label">
				<?php _e('Bulk actions', 'bp-members-directory-actions'); ?>
			</label>
			<select class="bp_mda_bulk_actions_options" name="bp_mda_bulk_action">
			<?php
				// previously chosen action if any
				$action = '';
				if (! empty($_REQUEST['bp_mda_bulk_action'])) {
					$action = $_REQUEST['bp_mda_bulk_action'];
				}
				// actions list
				$options = apply_filters('bp_mda_bulk_actions', array());
				foreach ($options as $k => $opt) { ?>
					<option value="<?php echo $k ?>" <?php echo ($action == $k) ? 'selected="selected"' : '' ?>>
						<?php echo $opt ?>
					</option>
				<?php }
			?>
			</select>
			<?php
				// Propagate search results if any
				bp_mda_bp_profile_search_proxy();
			?>
			<input class="bp_mda_bulk_actions_submit" type="submit" value="Go !">
		</form>
	</div>
	<?php
}

/**
 * If BP Profile Search is enabled and a search was performed, outputs
 * corresponding hidden inputs to propagate the search state through subsequent
 * bulk actions
 */
function bp_mda_bp_profile_search_proxy() {
	if (! empty($_REQUEST['bp_profile_search'])) { ?>
		<input type="hidden" name="bp_profile_search" value="<?php echo $_REQUEST['bp_profile_search'] ?>">
	<?php }
	if (! empty($_REQUEST['text_search'])) { ?>
		<input type="hidden" name="text_search" value="<?php echo $_REQUEST['text_search'] ?>">
	<?php }
	foreach ($_REQUEST as $k => $v) {
		if (substr($k, 0, 6) == 'field_') { ?>
			<input type="hidden" name="<?php echo $k ?>" value="<?php echo $v ?>">
		<?php }
	}
}

/**
 * Displays a checkbox that marks all search results (across multiple pages) as
 * selected
 */
function bp_mda_add_select_all_search_results_checkbox() {
	if (! empty($_REQUEST['bp_profile_search'])) {
		global $members_template;
		$checked = (isset($_REQUEST['bp_mda_select_all_search_results']) && ($_REQUEST['bp_mda_select_all_search_results'] == "on"));
		?>
		<div class="bp_mda_select_all_search_results">
			<label>
				<input type="checkbox" name="bp_mda_select_all_search_results"
					   class="bp_mda_all_search_results_checkbox bp_mda_checkbox"
					   <?php echo ($checked) ? 'checked="checked"' : '' ?>>
				<?php _e('Select all search results', 'bp-members-directory-actions') ?>
				(<?php echo bp_core_number_format($members_template->total_member_count) ?>)
			</label>
		</div>
	<?php
	}
}

/**
 * Default filter applied to "bp_mda_bulk_actions" : adds "Send message" action
 */
function bp_mda_add_default_bulk_actions($values) {
	$values = array(
		'send-message' => __('Send a message', 'bp-members-directory-actions'),
		'buy-cookie' => __('Buy a cookie', 'bp-members-directory-actions')
	);
	return $values;
}

/**
 * Adds a checkbox before each members list item; the checkbox value is the
 * corresponding user's ID
 */
function bp_mda_add_checkbox_before_member_item() {
	$recipients = array();
	if (isset($_REQUEST['bp_mda_recipients'])) {
		$recipients = $_REQUEST['bp_mda_recipients'];
	}
	?>
	<div class="bp_mda_before_member_item">
		<input type="checkbox"
			name="bp_mda_recipients[]"
			class="bp_mda_member_item_checkbox bp_mda_checkbox"
			<?php echo (in_array(bp_get_member_user_id(), $recipients)) ? 'checked="checked"' : '' ?>
			value="<?php bp_member_user_id() ?>">
	</div>
	<?php
}

/**
 * Loads custom JS file depending on jQuery
 */
function bp_mda_load_javascript() {
	wp_enqueue_script(
		'bp-members-directory-actions',
		WP_PLUGIN_URL . '/bp-members-directory-actions/bp-members-directory-actions.js',
		array('jquery')
	);
}
