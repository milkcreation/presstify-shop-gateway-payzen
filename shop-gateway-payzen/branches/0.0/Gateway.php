<?php

namespace tiFy\Plugins\ShopGatewayPayzen;

use tiFy\Plugins\Shop\Gateways\AbstractGateway;
use tiFy\Plugins\Shop\Orders\OrderInterface;
use tiFy\Plugins\Shop\Shop;

final class Gateway extends AbstractGateway
{
    /**
     * Classe de rappel de gestion des paramètres de requête PayZen
     *
     * @var \PayzenRequest
     */
    protected $request;

    /**
     * CONSTRUCTEUR
     *
     * @param Shop $shop Classe de rappel de la boutique
     * @param array Liste des attributs de l'article dans le panier
     *
     * @return void
     */
    public function __construct(Shop $shop, $attributes = [])
    {
        parent::__construct($shop, $attributes);

        // Initialisation de l'Api Payzen
        require_once $this->appDirname() . '/Api/PayzenRequest.php';
        $this->request = new \PayzenRequest();
    }

    /**
     * Récupération des attributs de configuration par défaut
     *
     * @return array
     */
    public function getDefaults()
    {
        return [
            'order_button_text'    => '',
            'enabled'              => true,
            'title'                => __('Carte', 'theme'),
            'description'          => __('Carte bleue Visa ou Mastercard', 'theme'),
            'method_title'         => __('Paiement par carte bancaire', 'theme'),
            'method_description'   => __('Permet le paiement par carte bancaire', 'theme'),
            'has_fields'           => false,
            'countries'            => [],
            'availability'         => '',
            'icon'                 => '',
            'choosen'              => false,
            'supports'             => ['products'],
            'max_amount'           => 0,
            'view_transaction_url' => '',
            'tokens'               => []
        ];
    }

    /**
     * Affichage de l'image d'identification de la plateforme.
     *
     * @return string
     */
    public function icon()
    {
        ob_start();
        self::tFyAppGetTemplatePart('_customer/checkout/payment-bank_card-icon');
        return ob_get_clean();
    }

    /**
     * Order review and payment form page.
     **/
    public function payzen_generate_form($order_id)
    {
        global $woocommerce;

        $order = new WC_Order($order_id);

        echo '<div style="opacity: 0.6; padding: 10px; text-align: center; color: #555;    border: 3px solid #aaa; background-color: #fff; cursor: wait; line-height: 32px;">';

        $img_url = WC_PAYZEN_PLUGIN_URL . 'assets/images/loading.gif';
        $img_url = class_exists('WC_HTTPS') ? WC_HTTPS::force_https_url($img_url) : $woocommerce->force_ssl($img_url);
        echo '<img src="' . esc_url($img_url) . '" alt="..." style="float:left; margin-right: 10px;"/>';
        echo __('Please wait, you will be redirected to the payment platform.', 'woo-payzen-payment');
        echo '</div>';
        echo '<br />';
        echo '<p>'.__('If nothing happens in 10 seconds, please click the button below.', 'woo-payzen-payment').'</p>';

        $this->payzen_fill_request($order);

        $form = "\n".'<form action="' . esc_url($this->request->get('platform_url')) . '" method="post" name="' . $this->getId() . '_payment_form" target="_top">';
        $form .= "\n".$this->request->getRequestHtmlFields();
        $form .= "\n".'<input type="submit" class="button-alt" id="submit_' . $this->getId() . '_payment_form" value="' . sprintf(__('Pay via %s', 'woo-payzen-payment'), 'PayZen').'">';
        $form .= "\n".'<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">'.__('Cancel order &amp; restore cart', 'woo-payzen-payment') . '</a>';
        $form .= "\n".'</form>';

        $form .= "\n".'<script type="text/javascript">';
        $form .= "\nfunction payzen_submit_form() {
                    document.getElementById('submit_" . $this->getId() . "_payment_form').click();
                  }";
        $form .= "\nif (window.addEventListener) { // for all major browsers, except IE 8 and earlier
                    window.addEventListener('load', payzen_submit_form, false);
                  } else if (window.attachEvent) { // for IE 8 and earlier versions
                    window.attachEvent('onload', payzen_submit_form);
                  }";
        $form .= "\n</script>\n";

        echo $form;
    }

    /**
     * Prepare PayZen form params to send to payment gateway.
     *
     * @param OrderInterface $order
     *
     * @return void
     */
    protected function payzen_fill_request($order)
    {
        //$this->log('Generating payment form for order #' . $this->get_order_property($order, 'id') . '.');

        // get currency
        $currency = \PayzenApi::findCurrencyByAlphaCode($this->settings()->currency());
        if ($currency == null) :
            $this->log('The store currency (' . $this->settings()->currency() . ') is not supported by PayZen.');

            wp_die(sprintf(__('The store currency (%s) is not supported by %s.'), get_woocommerce_currency(), 'PayZen'));
        endif;

        // effective used version
        include ABSPATH . WPINC . '/version.php';
        $version = $wp_version . '-' . $woocommerce->version;

        // PayZen params
        $misc_params = array(
            'amount' => $currency->convertAmountToInteger($order->get_total()),
            'contrib' => 'WooCommerce2.x-3.x_1.4.1/' . $version . '/' . PHP_VERSION,
            'currency' => $currency->getNum(),
            'order_id' => $order->getId(),
            'order_info' => $this->get_order_property($order, 'order_key'),
            'order_info2' => 'blog_id=' . $wpdb->blogid, // save blog_id for multisite cases

            // billing address info
            'cust_id' => $this->get_order_property($order, 'user_id'),
            'cust_email' => $this->get_order_property($order, 'billing_email'),
            'cust_first_name' => $this->get_order_property($order, 'billing_first_name'),
            'cust_last_name' => $this->get_order_property($order, 'billing_last_name'),
            'cust_address' => $this->get_order_property($order, 'billing_address_1') . ' ' .  $this->get_order_property($order, 'billing_address_2'),
            'cust_zip' => $this->get_order_property($order, 'billing_postcode'),
            'cust_country' => $this->get_order_property($order, 'billing_country'),
            'cust_phone' => str_replace(array('(', '-', ' ', ')'), '', $this->get_order_property($order, 'billing_phone')),
            'cust_city' => $this->get_order_property($order, 'billing_city'),
            'cust_state' => $this->get_order_property($order, 'billing_state'),

            // shipping address info
            'ship_to_first_name' => $this->get_order_property($order, 'shipping_first_name'),
            'ship_to_last_name' => $this->get_order_property($order, 'shipping_last_name'),
            'ship_to_street' => $this->get_order_property($order, 'shipping_address_1'),
            'ship_to_street2' => $this->get_order_property($order, 'shipping_address_2'),
            'ship_to_city' => $this->get_order_property($order, 'shipping_city'),
            'ship_to_state' => $this->get_order_property($order, 'shipping_state'),
            'ship_to_country' => $this->get_order_property($order, 'shipping_country'),
            'ship_to_zip' => $this->get_order_property($order, 'shipping_postcode'),
            'ship_to_phone_num' => str_replace(array('(', '-', ' ', ')'), '', $this->get_order_property($order, 'shipping_phone')),

            // return URLs
            'url_return' => add_query_arg('wc-api', 'WC_Gateway_Payzen', home_url('/'))
        );
        $this->payzen_request->setFromArray($misc_params);

        // activate 3ds ?
        $threeds_mpi = null;
        if ($this->get_option('3ds_min_amount') != '' && $order->get_total() < $this->get_option('3ds_min_amount')) {
            $threeds_mpi = '2';
        }

        $this->payzen_request->set('threeds_mpi', $threeds_mpi);

        // detect language
        $locale = get_locale() ? substr(get_locale(), 0, 2) : null;
        if ($locale && PayzenApi::isSupportedLanguage($locale)) {
            $this->payzen_request->set('language', $locale);
        } else {
            $this->payzen_request->set('language', $this->get_option('language'));
        }

        // available languages
        $langs = $this->get_option('available_languages');
        if (is_array($langs) && ! in_array('', $langs)) {
            $this->payzen_request->set('available_languages', implode(';', $langs));
        }

        if ($this->id != 'payzenchoozeo') {
            // payment cards
            if ($this->get_option('card_data_mode') == 'MERCHANT') {
                $selected_card = get_transient($this->id . '_card_type_' . $this->get_order_property($order, 'id'));
                $this->payzen_request->set('payment_cards', $selected_card);

                delete_transient($this->id . '_card_type_' . $this->get_order_property($order, 'id'));
            } else {
                $cards = $this->get_option('payment_cards');
                if (is_array($cards) && ! in_array('', $cards)) {
                    $this->payzen_request->set('payment_cards', implode(';', $cards));
                }
            }
        }

        // enable automatic redirection ?
        $this->payzen_request->set('redirect_enabled', ($this->get_option('redirect_enabled') == 'yes') ? true : false);

        // redirection messages
        $success_message = $this->get_option('redirect_success_message');
        $success_message = isset($success_message[get_locale()]) && $success_message[get_locale()] ? $success_message[get_locale()] :
            (is_array($success_message) ? $success_message['en_US'] : $success_message);
        $this->payzen_request->set('redirect_success_message', $success_message);

        $error_message = $this->get_option('redirect_error_message');
        $error_message = isset($error_message[get_locale()]) && $error_message[get_locale()] ? $error_message[get_locale()] :
            (is_array($error_message) ? $error_message['en_US'] : $error_message);
        $this->payzen_request->set('redirect_error_message', $error_message);

        // other configuration params
        $config_keys = array(
            'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url', 'capture_delay', 'validation_mode',
            'redirect_success_timeout', 'redirect_error_timeout', 'return_mode'
        );

        foreach ($config_keys as $key) {
            $this->payzen_request->set($key, $this->get_option($key));
        }

        $this->log('Data to be sent to payment platform : ' . print_r($this->payzen_request->getRequestFieldsArray(true /* to hide sensitive data */), true));
    }
}