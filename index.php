<?php
/**
* Plugin Name: orange-money-charity-form
* Plugin URI: https://www.odima-mg.org/
* Description: Ce plugins permet d'utiliser orange money pour récupérer les dons de l'ONG ODIMA.
* Version: 0.1
* Author: RijaCloud
* Author URI: https://github.com/RijaCloud
**/

// Donation form shortcode
function donation_form_shortcode() {
    ob_start();
    ?>
    <form method="post" id="donate-orange" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
        <input type="hidden" name="action" value="process_donation">
        <?php wp_nonce_field( 'donation_form', 'donation_nonce' ); ?>
        <label for="donation_amount">Entrez le montant du don:</label>
        <input type="number" id="donation_amount" name="donation_amount">
        <button type="submit" class="feature-media-button gdlr-button">Donner</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'donation_form', 'donation_form_shortcode' );

// Enqueue plugin stylesheet
function enqueue_plugin_styles() {
    // Get the path to your plugin's CSS file
    $css_file = plugins_url( 'css/index.css', __FILE__ );

    // Enqueue the stylesheet
    wp_enqueue_style( 'plugin-styles', $css_file );
}
add_action( 'wp_enqueue_scripts', 'enqueue_plugin_styles' );

function generate_invoice_id() {
    // Generate a random string or number
    $random_part = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 10)), 0, 10);

    // Generate timestamp part of the invoice ID (you can customize this as needed)
    $timestamp_part = date('YmdHis');

    // Combine both parts to form the invoice ID
    $invoice_id = $timestamp_part . '_' . $random_part;

    return $invoice_id;
}

// Process form submission
function process_donation() {
    if ( !isset($_POST['donation_nonce']) || !wp_verify_nonce( $_POST['donation_nonce'], 'donation_form' ) ) {
        wp_die( 'Security check failed' );
    }

   // Sanitize and validate donation amount
    $donation_amount = isset( $_POST['donation_amount'] ) ? floatval( $_POST['donation_amount'] ) : 0;

    // Prepare the request arguments
    $args = array(
        'headers' => array(
            'Authorization' => 'Basic OEt5aDBCNE9qeWpEeEh5SUVpekdmWFZKRVRkTXBMcFc6OW01Z25EQkFMdUVTQm95YQ==',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ),
        'body' => array(
            'grant_type' => 'client_credentials',
        ),
    );

    // Make POST request to retrieve credentials
    $credentials_response = wp_remote_post( 'https://api.orange.com/oauth/v3/token', $args );

    if ( is_wp_error( $response ) ) {
        // Handle error
        wp_die( 'Error Token :' . $credentials_response->get_error_message() );
    } else {
        // Check if response is successful
        $credentials_response_code = wp_remote_retrieve_response_code( $credentials_response );
        if ( 200 === $credentials_response_code ) {
            // Credentials retrieved successfully
            $credentials_body = wp_remote_retrieve_body( $credentials_response );
            $credentials_data = json_decode( $credentials_body );

            // Prepare data for the next API call
            $send_data = array(
                "merchant_key" => "3134cd57",
                "currency" => "MGA",
                "order_id" => generate_invoice_id(),
                "amount" => $donation_amount,
                "return_url" => "https://odima-mg.org",
                "cancel_url" => "https://odima-mg.org",
                "notif_url" => "https://odima-mg.org",
                "lang" => "fr",
                "reference" => "ONG ODIMA"
            );

            // Prepare request arguments for sending data to the next API
            $send_args = array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => $credentials_data->token_type . ' ' . $credentials_data->access_token,
                ),
                'body' => json_encode( $send_data ),
                'timeout' => 320
            );
            // Make POST request to send data to the next API
            $send_response = wp_remote_post( 'https://api.orange.com/orange-money-webpay/dev/v1/webpayment', $send_args );

            if ( is_wp_error( $send_response ) ) {
                // Handle error
                wp_die( 'Error send response : ' . $send_response->get_error_message() );
            } else {
                // Check if response is successful
                $send_response_code = wp_remote_retrieve_response_code( $send_response );
                if ( 200 === $send_response_code ) {
                    // Data sent successfully
                    // Data sent successfully
                    $send_body = wp_remote_retrieve_body( $send_response );
                    $send_data = json_decode( $send_body );

                    // Redirect user to the payment URL
                    wp_redirect( $send_data->payment_url );
                    exit;
                } else {
                    // Handle non-200 response
                    wp_die( 'Error redirect paiement : ' . $send_response_code );
                }
            }
        } else {
            // Handle non-200 response
            wp_die( 'Error status code: ' . $credentials_response_code );
        }
    }


    // Redirect back to the donation page
    wp_redirect( $_SERVER['HTTP_REFERER'] );
    exit;
}
add_action( 'admin_post_process_donation', 'process_donation' );
add_action( 'admin_post_nopriv_process_donation', 'process_donation' );
