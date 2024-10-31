<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Name: Webpay Plus Integrapago (de PayForm) 
 * Plugin URI: https://integrapago.cl
 * Description: Integra Webpay Plus y acepta pagos con Tarjetas de Cr&eacute;dito y Redcompra.
 * Version: 2.0.4
 * Author: PayForm
 * Author URI: https://integrapago.cl
**/


$woooptions = get_option('woocommerce_payform_settings');

if ($woooptions == false || (isset($woooptions['payform_secret']) && strlen($woooptions['payform_secret']) < 6) || !isset($woooptions['payform_secret'])) {
    $old_customer = false;
} else {
    $old_customer = true;
}

if (!$old_customer) {



    if (isset($_POST['integrapago_action'])) {

        if (strlen(get_option('integrapago_installation_id')) > 0 && $_POST['integrapago_installation_id'] == get_option('integrapago_installation_id')) {
            
            if (isset($_POST['integrapago_step'])) update_option("integrapago_step", $_POST['integrapago_step']);

            if (isset($_POST['integrapago_certs'])) update_option("integrapago_certs", $_POST['integrapago_certs']);

            ?>
            <script type="text/javascript">
                location.href = "<?php echo $_POST['return_url'];?>";
            </script>
            <?php
            exit;
        }
    }

    if (isset($_GET['integrapago_hide'])) {
        update_option("integrapago_visibility", "no");
        header("Location: " . admin_url());
        exit;
    }

    if (isset($_GET['integrapago_show'])) {
        update_option("integrapago_visibility", "yes");
        header("Location: " . admin_url());
        exit;
    }

    if (in_array('woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) {

        function integrapago_activation_redirect( $plugin ) {
            if( $plugin == plugin_basename( __FILE__ ) ) {
                exit( wp_redirect( admin_url( 'admin.php?page=integrapago-webpay' ) ) );
            }
        }
        add_action( 'activated_plugin', 'integrapago_activation_redirect' );


        if (intval(get_option('integrapago_step')<5)) {

            function integrapago_cert_admin_notice() {
                ?>
                <div class="notice notice-error" style="<?php
                    if(isset($_GET['page']) &&  $_GET['page'] == "integrapago-webpay") echo "display:none;";
                ?>">
                    <p>Tu certificación de <b>Integrapago Transbank Webpay</b> aún no ha sido completada. <a href="admin.php?page=integrapago-webpay">Mas información</a></p>
                </div>
                <?php
            }
            add_action('admin_notices', 'integrapago_cert_admin_notice');

        }

        if (get_option("integrapago_visibility")!=="no") {

            function integrapago_options_panel(){
              add_submenu_page( 'woocommerce', 'Integrapago Webpay', 'Integrapago Webpay', 'manage_options', 'integrapago-webpay', 'integrapago_theme_func_settings');
            }
            add_action('admin_menu', 'integrapago_options_panel');

        }

        function integrapago_theme_func_settings(){

            if (strlen(get_option('integrapago_installation_id')) == 0) {
                update_option("integrapago_installation_id", sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
                    mt_rand( 0, 0xffff ),
                    mt_rand( 0, 0x0fff ) | 0x4000,
                    mt_rand( 0, 0x3fff ) | 0x8000,
                    mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
                ));
            }

            if (strlen(get_option('integrapago_step')) == 0) {
                update_option("integrapago_step", "0");
            }
            ?>
            <iframe id="integrapago_frame" name="integrapago_frame" style="width:calc(100% - 20px); min-height: 100%; border: 0px; overflow: hidden;" src="about:blank"></iframe>
            <form method="post" target="integrapago_frame" id="main_form" action="https://integrapago.cl/index.php/assistant/?uniqid=<?php echo uniqid();?>" align="center">
                <input type="hidden" name="integrapago_step" value="<?php echo get_option('integrapago_step');?>">
                <input type="hidden" name="integrapago_installation_id" value="<?php echo get_option('integrapago_installation_id');?>">
                <input type="hidden" name="email" value="<?php echo get_bloginfo('admin_email');?>">
                <input type="hidden" name="blog_title" value="<?php echo get_bloginfo('title');?>">
                <input type="hidden" name="blog_url" value="<?php echo get_site_url();?>">
                <input type="hidden" name="return_url" id="return_url" value="">
                <script type="text/javascript">
                    document.getElementById('return_url').value = location.href;
                    document.getElementById('main_form').submit();

                    window.addEventListener("message", function(e){
                        var this_frame = document.getElementById("integrapago_frame");
                        if (this_frame.contentWindow === e.source) {
                            this_frame.height = (e.data.height + 100) + "px";
                            this_frame.style.height = (e.data.height + 100) + "px";
                        }
                    });
                </script>
            </form>
            <?php
        }


    } else {

        function integrapago_admin_notice() {
            ?>
            <div class="notice notice-error">
                <p>El plugin de <b>Integrapago para Transbank Webpay</b> requiere de WooCommerce para funcionar. <a href="plugins.php">Activar WooCommerce</a></p>
            </div>
            <?php
        }
        add_action('admin_notices', 'integrapago_admin_notice');

    }


    add_action('plugins_loaded', 'woocommerce_integrapago_init', 0);

    require_once( ABSPATH . "wp-includes/pluggable.php" );

    function woocommerce_integrapago_init()
    {

        require_once( dirname(__FILE__) . '/libwebpay/webpay-soap.php' );
        if (!class_exists("WC_Payment_Gateway")){
            return;
        }

        class WC_Gateway_Integrapago extends WC_Payment_Gateway
        {

            var $notify_url;

            public function __construct()
            {

                $this->id = 'integrapago';
                $this->icon = "https://www.transbank.cl/public/img/Logo_Webpay3-01-50x50.png";
                $this->method_title = __('Integrapago para Webpay Plus');
                $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));

                $this->init_form_fields();
                $this->init_settings();


                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                if (strlen($this->get_option('integrapago_commerce_code'))==0) {
                    $this->config = array(
                        "MODO" => "INTEGRACION",
                        "PRIVATE_KEY" => "-----BEGIN RSA PRIVATE KEY-----
MIIEpQIBAAKCAQEA0ClVcH8RC1u+KpCPUnzYSIcmyXI87REsBkQzaA1QJe4w/B7g
6KvKV9DaqfnNhMvd9/ypmGf0RDQPhlBbGlzymKz1xh0lQBD+9MZrg8Ju8/d1k0pI
b1QLQDnhRgR2T14ngXpP4PIQKtq7DsdHBybFU5vvAKVqdHvImZFzqexbZjXWxxhT
+/sGcD4Vs673fc6B+Xj2UrKF7QyV5pMDq0HCCLTMmafWAmNrHyl6imQM+bqC12gn
EEAEkrJiSO6P/21m9iDJs5KQanpJby0aGW8mocYRHDMHZjtTiIP0+JAJgL9KsH+r
Xdk2bT7aere7TzOK/bEwhkYEXnMMt/65vV6AfwIDAQABAoIBAHnIlOn6DTi99eXl
KVSzIb5dA747jZWMxFruL70ifM+UKSh30FGPoBP8ZtGnCiw1ManSMk6uEuSMKMEF
5iboVi4okqnTh2WSC/ec1m4BpPQqxKjlfrdTTjnHIxrZpXYNucMwkeci93569ZFR
2SY/8pZV1mBkZoG7ocLmq+qwE1EaBEL/sXMvuF/h08nJ71I4zcclpB8kN0yFrBCW
7scqOwTLiob2mmU2bFHOyyjTkGOlEsBQxhtVwVEt/0AFH/ucmMTP0vrKOA0HkhxM
oeR4k2z0qwTzZKXuEZtsau8a/9B3S3YcgoSOhRP/VdY1WL5hWDHeK8q1Nfq2eETX
jnQ4zjECgYEA7z2/biWe9nDyYDZM7SfHy1xF5Q3ocmv14NhTbt8iDlz2LsZ2JcPn
EMV++m88F3PYdFUOp4Zuw+eLJSrBqfuPYrTVNH0v/HdTqTS70R2YZCFb9g0ryaHV
TRwYovu/oQMV4LBSzrwdtCrcfUZDtqMYmmZfEkdjCWCEpEi36nlG0JMCgYEA3r49
o+soFIpDqLMei1tF+Ah/rm8oY5f4Wc82kmSgoPFCWnQEIW36i/GRaoQYsBp4loue
vyPuW+BzoZpVcJDuBmHY3UOLKr4ZldOn2KIj6sCQZ1mNKo5WuZ4YFeL5uyp9Hvio
TCPGeXghG0uIk4emSwolJVSbKSRi6SPsiANff+UCgYEAvNMRmlAbLQtsYb+565xw
NvO3PthBVL4dLL/Q6js21/tLWxPNAHWklDosxGCzHxeSCg9wJ40VM4425rjebdld
DF0Jwgnkq/FKmMxESQKA2tbxjDxNCTGv9tJsJ4dnch/LTrIcSYt0LlV9/WpN24LS
0lpmQzkQ07/YMQosDuZ1m/0CgYEAu9oHlEHTmJcO/qypmu/ML6XDQPKARpY5Hkzy
gj4ZdgJianSjsynUfsepUwK663I3twdjR2JfON8vxd+qJPgltf45bknziYWvgDtz
t/Duh6IFZxQQSQ6oN30MZRD6eo4X3dHp5eTaE0Fr8mAefAWQCoMw1q3m+ai1PlhM
uFzX4r0CgYEArx4TAq+Z4crVCdABBzAZ7GvvAXdxvBo0AhD9IddSWVTCza972wta
5J2rrS/ye9Tfu5j2IbTHaLDz14mwMXr1S4L39UX/NifLc93KHie/yjycCuu4uqNo
MtdweTnQt73lN2cnYedRUhw9UTfPzYu7jdXCUAyAD4IEjFQrswk2x04=
-----END RSA PRIVATE KEY-----",
                    "PUBLIC_CERT" => "-----BEGIN CERTIFICATE-----
MIIDujCCAqICCQCZ42cY33KRTzANBgkqhkiG9w0BAQsFADCBnjELMAkGA1UEBhMC
Q0wxETAPBgNVBAgMCFNhbnRpYWdvMRIwEAYDVQQKDAlUcmFuc2JhbmsxETAPBgNV
BAcMCFNhbnRpYWdvMRUwEwYDVQQDDAw1OTcwMjAwMDA1NDExFzAVBgNVBAsMDkNh
bmFsZXNSZW1vdG9zMSUwIwYJKoZIhvcNAQkBFhZpbnRlZ3JhZG9yZXNAdmFyaW9z
LmNsMB4XDTE2MDYyMjIxMDkyN1oXDTI0MDYyMDIxMDkyN1owgZ4xCzAJBgNVBAYT
AkNMMREwDwYDVQQIDAhTYW50aWFnbzESMBAGA1UECgwJVHJhbnNiYW5rMREwDwYD
VQQHDAhTYW50aWFnbzEVMBMGA1UEAwwMNTk3MDIwMDAwNTQxMRcwFQYDVQQLDA5D
YW5hbGVzUmVtb3RvczElMCMGCSqGSIb3DQEJARYWaW50ZWdyYWRvcmVzQHZhcmlv
cy5jbDCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBANApVXB/EQtbviqQ
j1J82EiHJslyPO0RLAZEM2gNUCXuMPwe4OirylfQ2qn5zYTL3ff8qZhn9EQ0D4ZQ
Wxpc8pis9cYdJUAQ/vTGa4PCbvP3dZNKSG9UC0A54UYEdk9eJ4F6T+DyECrauw7H
RwcmxVOb7wClanR7yJmRc6nsW2Y11scYU/v7BnA+FbOu933Ogfl49lKyhe0MleaT
A6tBwgi0zJmn1gJjax8peopkDPm6gtdoJxBABJKyYkjuj/9tZvYgybOSkGp6SW8t
GhlvJqHGERwzB2Y7U4iD9PiQCYC/SrB/q13ZNm0+2nq3u08ziv2xMIZGBF5zDLf+
ub1egH8CAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAdgNpIS2NZFx5PoYwJZf8faze
NmKQg73seDGuP8d8w/CZf1Py/gsJFNbh4CEySWZRCzlOKxzmtPTmyPdyhObjMA8E
Adps9DtgiN2ITSF1HUFmhMjI5V7U2L9LyEdpUaieYyPBfxiicdWz2YULVuOYDJHR
n05jlj/EjYa5bLKs/yggYiqMkZdIX8NiLL6ZTERIvBa6azDKs6yDsCsnE1M5tzQI
VVEkZtEfil6E1tz8v3yLZapLt+8jmPq1RCSx3Zh4fUkxBTpUW/9SWUNEXbKK7bB3
zfB3kGE55K5nxHKfQlrqdHLcIo+vdShATwYnmhUkGxUnM9qoCDlB8lYu3rFi9w==
-----END CERTIFICATE-----",
                    "WEBPAY_CERT" => "-----BEGIN CERTIFICATE-----
MIIDKTCCAhECBFZl7uIwDQYJKoZIhvcNAQEFBQAwWTELMAkGA1UEBhMCQ0wxDjAMBgNVBAgMBUNo
aWxlMREwDwYDVQQHDAhTYW50aWFnbzEMMAoGA1UECgwDa2R1MQwwCgYDVQQLDANrZHUxCzAJBgNV
BAMMAjEwMB4XDTE1MTIwNzIwNDEwNloXDTE4MDkwMjIwNDEwNlowWTELMAkGA1UEBhMCQ0wxDjAM
BgNVBAgMBUNoaWxlMREwDwYDVQQHDAhTYW50aWFnbzEMMAoGA1UECgwDa2R1MQwwCgYDVQQLDANr
ZHUxCzAJBgNVBAMMAjEwMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAizJUWTDC7nfP
3jmZpWXFdG9oKyBrU0Bdl6fKif9a1GrwevThsU5Dq3wiRfYvomStNjFDYFXOs9pRIxqX2AWDybjA
X/+bdDTVbM+xXllA9stJY8s7hxAvwwO7IEuOmYDpmLKP7J+4KkNH7yxsKZyLL9trG3iSjV6Y6SO5
EEhUsdxoJFAow/h7qizJW0kOaWRcljf7kpqJAL3AadIuqV+hlf+Ts/64aMsfSJJA6xdbdp9ddgVF
oqUl1M8vpmd4glxlSrYmEkbYwdI9uF2d6bAeaneBPJFZr6KQqlbbrVyeJZqmMlEPy0qPco1TIxrd
EHlXgIFJLyyMRAyjX9i4l70xjwIDAQABMA0GCSqGSIb3DQEBBQUAA4IBAQBn3tUPS6e2USgMrPKp
sxU4OTfW64+mfD6QrVeBOh81f6aGHa67sMJn8FE/cG6jrUmX/FP1/Cpbpvkm5UUlFKpgaFfHv+Kg
CpEvgcRIv/OeIi6Jbuu3NrPdGPwzYkzlOQnmgio5RGb6GSs+OQ0mUWZ9J1+YtdZc+xTga0x7nsCT
5xNcUXsZKhyjoKhXtxJm3eyB3ysLNyuL/RHy/EyNEWiUhvt1SIePnW+Y4/cjQWYwNqSqMzTSW9TP
2QR2bX/W2H6ktRcLsgBK9mq7lE36p3q6c9DtZJE+xfA4NGCYWM9hd8pbusnoNO7AFxJZOuuvLZI7
JvD7YLhPvCYKry7N6x3l
-----END CERTIFICATE-----",
                        "CODIGO_COMERCIO" => "597020000541",
                        "URL_RETURN" => home_url('/') . '?wc-api=WC_Gateway_' . $this->id,
                        "URL_FINAL" => "_URL_",
                        "VENTA_DESC" => array(
                            "VD" => "Venta Deb&iacute;to",
                            "VN" => "Venta Normal",
                            "VC" => "Venta en cuotas",
                            "SI" => "3 cuotas sin inter&eacute;s",
                            "S2" => "2 cuotas sin inter&eacute;s",
                            "NC" => "N cuotas sin inter&eacute;s",
                        ),
                    );
                } else {

                    $certificados = get_option('integrapago_certs');

                    $this->config = array(
                        "MODO" => $certificados["MODO"],
                        "PRIVATE_KEY" => $certificados["PRIVATE_KEY"],
                        "PUBLIC_CERT" => $certificados["PUBLIC_CERT"],
                        "WEBPAY_CERT" => $certificados["WEBPAY_CERT"],
                        "CODIGO_COMERCIO" => $certificados["CODIGO_COMERCIO"],
                        "URL_RETURN" => home_url('/') . '?wc-api=WC_Gateway_' . $this->id,
                        "URL_FINAL" => "_URL_",
                        "VENTA_DESC" => array(
                            "VD" => "Venta Deb&iacute;to",
                            "VN" => "Venta Normal",
                            "VC" => "Venta en cuotas",
                            "SI" => "3 cuotas sin inter&eacute;s",
                            "S2" => "2 cuotas sin inter&eacute;s",
                            "NC" => "N cuotas sin inter&eacute;s",
                        ),
                    );
                }

                add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_ipn_response'));

                if (!$this->is_valid_for_use()) {
                    $this->enabled = false;
                }
            }


            function is_valid_for_use()
            {
                if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')))) {
                    return false;
                }
                return true;
            }


            function init_form_fields()
            {

                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Activar/Desactivar', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Activar Webpay Plus', 'woocommerce'),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('T&iacute;tulo', 'woocommerce'),
                        'type' => 'text',
                        'default' => __('Pago con Webpay Plus', 'woocommerce')
                    ),
                    'description' => array(
                        'title' => __('Descripci&oacute;n', 'woocommerce'),
                        'type' => 'textarea',
                        'default' => __('Pague con tarjetas de débito o crédito usando Webpay Plus', 'woocommerce')
                    )
                );
            }


            function receipt_page($order)
            {
                echo $this->generate_integrapago_payment($order);
            }


            function check_ipn_response()
            {
                @ob_clean();

                if (isset($_POST)) {
                    header('HTTP/1.1 200 OK');
                    $this->check_ipn_request_is_valid($_POST);
                } else {
                    echo "Ocurrio un error al procesar su Compra";
                }
            }


            public function check_ipn_request_is_valid($data)
            {

                $voucher = false;

                try {

                    if (isset($data["token_ws"])) {
                        $token_ws = $data["token_ws"];
                    } else {
                        $token_ws = 0;
                    }

                    $webpay = new WebPaySoap($this->config);
                    $result = $webpay->webpayNormal->getTransactionResult($token_ws);

                } catch (Exception $e) {

                    $result["error"] = "Error conectando a Webpay";
                    $result["detail"] = $e->getMessage();

                }

                $order_info = new WC_Order($result->buyOrder);

                WC()->session->set($order_info->order_key, $result);

                if ($result->buyOrder && $order_info) {

                    if (($result->VCI == "TSY" || $result->VCI == "") && $result->detailOutput->responseCode == 0) {

                        $voucher = true;
                        WC()->session->set($order_info->order_key . "_transaction_paid", 1);

                        WebPaySOAP::redirect($result->urlRedirection, array("token_ws" => $token_ws));

                        $order_info->add_order_note(__('Pagado con Webpay Plus', 'woocommerce'));
                        $order_info->update_status('completed');
                        $order_info->reduce_order_stock();

                    } else {

                        $responseDescription = htmlentities($result->detailOutput->responseDescription);
                    }
                }

                if (!$voucher) {

                    $date = new DateTime($result->transactionDate);

                    WC()->session->set($order_info->order_key, "");

                    $error_message = "Estimado cliente, le informamos que su orden número ". $result->buyOrder . ", realizada el " . $date->format('d-m-Y H:i:s') . " termin&oacute; de forma inesperada ( " . $responseDescription . " ) ";
                    wc_add_notice(__('ERROR: ', 'woothemes') . $error_message, 'error');

                    $redirectOrderReceived = $order_info->get_checkout_payment_url();
                    WebPaySOAP::redirect($redirectOrderReceived, array("token_ws" => $token_ws));
                }

                die;
            }


            function generate_integrapago_payment($order_id)
            {

                $order = new WC_Order($order_id);
                $amount = (int) number_format($order->get_total(), 0, ',', '');

                $urlFinal = str_replace("_URL_", add_query_arg('key', $order->order_key, $order->get_checkout_order_received_url()), $this->config["URL_FINAL"]);

                try {

                    $webpay = new WebPaySoap($this->config);
                    $result = $webpay->webpayNormal->initTransaction($amount, $sessionId = "", $order_id, $urlFinal);

                } catch (Exception $e) {

                    $result["error"] = "Error conectando a Webpay";
                    $result["detail"] = $e->getMessage();
                }

                if (isset($result["token_ws"])) {

                    $url = $result["url"];
                    $token = $result["token_ws"];

                    return '<form action="' . $url . '" method="post">' .
                            '<input type="hidden" name="token_ws" value="' . $token . '"></input>' .
                            '<button type="submit">Pagar orden usando Webpay Plus</button>' .
                            '</form>';
                } else {

                    wc_add_notice(__('ERROR: ', 'woothemes') . 'Ocurri&oacute; un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.<br/>', 'error');
                }
            }


            function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
            }


            public function admin_options()
            {
                ?>
                <h3><?php _e('Integrapago para Webpay Plus', 'woocommerce'); ?></h3>
                <p><?php _e('Desde ahora la integración y certificación de Webpay es mucho mas facil con Integrapago de PayForm. Para mas información sobre Integrapago visite <a href="https://integrapago.cl" target="_new">nuestro sitio web</a>.'); ?></p>

                <?php if ($this->is_valid_for_use()) : ?>

                    <table class="form-table">
                    <?php
                    $this->generate_settings_html();
                    ?>
                    </table>

                <?php else : ?>
                    <div class="inline error">
                        <p>
                            <strong><?php _e('Webpay Plus', 'woocommerce');
                    ?></strong>: <?php _e('Este plugin solo soporta tiendas en pesos chilenos', 'woocommerce');
                    ?>
                        </p>
                    </div>
                <?php
                endif;
            }

        }


        function woocommerce_add_integrapago_gateway($methods)
        {
            $methods[] = 'WC_Gateway_Integrapago';
            return $methods;
        }


        function pay_content($order_id)
        {
            $order_info = new WC_Order($order_id);
            $integrapago_data = new WC_Gateway_Integrapago;

            if ($order_info->payment_method_title == $integrapago_data->title) {

                if (WC()->session->get($order_info->order_key . "_transaction_paid") == "" && WC()->session->get($order_info->order_key) == "") {

                    wc_add_notice(__('Compra <strong>Anulada</strong>', 'woocommerce') . ' por usuario. Recuerda que puedes pagar o
                        cancelar tu compra cuando lo desees desde <a href="' . wc_get_page_permalink('myaccount') . '">' . __('Tu Cuenta', 'woocommerce') . '</a>', 'error');
                    wp_redirect($order_info->get_checkout_payment_url());

                    die;
                }

            } else {
                return;
            }

            $finalResponse = WC()->session->get($order_info->order_key);
            WC()->session->set($order_info->order_key, "");

            $paymentTypeCode = $finalResponse->detailOutput->paymentTypeCode;
            $paymenCodeResult = $integrapago_data->config['VENTA_DESC'][$paymentTypeCode];

            if ($finalResponse->detailOutput->responseCode == 0) {
                $transactionResponse = "Aceptado";
            } else {
                $transactionResponse = "Rechazado [" . $finalResponse->detailOutput->responseCode . "]";
            }

            $date_accepted = new DateTime($finalResponse->transactionDate);

            if ($finalResponse != null) {

                echo '</br><h2>Detalles del pago</h2>' .
                '<table class="shop_table order_details">' .
                '<tfoot>' .
                '<tr>' .
                '<th scope="row">Respuesta de la Transacci&oacute;n:</th>' .
                '<td><span class="RT">' . $transactionResponse . '</span></td>' .
                '</tr>' .
                '<tr>' .
                '<th scope="row">Orden de Compra:</th>' .
                '<td><span class="RT">' . $finalResponse->buyOrder . '</span></td>' .
                '</tr>' .
                '<tr>' .
                '<th scope="row">Codigo de Autorizaci&oacute;n:</th>' .
                '<td><span class="CA">' . $finalResponse->detailOutput->authorizationCode . '</span></td>' .
                '</tr>' .
                '<tr>' .
                '<th scope="row">Fecha Transacci&oacute;n:</th>' .
                '<td><span class="FC">' . $date_accepted->format('d-m-Y') . '</span></td>' .
                '</tr>' .
                '<tr>' .
                '<th scope="row"> Hora Transacci&oacute;n:</th>' .
                '<td><span class="FT">' . $date_accepted->format('H:i:s') . '</span></td>' .
                '</tr>' .
                '<tr>' .
                '<th scope="row">Tarjeta de Cr&eacute;dito:</th>' .
                '<td><span class="TC">************' . $finalResponse->cardDetail->cardNumber . '</span></td>' .
                '</tr>' .
                '<tr>' .
                '<th scope="row">Tipo de Pago:</th>' .
                '<td><span class="TP">' . $paymenCodeResult . '</span></td>' .
                '</tr>' .
                '<tr>' .
                '<th scope="row">Monto Compra:</th>' .
                '<td><span class="amount">' . $finalResponse->detailOutput->amount . '</span></td>' .
                '</tr>' .
                '<tr>' .
                '<th scope="row">N&uacute;mero de Cuotas:</th>' .
                '<td><span class="NC">' . $finalResponse->detailOutput->sharesNumber . '</span></td>' .
                '</tr>' .
                '</tfoot>' .
                '</table><br/>';
            }
        }

        add_action('woocommerce_thankyou', 'pay_content', 1);
        add_filter('woocommerce_payment_gateways', 'woocommerce_add_integrapago_gateway');
    }


} else {

    // OLD PLUGIN. ONLY HERE FOR LEGACY REASONS

    if ( 
      in_array( 
        'woocommerce/woocommerce.php', 
        apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) 
      ) 
    ) {

        function pf_activation_redirect( $plugin ) {
            if( $plugin == plugin_basename( __FILE__ ) ) {
                exit( wp_redirect( admin_url( 'admin.php?page=woocommerce-webpay' ) ) );
            }
        }
        add_action( 'activated_plugin', 'pf_activation_redirect' );

        function theme_options_panel(){
          add_submenu_page( 'woocommerce', 'PayForm (Webpay Transbank)', 'PayForm (Webpay Transbank)', 'manage_options', 'woocommerce-webpay', 'wps_theme_func_settings');
        }
        add_action('admin_menu', 'theme_options_panel');

        function wps_theme_func_settings(){
            $woooptions = get_option('woocommerce_payform_settings');

            if ($woooptions == false || (isset($woooptions['payform_secret']) && strlen($woooptions['payform_secret']) < 6) || !isset($woooptions['payform_secret'])) {
                $configured = false;
            } else {
                $configured = true;
            }

            if (isset($_GET['easyname']) && isset($_GET['key'])) {

                update_option( 'woocommerce_payform_settings', array(
                    "enabled" => "yes",
                    "title" => "Pago via Webpay",
                    "description" => "Pago mediante tarjetas de crédito y débito",
                    "payform_name" => $_GET['easyname'],
                    "payform_secret" => $_GET['key']
                ));

                $configured = true;
            }

            ?>

            <style type="text/css">
                .firstblock {
                    background-color: #1b6999;
                    color: white;
                    padding: 20px;
                }
                .firstblock h1 {
                    color: white;
                    font-weight: lighter;
                    font-size: 30px;
                    text-align: center;
                }


                .firstblock p.infotext{
                    color: white;
                    font-size: 14px;
                }

                .payform-input {
                    width: 100%;
                    height: 40px;
                    height: 34px;
                    padding: 9px 15px;
                    font-size: 16px;
                    line-height: 1.42857143;
                    color: #555;
                    background-color: #fff;
                    background-image: none;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
                    box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
                }
                .payform-btn {
                    padding: 8px 13px;
                    border-radius: 4px;
                    font-size: 18px;
                    margin-top: 3px;
                    background-color: #e97d68;
                    border: 0px;
                    color: white;
                    cursor: pointer;
                    text-decoration: none;
                }
                .payform-btn:hover {
                    color: white;
                }

                .payform-blue-btn {
                    padding: 14px 13px;
                    border-radius: 4px;
                    font-size: 16px;
                    margin-top: 3px;
                    background-color: #1b6999;
                    border: 0px;
                    color: white;
                    text-decoration: none;
                    margin-top: 10px;
                    display: inline-block;
                    width: 150px;
                }
                .payform-blue-btn:hover {
                    color: white;
                }

                .secondblock {
                    background-color: white;
                    padding-top: 20px;
                    padding-left: 20px;
                    padding-right: 20px;
                    vertical-align:top;
                    text-align: center;
                }

                .secondblock .help {
                    width: calc(100% - 400px);
                    padding: 20px;
                    vertical-align:top;
                    padding-top: 30px;
                    text-align: center;
                    display: inline-block;
                }

                .secondblock .help h2{
                    font-weight: lighter;
                    font-size: 26px;
                    color: #222;
                }

                @media screen and (max-width: 700px) {
                   .secondblock .help {
                        width: calc(100% - 20px);
                   }
                }
            </style>
            <div class="wrap">
                <div id="payform-configured" class="firstblock" style="<?php if(!$configured) echo "display: none;";?>">
                    <div align="right">
                        <img src="https://payform.me/img/payform-white.png" style="height: 30px;">
                    </div>
                    <br>
                    <br>
                    <h1>
                        ¡Felicitaciones! Tu plugin se encuentra configurado
                    </h1>
                    <p class="infotext" align="center">
                        
                        Ya estás aceptando pagos mediante Transbank Webpay usando PayForm.<br>
                        Puedes configurar otras opciones en las <a href="admin.php?page=wc-settings&tab=checkout&section=payform" style="color: white; font-weight: bold;">opciones avanzadas.</a>

                        <br>
                        <br>
                        <small><a href="https://manage.payform.me/<?php if(isset($woooptions['payform_name'])) echo $woooptions['payform_name'];?>/#!/transactions" target="_new" class="payform-btn" type="submit">Ver mi detalle de pagos y transacciones</a></small>
                        <br>
                        <br>
                    </p>

                </div>
                <div id="payform-nonconfigured" class="firstblock" style="<?php if($configured) echo "display: none;";?>">
                    <div align="right">
                        <img src="https://payform.me/img/payform-white.png" style="height: 30px;">
                    </div>
                    <br>
                    <br>
                    <h1>
                        Bienvenido a PayForm
                    </h1>
                    <p class="infotext" align="center">
                        
                        Comenzar su integración de PayForm para aceptar <b>Transbank Webpay</b> en WooCommerce es muy fácil y no tomará mas de un minuto.<br>
                        Puede revisar nuestras tarifas vigentes en nuestra <a target="_new" href="https://payform.me/pricing" style="color: white; font-weight: bold;">página de precios</a>.

                        <br>
                        <form style="margin-top: 30px;" method="post" action="https://payform.me/plugins/config" align="center">
                            <input name="email" class="payform-input" style="max-width: 400px;" value="<?php echo get_bloginfo('admin_email');?>" placeholder="Ingrese su email">
                            <input type="hidden" name="blog_title" value="<?php echo get_bloginfo('title');?>">
                            <input type="hidden" name="blog_url" value="<?php echo get_site_url();?>">
                            <input type="hidden" id="return_url" name="return_url" value="">
                            <script type="text/javascript">document.getElementById('return_url').value = location.href;</script>
                            <button class="payform-btn" type="submit">Comenzar</button>
                            <br>
                            <small style="margin-top: 10px; display: block">Al continuar aceptas nuestros <a href="https://payform.me/tos" target="_new" style="color:white;">Términos de Servicio</a> y nuestra <a href="https://payform.me/privacy" target="_new" style="color:white;">Política de Privacidad</a></small>
                        </form>
                        <br>
                        <br>
                    </p>

                </div>
                <div class="secondblock">
                    
                    <div class="help" align="center">
                        <h2>¿Necesitas ayuda?</h2>
                        <p style="max-width: 500px; display: inline-block;">Si tienes alguna duda acerca de nuestros productos, pasarelas de pago o deseas obtener mas información, no dudes en contactarnos.</p>
                        <br>
                        <a href="https://payform.me/cl/#call" target="_new" class="payform-blue-btn">Te llamamos <span class="dashicons dashicons-phone"></span></a>
                    </div><img src="https://payform.me/img/nicolas.png" style="width: 250px;">
                </div>
            </div>
            
            <?php
        }

        function pf_cleanup_number($number) {

            $number = preg_replace("/[^0-9,.]/", "", $number);

            preg_match_all('/([0-9]+)/', $number, $num, PREG_PATTERN_ORDER);
            preg_match_all('/([^0-9]{1})/', $number, $sep, PREG_PATTERN_ORDER);
            if (count($sep[0]) == 0) {
                // no separator, integer
                return (int) $num[0][0];
            }
            elseif (count($sep[0]) == 1) {
                // one separator, look for last number column
                if (strlen($num[0][1]) == 3) {
                    if (strlen($num[0][0]) <= 3) {
                        // treat as thousands seperator
                        return (int) ($num[0][0] * 1000 + $num[0][1]);
                    }
                    elseif (strlen($num[0][0]) > 3) {
                        // must be decimal point
                        return (float) ($num[0][0] + $num[0][1] / 1000);
                    }
                }
                else {
                    // must be decimal point
                    return (float) ($num[0][0] + $num[0][1] / pow(10,strlen($num[0][1])));
                }
            }
            else {
                // multiple separators, check first an last
                if ($sep[0][0] == end($sep[0])) {
                    // same character, only thousands separators, check well-formed nums
                    $value = 0;
                    foreach($num[0] AS $p => $n) {
                        if ($p == 0 && strlen($n) > 3) {
                            return -1; // malformed number, incorrect thousands grouping
                        }
                        elseif ($p > 0 && strlen($n) != 3) {
                            return -1; // malformed number, incorrect thousands grouping
                        }
                        $value += $n * pow(10, 3 * (count($num[0]) - 1 - $p));
                    }
                    return (int) $value;
                }
                else {
                    // mixed characters, thousands separators and decimal point
                    $decimal_part = array_pop($num[0]);
                    $value = 0;
                    foreach($num[0] AS $p => $n) {
                        if ($p == 0 && strlen($n) > 3) {
                            return -1; // malformed number, incorrect thousands grouping
                        }
                        elseif ($p > 0 && strlen($n) != 3) {
                            return -1; // malformed number, incorrect thousands grouping
                        }
                        $value += $n * pow(10, 3 * (count($num[0]) - 1 - $p));
                    }
                    return (float) ($value + $decimal_part / pow(10,strlen($decimal_part)));
                }
            }
        }


        function sample_admin_notice__success() {
            $woooptions = get_option('woocommerce_payform_settings');
            
            if ($woooptions == false || (isset($woooptions['payform_secret']) && strlen($woooptions['payform_secret']) < 6) || !isset($woooptions['payform_secret'])) {
            
            ?>
            <div class="notice notice-error" style="<?php
                if(isset($_GET['page']) &&  $_GET['page'] == "woocommerce-webpay") echo "display:none;";
            ?>">
                <p>El plugin de <b>PayForm para Transbank Webpay</b> aún no ha sido configurado. <a href="admin.php?page=woocommerce-webpay">Configúralo ahora</a></p>
                
            </div>
            <?php
            }
        }
        add_action('admin_notices', 'sample_admin_notice__success');

        add_action('plugins_loaded', 'woocommerce_payform_init', 0);

        function woocommerce_payform_init()
        {

            class WC_Gateway_PayForm extends WC_Payment_Gateway
            {

                public function __construct()
                {
                    $this->id = 'payform';
                    $this->icon = plugins_url('images/buttons/50x25.png', __FILE__);
                    $this->has_fields = false;
                    $this->method_title = __('PayForm [CL] para WooCommerce', 'woocommerce');
                    $this->notify_url = add_query_arg('wc-api', 'wc_gateway_payform', home_url('/') . "index.php");
                    $this->init_form_fields();
                    $this->init_settings();
                    $this->title = $this->get_option('title');
                    $this->description = $this->get_option('description');
                    $this->payform_name = $this->get_option('payform_name');
                    $this->payform_secret = $this->get_option('payform_secret');
                    
                    $this->payform_error = false;
                    if ($this->get_option('woocommerce_payform_receiver_valid', "true") == "false") {
                        $this->payform_error = "Debe indicar el nombre de su PayForm";
                        $this->update_option('woocommerce_payform_receiver_valid', "true");
                    }
                    if ($this->get_option('woocommerce_payform_secret_valid', "true") == "false") {
                        $this->payform_error = "Su Secret Key no es válida";
                        $this->update_option('woocommerce_payform_secret_valid', "true");
                    }

                    // Hooks
                    add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                    

                    add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
                    /** Detecting WC version **/
                    if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
                      add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
                    } else {
                      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                    }

                    add_action('woocommerce_api_wc_gateway_payform', array($this, 'check_ipn'));
                    
                }
                


                public function admin_options()
                {
                    ?>
                    
                    <h3><?php _e('PayForm para WooCommerce', 'woocommerce'); ?></h3>
                    <p><?php _e('Utilice nuestro Plugin para WooCommerce de forma de conectar su PayForm y recibir pagos mediante Webpay en su tienda. Este plugin es exclusivo para Chile', 'woocommerce'); ?></p>


                    <?php if ($this->payform_error !== false) { ?>
                    <div class="inline error">
                        <p>
                            <strong><?php echo $this->payform_error; ?></strong>
                        </p>
                    </div>
                    <?php } ?>

                    <table class="form-table">
                        <?php $this->generate_settings_html();?>
                    </table>
                    
                <?php
                }

                function init_form_fields()
                {
                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => 'Activar/Desactivar',
                            'type' => 'checkbox',
                            'label'   => __( 'Enable PayForm', 'woocommerce' ),
                            'default' => 'yes'
                        ),
                        'title' => array(
                            'title' => __('Title', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                            'default' => __('Pago via Webpay', 'woocommerce'),
                            'desc_tip' => true
                        ),
                        'description' => array(
                            'title' => __('Description', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                            'default' => __('Pago mediante tarjetas de crédito y débito')
                        ),
                        'payform_name' => array(
                            'title' => __('Nombre de PayForm', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('Ingrese el nombre de su PayForm (Lo que va después de <i>https://payform.cl/</i>)', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false
                        ),
                        'payform_secret' => array(
                            'title' => __('Secret Key de PayForm', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('Ingrese su Secret Ket de PayForm (La puede encontrar en la sección <i>Compartir</i> del Dashboard de PayForm', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false
                        )
                    );

                }

                function process_payment($order_id)
                {

                    $order = new WC_Order($order_id);
                    return array(
                        'result' => 'success',
                        'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
                    );
                }

                function receipt_page($order_id)
                {
                    
                    $order = new WC_Order($order_id);
                    $order_item = $order->get_items();
                    $catchid = 0;
                    foreach($order_item as $item) {
                        $catchid = ($item['product_id']);
                    }
                    $recurrence = get_post_meta($catchid, '_payform_recurrence', true );
                    if (!$recurrence) $recurrence = "none";

                    $payform_name = $this->get_option('payform_name');
                    $order_id = str_replace('#', '', $order->get_order_number());
                    
                    $amount = pf_cleanup_number($order->get_total());
                    
                    if ( version_compare( WOOCOMMERCE_VERSION, '2.7', '<' ) ) {
                        $currency = strtoupper($order->get_order_currency());
                    } else {
                        $currency = strtoupper($order->get_currency());
                    }
                    
                    $first_name = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_first_name : $order->get_billing_first_name(); 
                    $last_name  = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_last_name : $order->get_billing_last_name(); 
                    $email      = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_email : $order->get_billing_email(); 
                    $payform_url_exito = $this->get_return_url($order);
                    $payform_url_fracaso = str_replace('&amp;', '&', $order->get_cancel_order_url());
                    $payform_url_ipn =  $this->notify_url;

                    if ($currency=="CLP") {
                        
                        ?>
                            <form method="post" action="https://api.payform.me/pay">
                                <input type="hidden" name="business" value="<?php echo $payform_name;?>">
                                <input type="hidden" name="customer_email" value="<?php echo $email;?>">
                                <input type="hidden" name="customer_firstname" value="<?php echo $first_name;?>">
                                <input type="hidden" name="customer_lastname" value="<?php echo $last_name;?>">
                                <input type="hidden" name="trx_id" value="<?php echo $order_id;?>">
                                <input type="hidden" name="item_name" value="<?php echo get_bloginfo('name') . " | Orden Nº" . $order_id;?>">
                                <input type="hidden" name="amount" value="<?php echo $amount;?>">
                                <input type="hidden" name="currency_code" value="<?php echo $currency;?>">
                                <input type="hidden" name="recurrence" value="<?php echo $recurrence;?>">
                                <input type="hidden" name="return" value="<?php echo $payform_url_exito;?>">
                                <input type="hidden" name="fail_return" value="<?php echo $payform_url_fracaso;?>">
                                <input type="hidden" name="ipn" value="<?php echo $payform_url_ipn;?>">
                                <button type="submit">Pagar con WebPay</button>
                            </form>
                        <?php

                    } else {
                        echo "Este medio de pago solo acepta órdenes en CLP";
                    }
                }

                
                function check_ipn()
                {

                    $order_id = sanitize_text_field($_POST['trx_id']);
                    $event = sanitize_text_field($_POST['event']);
                    $hash = sanitize_text_field($_POST['hash']);
                    $amount = pf_cleanup_number(sanitize_text_field($_POST['amount']));
                    $currency = strtoupper(sanitize_text_field($_POST['currency']));
                    $payform_secret = $this->payform_secret;

                    if (is_null($payform_secret) || $payform_secret == false || strlen($payform_secret)==0) {
                        $payform_secret = "00e608dc-5491-4103-87cd-a7d0027f03eb";
                    }

                    $payform_id = sanitize_text_field($_POST['id']);
                    $payform_sub_id = sanitize_text_field($_POST['subscription_id']);

                    $calchash = md5($payform_id . $payform_secret . $payform_sub_id . $amount . $currency);
                    
                    $order = new WC_Order($order_id);
                    
                    if ($order) {
                        
                        $order_woo = pf_cleanup_number($order->get_total());
                        if ( version_compare( WOOCOMMERCE_VERSION, '2.7', '<' ) ) {
                            $currency_woo = strtoupper($order->get_order_currency());
                        } else {
                            $currency_woo = strtoupper($order->get_currency());
                        }
                        

                        if ($order_woo == $amount && $currency_woo == $currency && $hash == $calchash) {
                            if ($event == 'approved') {
                                $order->add_order_note(__('Pago aprobado', 'woocommerce'));
                                $order->payment_complete();
                            } else {
                                $order->update_status( 'failed',  __( 'Pago rechazado', 'woocommerce' ));
                            }
                        
                        }
                    }

                    exit;

                }

                //

            }



            /**
             * Add the Gateway to WooCommerce
             **/
            function woocommerce_add_payform_gateway($methods)
            {
                $methods[] = 'WC_Gateway_PayForm';
                return $methods;
            }

            add_filter('woocommerce_payment_gateways', 'woocommerce_add_payform_gateway');

            add_action( 'woocommerce_product_options_general_product_data', 'wc_custom_add_custom_fields' );
            function wc_custom_add_custom_fields() {
                woocommerce_wp_select(
                    array( 
                        'id'          => '_payform_recurrence', 
                        'label'       => __( 'Recurrencia de PayForm', 'woocommerce' ), 
                        'description' => __( 'Esta es la recurrencia de este producto o servicio. El carro de compras solo aceptará productos con la misma recurrencia.', 'woocommerce' ),
                        'desc_tip' => 'true',
                        'options' => array(
                            'none'   => __( 'Sin recurrencia', 'woocommerce' ),
                            '1 month'   => __( 'Mensual', 'woocommerce' ),
                            '3 months' => __( 'Cada 3 meses', 'woocommerce' ),
                            '6 months' => __( 'Cada 6 meses', 'woocommerce' ),
                            '1 year' => __( 'Cada año', 'woocommerce' ),
                            '2 years' => __( 'Cada 2 años', 'woocommerce' )
                        )
                    )
                );
            }

            add_action( 'woocommerce_process_product_meta', 'payform_custom_save_custom_fields' );
            function payform_custom_save_custom_fields( $post_id ) {
                if ( ! empty( $_POST['_payform_recurrence'] ) ) {
                    
                    $valid_values = array(
                       'none',
                       '1 month',
                       '3 months',
                       '6 months',
                       '1 year',
                       '2 years'
                    );

                    $recurrence_value = sanitize_text_field( $_POST['_payform_recurrence'] );
                    if( in_array( $recurrence_value, $valid_values ) ) {
                        update_post_meta( $post_id, '_payform_recurrence', $recurrence_value );
                    }
                }
            }

            function so_validate_add_cart_item( $passed, $product_id, $quantity, $variation_id = '', $variations= '' ) {
                
                global $woocommerce;
                
                $items = $woocommerce->cart->get_cart();

                if (count($items)==0) return $passed;

                $new_recurrence = get_post_meta($product_id, '_payform_recurrence', true );
                $has_removed = false;
                foreach($items as $item => $values) { 
                    $old_recurrence = get_post_meta($values['product_id'], '_payform_recurrence', true );
                    if (!$old_recurrence) $old_recurrence = "none";
                    if ($old_recurrence !== $new_recurrence) {
                        $woocommerce->cart->remove_cart_item($item);
                        $has_removed = true;
                    }
                }

                if ($has_removed) wc_add_notice( __( 'Hemos actualizado su carro de compras debido a que algunos productos no son compatibles entre si', 'textdomain' ), 'error' );
                return $passed;

            }
            //add_filter( 'woocommerce_add_to_cart_validation', 'so_validate_add_cart_item', 10, 5 );


        }

    } else {

        function sample_admin_notice__success() {
            ?>
            <div class="notice notice-error">
                <p>El plugin de <b>PayForm para Transbank Webpay</b> requiere de WooCommerce para funcionar. <a href="plugins.php">Activar WooCommerce</a></p>
                
            </div>
            <?php
        }
        add_action('admin_notices', 'sample_admin_notice__success');
    }


}
