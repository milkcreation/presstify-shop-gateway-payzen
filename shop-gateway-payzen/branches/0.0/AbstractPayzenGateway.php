<?php

namespace tiFy\Plugins\ShopGatewayPayzen;

use \PayzenApi;
use \PayzenRequest;
use \PayzenResponse;
use tiFy\Plugins\Shop\Gateways\AbstractGateway;
use tiFy\Plugins\Shop\Orders\OrderInterface;
use tiFy\Plugins\Shop\Shop;

abstract class AbstractPayzenGateway extends AbstractGateway
{
    /**
     * Classe de rappel de gestion des paramètres de requête PayZen
     * @var PayzenRequest
     */
    protected $request;

    /**
     * Classe de rappel de gestion des paramètres de réponse PayZen
     * @var PayzenResponse
     */
    protected $response;

    /**
     * CONSTRUCTEUR
     *
     * @param Shop $shop Classe de rappel de la boutique
     * @param array Liste des attributs de l'article dans le panier
     *
     * @return void
     */
    public function __construct($id, $attributes = [], Shop $shop)
    {
        parent::__construct($id, $attributes, $shop);

        // Initialisation de l'Api Payzen
        require_once $this->appDirname() . '/Api/PayzenRequest.php';
        $this->request = new PayzenRequest();

        $this->appAddAction(
            'wp_loaded',
            function () {
                $this->checkNotifyResponse();
            }
        );
    }

    /**
     * Vérification de la réponse suite au paiement.
     *
     * @return void
     */
    public function checkNotifyResponse()
    {
        @ob_clean();

        $raw_response = $this->appRequestCall('all', [], $this->get('return_mode'));

        require_once $this->appDirname() . '/Api/PayzenResponse.php';
        $this->response = new PayzenResponse(
            $raw_response,
            $this->get('ctx_mode'),
            $this->get('key_test'),
            $this->get('key_prod')
        );

        $from_server = $this->response->get('hash') != null;

        if ($from_server) :
            $this->appLog()->error('Response received from PayZen server URL: ' . print_r($raw_response, true));
        endif;

        if (! $this->response->isAuthentified()) :
            $this->appLog()->error('Received invalid response from PayZen: authentication failed.');

            if ($from_server) :
                $this->appLog()->error('SERVER URL PROCESS END');
                die($this->response->getOutputForPlatform('auth_fail'));
            else :
                $this->appLog()->error('RETURN URL PROCESS END');
                wp_die(sprintf(__('%s response authentication failure.', 'woo-payzen-payment'), 'PayZen'));
            endif;
        else :
            header('HTTP/1.1 200 OK');

            $this->handleNotifyResponse();
        endif;
    }

    /**
     * Traitement de la réponse suite au paiment.
     * @internal Mise à jour de la commande, expédition de mail ...
     *
     * @return void
     */
    public function handleNotifyResponse()
    {
        $this->notices()->clear();

        $order_id = (int) $this->response->get('order_id');
        $from_server = $this->response->get('hash') != null;


        $order = $this->orders()->get($order_id);

        if ($order->getOrderKey() !== $this->response->get('order_info')) :
            $this->appLog()->error(
                sprintf(
                    __('ERREUR: La commande n°%s n\'a pas été trouvée ou la clé ne correspond pas à l\'identifiant reçu par le paiement.', 'tify'),
                    $order->getId()
                )
            );

            if ($from_server) :
                $this->appLog()->error(__('Fin de processus côté url de serveur', 'tify'));
                die($this->response->getOutputForPlatform('order_not_found'));
            else :
                $this->appLog()->error(__('Fin de processus côté url de retour', 'tify'));
                wp_die(
                    sprintf(__('ERREUR: La commande n°%s n\'a pas été trouvée.', 'tify'), $order->getId()),
                    __('Commande non trouvée', 'tify'),
                    500
                );
            endif;
        endif;

        if ($this->get('ctx_mode') === 'TEST') :
            $msg  = __('<p><u>GOING INTO PRODUCTION</u></p>You want to know how to put your shop into production mode, please go to this URL: ', 'woo-payzen-payment');
            $msg .= '<a href="https://secure.payzen.eu/html/faq/prod" target="_blank">https://secure.payzen.eu/html/faq/prod</a>';

            $this->notices()->add($msg);
        endif;

        // checkout payment URL to allow re-order
        //$error_url = $woocommerce->cart->get_checkout_url();

        if (true === true) :
            \delete_post_meta($order->getId(), 'Transaction ID');
            \delete_post_meta($order->getId(), 'Card number');
            \delete_post_meta($order->getId(), 'Payment mean');
            \delete_post_meta($order->getId(), 'Card expiry');

            \update_post_meta($order->getId(), 'Transaction ID', $this->response->get('trans_id'));
            \update_post_meta($order->getId(), 'Card number', $this->response->get('card_number'));
            \update_post_meta($order->getId(), 'Payment mean', $this->response->get('card_brand'));

            $expiry = str_pad($this->response->get('expiry_month'), 2, '0', STR_PAD_LEFT) . '/' . $this->response->get('expiry_year');
            if (! $this->response->get('expiry_month')) :
                $expiry = '';
            endif;
            \update_post_meta($order->getId(), 'Card expiry', $expiry);

            $note = $this->response->getCompleteMessage("\n");

            if ($this->response->isAcceptedPayment()) {
                $this->appLog()->info(
                    sprintf(
                        __('Paiement réussi, la commande n°%d va être enregistrée', 'tify'),
                        $order_id
                    )
                );

                $note .= "\n";
                $note .= sprintf(__('Transaction %s.', 'tify'), $this->response->get('trans_id'));
                $order->addNote($note);
                exit;
                $order->paymentComplete();

                if ($from_server) {
                    $this->log('Payment completed successfully by server URL call.');
                    $this->log('SERVER URL PROCESS END');

                    die ($this->response->getOutputForPlatform('payment_ok'));
                } else {
                    $this->log('Warning ! IPN URL call has not worked. Payment completed by return URL call.');

                    if ($this->testmode) {
                        $ipn_url_warn = sprintf(__('The automatic notification (peer to peer connection between the payment platform and your shopping cart solution) hasn\'t worked. Have you correctly set up the notification URL in the %s Back Office ?', 'woo-payzen-payment'), 'PayZen');
                        $ipn_url_warn .= '<br />';
                        $ipn_url_warn .= __('For understanding the problem, please read the documentation of the module : <br />&nbsp;&nbsp;&nbsp;- Chapter &laquo;To read carefully before going further&raquo;<br />&nbsp;&nbsp;&nbsp;- Chapter &laquo;Notification URL settings&raquo;', 'woo-payzen-payment');

                        $this->add_notice($ipn_url_warn, 'error');
                    }

                    $this->log('RETURN URL PROCESS END');
                    wp_redirect($this->get_return_url($order));
                    die();
                }
            } else {
                if (! $this->response->isCancelledPayment()) {
                    $note .= "\n";
                    $note .= sprintf(__('Transaction %s.', 'woo-payzen-payment'), $this->response->get('trans_id'));
                }
                $order->add_order_note($note);
                $order->update_status('failed');

                $this->log('Payment failed or cancelled. ' . $this->response->getLogString());

                if ($from_server) {
                    $this->log('SERVER URL PROCESS END');
                    die($this->response->getOutputForPlatform('payment_ko'));
                } else {
                    if (! $this->response->isCancelledPayment()) {
                        $this->add_notice(__('Your payment was not accepted. Please, try to re-order.', 'woo-payzen-payment'), 'error');
                    }

                    $this->log('RETURN URL PROCESS END');
                    wp_redirect($error_url);
                    die();
                }
            }
        else :
            $this->log('Order #' . $order_id . ' is already processed. Just show payment result.');

            if ($this->response->isAcceptedPayment() && key_exists($this->get_order_property($order, 'status'), self::$success_order_statues)) {
                $this->log('Payment successfull reconfirmed.');

                // order success registered and payment succes received
                if ($from_server) {
                    $this->log('SERVER URL PROCESS END');
                    die ($this->response->getOutputForPlatform('payment_ok_already_done'));
                } else {
                    $this->log('RETURN URL PROCESS END');
                    wp_redirect($this->get_return_url($order));
                    die();
                }
            } elseif (! $this->response->isAcceptedPayment() && ($this->get_order_property($order, 'status') === 'failed' || $this->get_order_property($order, 'status') === 'cancelled')) {
                $this->log('Payment failed reconfirmed.');

                // order failure registered and payment error received
                if ($from_server) {
                    $this->log('SERVER URL PROCESS END');
                    die($this->response->getOutputForPlatform('payment_ko_already_done'));
                } else {
                    $this->log('RETURN URL PROCESS END');

                    if (! $this->response->isCancelledPayment()) {
                        $this->add_notice(__('Your payment was not accepted. Please, try to re-order.', 'woo-payzen-payment'), 'error');
                    }

                    wp_redirect($error_url);
                    die();
                }
            } else {
                $this->log('Error ! Invalid payment result received for already saved order. Payment result : ' . $this->response->get('result') . ', Order status : ' . $this->get_order_property($order, 'status'));

                // registered order status not match payment result
                if ($from_server) {
                    $this->log('SERVER URL PROCESS END');
                    die($this->response->getOutputForPlatform('payment_ko_on_order_ok'));
                } else {
                    $this->log('RETURN URL PROCESS END');
                    wp_die(sprintf(__('Error : invalid payment code received for already processed order (%s).', 'woo-payzen-payment'), $order_id));
                }
            }
        endif;
    }
}