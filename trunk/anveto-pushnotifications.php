<?php

/*
Plugin Name: Anveto Push Notifications
Plugin URI: http://anveto.com/members/
Description: This plugin stores UDIDs from apps and allows users to send push notifications to users.
Version: 1.0
Author: Anveto, Markus Tenghamn
Author URI: http://anveto.com
License: GPL2
*/

$anveto_pushnotification_db_version = '1.0';

add_action('admin_menu', 'anveto_pushnotification_menu');



function anveto_pushnotification_menu()
{
    add_menu_page('Anveto Push Notifications', 'Push Notifications', 'administrator', __FILE__, 'anveto_pushnotification_settingsPage', plugins_url('/images/icon.png', __FILE__));

    add_action('admin_init', 'anveto_pushnotification_registerSettings');
}

function anveto_pushnotification_registerSettings()
{
    register_setting('anveto-settingsGroup', 'anveto-notifications-key');
//    register_setting('anveto-settingsGroup', 'anveto-shortenInternalLinks');
}

function anveto_pushnotification_settingsPage()
{
    ?>
    <div class="wrap">
        <h2>Anveto Push Notifications</h2>

        <p>
            This plugin stores tokens from apps used for push notifications. It creates an api endpoint which your app can send tokens to and also supports a key to make the url unique.
        </p>
        <?php
        $site_url = network_site_url( '/' );
        if (get_option('anveto-notifications-key') !== false && get_option('anveto-notifications-key') != "") {
            ?>
            <p>API Url: <?php echo $site_url; ?>api/anveto/<?php echo get_option('anveto-notifications-key'); ?>/(udid)</p>
        <?php
        } else {
            ?>
            <p>API Url: <?php echo $site_url; ?>api/anveto/(udid)</p>
            <?php
        }
        ?>
        <p>Replace the (udid) portion of the url with the token you wish to store. The token will be stored in the database along with a timestamp.</p>
        <p>Currently the app stores all UDIDs in the prefix_anveto_pushnotification table in your wordpress database.</p>

        <form method="post" action="options.php">
            <?php settings_fields('anveto-settingsGroup'); ?>
            <?php do_settings_sections('anveto-settingsGroup'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Set a API key</a>)</th>
                    <td><input type="text" name="anveto-notifications-key"
                               value="<?php echo get_option('anveto-notifications-key'); ?>"/>
                    </td>
                </tr>

            </table>

            <?php submit_button(); ?>

        </form>
    </div>
<?php
}

function anveto_pushnotification_install() {
    global $wpdb;
    global $anveto_pushnotification_db_version;

    $table_name = $wpdb->prefix . 'anveto_pushnotification';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
		token VARCHAR(300) NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'anveto_pushnotification_db_version', $anveto_pushnotification_db_version );
}


register_activation_hook( __FILE__, 'anveto_pushnotification_install' );

function anveto_pushnotification_add_query_vars($vars) {
    $vars[] = 'anveto_push_api';
    $vars[] = 'payload';
    if (get_option('anveto-notifications-key') !== false && get_option('anveto-notifications-key') != "") {
        $vars[] = 'key';
    }
    return $vars;
}

function anveto_pushnotification_add_endpoint(){
    //settings_fields('anveto-settingsGroup');
    //do_settings_sections('anveto-settingsGroup');
    if (get_option('anveto-notifications-key') !== false && get_option('anveto-notifications-key') != "") {
        add_rewrite_rule('^api/anveto/' . get_option('anveto-notifications-key') . '/?([^/]*)?/?', 'index.php?anveto_push_api=1&key='.get_option('anveto-notifications-key').'&payload=$matches[1]', 'top');
    } else {
        add_rewrite_rule('^api/anveto/?([^/]*)?/?', 'index.php?anveto_push_api=1&payload=$matches[1]', 'top');
    }
}

function anveto_pushnotification_sniff_requests() {
    global $wp;
    if (get_option('anveto-notifications-key') !== false && get_option('anveto-notifications-key') != "") {
        if (isset($wp->query_vars['key']) && $wp->query_vars['key'] == get_option('anveto-notifications-key')) {
            anveto_pushnotification_handle_request();
            exit;
        }
    } else {
        if (isset($wp->query_vars['anveto_push_api'])) {
            anveto_pushnotification_handle_request();
            exit;
        }
    }
}

function anveto_pushnotification_handle_request() {
    global $wp;
    global $wpdb;
    $key = $wp->query_vars['key'];
    $payload = trim($wp->query_vars['payload']);

    $table_name = $wpdb->prefix . 'anveto_pushnotification';

    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE token='$payload'"), ARRAY_A);

    if (count($results) == 0) {

        $wpdb->insert(
            $table_name,
            array(
                'token' => $payload
            )
        );

        $response = array('response' => 'stored');
        echo json_encode($response) . "\n";
    } else {
        $response = array('response' => 'exists');
        echo json_encode($response) . "\n";
    }
}

add_filter('query_vars', 'anveto_pushnotification_add_query_vars', 0);
add_action('parse_request', 'anveto_pushnotification_sniff_requests', 0);
add_action('init', 'anveto_pushnotification_add_endpoint', 0);

