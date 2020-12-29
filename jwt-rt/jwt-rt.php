<?php 

/*

Plugin Name: JWT Refresh Token
Description: This is an extension for "JWT Auth" plugin by Useful Team. It adds Refresh Token support for better JWT Authentication.
Author: Piotr Kucułyma
Author URI: wpsoft.pl
Version: 0.0.1

*/

use Firebase\JWT\JWT;

defined( 'ABSPATH' ) || die( "Can't access directly" );

// Helper constants.
define( 'JWTRT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWTRT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JWTRT_PLUGIN_VERSION', '1.0.0' );
define( 'JWT_AUTH_PLUGIN_BASENAME', 'jwt-auth/jwt-auth.php' );
define( 'JWT_AUTH_PLUGIN_FILE', WP_CONTENT_DIR . '/plugins/jwt-auth/jwt-auth.php' );

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

add_action('plugins_loaded', function() {

    // Check if JWT Auth is installed and activated

    if( ! file_exists( JWT_AUTH_PLUGIN_FILE ) ) {
        add_action( 'admin_notices', function() { ?>
            <div class="notice notice-error is-dismissible">
                <p>Please install <a href="<?= admin_url('plugin-install.php?s=jwt+auth&tab=search&type=term') ?>">JWT Auth</a> plugin by Useful Team before running JWT Refresh Token.</p>
            </div>
            <?php });
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return;
    }
    
    if( ! is_plugin_active( JWT_AUTH_PLUGIN_BASENAME ) ) {
        add_action( 'admin_notices', function() { ?>
            <div class="notice notice-error is-dismissible">
                <p>Please activate <b>JWT Auth</b> plugin before running JWT Refresh Token.</p>
            </div>
            <?php });
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return;
    }

    
    
    // SETUP REST ENDPOINTS

    add_action( 'rest_api_init', function() {
        
        register_rest_route( 'jwt-auth/v1', 'token/refresh', [
            'methods'=>'POST',
            'callback'=>'jwtrt_validate_refresh_token',
            'permission_callback'=>'__return_true',
        ]);
        
        register_rest_route( 'jwt-auth/v1', 'register-user', [
            'methods'=>'POST',
            'callback'=>'jwtrt_register_new_user'
        ]);
            
        register_rest_route( 'jwt-auth/v1', 'reset-password', [
            'methods'=>'POST',
            'callback'=>'jwtrt_reset_user_password'
        ]);
    
    });
            
    // Add endpoints to whitelist so they won't be checked for authorization
    
    add_filter( 'jwt_auth_whitelist', function ( $endpoints ) {
        $endpoints[] = '/wp-json/jwt-auth/v1/token/refresh';
        $endpoints[] = '/wp-json/jwt-auth/v1/register-user';
        $endpoints[] = '/wp-json/jwt-auth/v1/reset-password';
        return $endpoints;
    });
    
    
    
    // SET EXPIRATION TIME FOR TOKENS
    
    add_filter( 'jwt_auth_expire', function( $expire, $issued_at ) {
        return $issued_at + 60 * 15;
    }, 10, 2 );



    // ISSUE REFRESH TOKEN WITH NORMAL TOKEN ON LOGIN

    add_filter( 'jwt_auth_valid_credential_response', function( $response, $user ) {
        
        $jwt_auth = new \JWTAuth\Auth;
        
        // Create refresh token
        
        $issued_at  = time();
		$not_before = $issued_at;
		$not_before = apply_filters( 'jwtrt_auth_not_before', $not_before, $issued_at );
		$expire     = $issued_at + ( DAY_IN_SECONDS );
		$expire     = apply_filters( 'jwtrt_auth_expire', $expire, $issued_at );

        $payload = array(
			'iss'  => $jwt_auth->get_iss(),
			'iat'  => $issued_at,
			'nbf'  => $not_before,
			'exp'  => $jwt_refresh_expire,
			'data' => array(
				'user' => array(
					'id' => $user->ID,
                ),
                'is_refresh_token' => true
			),
        );
        
        $alg =              $jwt_auth->get_alg();
        $secret_key =       defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : false;
        $refresh_token =    JWT::encode( $payload, $secret_key, $alg );
        
        // We use current_time() to take wp offset into account for cookie expiration date
        
        $refresh_expire =   current_time('timestamp') + ( $expire - $issued_at );
        
        setcookie( 'jwtrt_refresh_token', $refresh_token, $refresh_expire, "", "", false, true );

        update_user_meta( $user->ID, '_jwtrt_refresh_token', $refresh_token );

        return $response;

    }, 10, 2 );



    // RENEW TOKEN ON REFRESH ENDPOINT
    
    function jwtrt_validate_refresh_token( WP_REST_Request $request ) {
        
        $jwt_auth = new \JWTAuth\Auth;  
        
        $secret_key = defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : false;

        $response = [
            'success'    => false,
            'statusCode' => 403,
            'code'       => 'jwtrt_auth_invalid_request',
            'message'    => __( 'There was a problem with processing the request.', 'jwt-auth' ),
            'data'       => array(),
        ];
        
        if ( ! $secret_key ) {
            $response['code'] = 'jwt_auth_bad_config';
            $response['message'] = __( 'JWT is not configurated properly.', 'jwt-auth' );
            return new WP_REST_Response( $response );
        }
            
        $session_token = isset( $_COOKIE['jwtrt_refresh_token'] ) ? $_COOKIE['jwtrt_refresh_token'] : false;
            
        if ( ! $session_token ) {
            $response['code']       = 'jwtrt_no_refresh_token_in_session';
            $response['message']    = 'There is no Refresh Token in session cookies.';
            return new WP_REST_Response( $response );
        }
    
        try {
            $alg     = $jwt_auth->get_alg();
            $payload = JWT::decode( $session_token, $secret_key, [$alg] );
            
            // Validate the iss in the token
            
            if ( $payload->iss !== $jwt_auth->get_iss() ) {
                $response['code']       = 'jwt_auth_bad_iss';
                $response['message']    = __( 'The iss do not match with this server.', 'jwt-auth' );
                return new WP_REST_Response( $response );
            }
                
            // Check the token for user id
            
            if ( ! isset( $payload->data->user->id ) ) {
                $response['code']       = 'jwt_auth_bad_request';
                $response['message']    = __( 'User ID not found in the token.', 'jwt-auth' );
                return new WP_REST_Response( $response );
            }
                
            // Check the user id in db.
            
            $user = get_user_by( 'id', $payload->data->user->id );
            
            if ( ! $user ) {
                $response['code']       = 'jwt_auth_user_not_found';
                $response['message']    = __( "User doesn't exist", 'jwt-auth' );
                return new WP_REST_Response( $response );
            }
        
            $user_token = get_user_meta( $user->ID, '_jwtrt_refresh_token', true );
            
            if ( ! $user_token ) {
                $response['code']       = 'jwtrt_auth_no_refresh_token_in_db';
                $response['message']    = "There is no Refresh Token for the user in the database.";
                return new WP_REST_Response( $response );
            }

            if ( $user_token !== $session_token ) {
                $response['code']       = 'jwtrt_auth_refresh_token_does_not_match';
                $response['message']    = "Refresh Token doesn't match the one in database.";
                return new WP_REST_Response( $response );
            }

            return $jwt_auth->generate_token( $user , false );
        
        } catch ( Exception $e ) {
            $response['code']       = 'jwt_auth_invalid_token';
            $response['message']    = $e->getMessage();
            return new WP_REST_Response( $response );
        }

    };
    
    
    
    // DON'T AUTHORIZE WHEN PROVIDED REFRESH TOKEN INSTEAD OF NORMAL TOKEN
    
    // If user tried to authorize with Refresh Token instead of Token, abort
    
    add_filter( 'jwt_auth_valid_token_response', function( $response, $user, $token, $payload ) {
        $is_refresh_token = $payload->data->is_refresh_token;
        if( $is_refresh_token ) {
            return array(
                'success'    => false,
                'statusCode' => 403,
                'code'       => 'jwtrt_auth_refresh_token_used_for_auth',
                'message'    => 'Don\'t use Refresh Token for authorization.',
                'data'       => array(),
            );
        }
        return $response;
    }, 10, 4);



    // SEND RESET PASSWORD LINK FOR PROVIDED E-MAIL

    function jwtrt_reset_user_password( WP_REST_Request $request ) {

        $response = [
            'success'=>false,
            'statusCode'=>400,
            'code'=>'jwtrt_auth_resetpass_failed',
            'message'=>'Nie udało się zresetować hasła dla użytkownika.',
            'data'=>[]
        ];

        $email = $request->get_param( 'email' );
        
        if( is_null( $email ) ) {
            $response['code'] = 	'jwtrt_auth_email_not_provided';
            $response['message'] = 	'Nie podano adresu e-mail.';
            return new WP_REST_Response( $response );
        }
        
        if( ! is_email( $email ) ) {
            $response['code'] =		'jwtrt_auth_not_an_email';
            $response['message'] =	'Podany adres e-mail jest błędny.';
            return new WP_REST_Response( $response );
        }
        
        $email = sanitize_email( $email );	
        $user_data = get_user_by( 'email', $email );
        
        if( ! $user_data ) {
            $response['code'] =		'jwt_auth_user_not_found';;
            $response['message'] =	__( "User doesn't exist", 'jwt-auth' );;
            return new WP_REST_Response( $response );
        }

        $errors = jwtrt_retrieve_password( $user_data );
        
        if( $errors instanceof WP_Error && $errors->has_errors() ) {
            $response['code'] = 	$errors->get_error_code();
            $response['message'] = 	strip_tags( $errors->get_error_message() );
            return new WP_REST_Response( $response );
        }
        
        $response['success'] = true;
        $response['statusCode'] = 200;
        $response['code'] = 'jwtrt_auth_reset_password_link_sent';
        $response['message'] = 'Nowe hasło zostało wysłane na podany adres e-mail.';
        return new WP_REST_Response( $response );
        
    }

    function jwtrt_retrieve_password( WP_User $user_data ) {

        $errors = new WP_Error;
        
        if ( ! $user_data ) {
            $errors->add( 'invalidcombo', __( '<strong>Error</strong>: There is no account with that username or email address.' ) );
            return $errors;
        }
        
        $user_login = $user_data->user_login;
        $user_email = $user_data->user_email;
        $key = get_password_reset_key( $user_data );

        if ( is_wp_error( $key ) ) return $key;

        $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    
        $message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
        $message .= sprintf( __( 'Site Name: %s' ), $site_name ) . "\r\n\r\n";
        $message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
        $message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
        $message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
        $message .= network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . "\r\n";

        $title = sprintf( __( '[%s] Password Reset' ), $site_name );

        $title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );
        $message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );
    
        if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
            $errors->add(
                'retrieve_password_email_failure',
                sprintf(
                    __( '<strong>Error</strong>: The email could not be sent. Your site may not be correctly configured to send emails. <a href="%s">Get support for resetting your password</a>.' ),
                    esc_url( __( 'https://wordpress.org/support/article/resetting-your-password/' ) )
                )
            );
            return $errors;
        }
        
        return true;
    }
    
    

    // REGISTER NEW USER
    
    function jwtrt_register_new_user( WP_REST_Request $request ) {

        $username = $request->get_param('username');
        $email = 	$request->get_param('email');
        $password = $request->get_param('password');

        $response = [
            'success'=>false,
            'statusCode'=>403,
            'code'=>'jwtrt_auth_user_not_registered',
            'message'=>'Nie udało się zarejestrować użytkownika.',
            'data'=>[]
        ];
    
        if( is_null( $username ) ) {
            $response['code'] = 'jwtrt_auth_no_username';
            $response['message'] = 'You need to provide username for new account.';
            return new WP_REST_Response( $response );
        }
    
        if( is_null( $password ) || strlen($password) < 8 ) {
            $response['code'] = 'jwtrt_auth_no_password';
            $response['message'] = 'Password should have at leat 8 characters.';
            return new WP_REST_Response( $response );
        }
    
        if( is_null( $email ) || ! is_email( $email ) ) {
            $response['code'] = 'jwtrt_auth_no_email';
            $response['message'] = 'You didn\'t provide valid e-mail address.';
            return new WP_REST_Response( $response );
        }
    
        $user_id = wp_create_user( $username, $password, $email );
    
        if( is_wp_error( $user_id )) {
            $response['statusCode'] = $user_id->get_error_code();
            $response['code'] = 'jwtrt_auth_error_creating_user';
            $response['message'] = $user_id->get_error_message();
            $response['data'] = ['user_id'=>$user_id];
            return new WP_REST_Response( $response );
        }
    
        $user = get_user_by( 'id', $user_id );
    
        return new WP_REST_Response([
            'success'=>true,
            'statusCode'=>201,
            'code'=>'jwtrt_auth_user_created',
            'message'=>'Utworzono nowego użytkownika.',
            'data'=>[
                'id'          => $user->ID,
                'email'       => $user->user_email,
                'nicename'    => $user->user_nicename,
                'firstName'   => $user->first_name,
                'lastName'    => $user->last_name,
                'displayName' => $user->display_name,
            ]
        ]);
    
    }

});  

if( ! function_exists( 'show_pre' ) ) {
    function show_pre( $data ) {
        echo '<pre>';
        print_r( $data );
        echo '</pre>';
    }
}