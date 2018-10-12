<?php

namespace tiFy\Plugins\ShopGatewayPayzen;

use \PayzenApi;
use \PayzenRequest;
use \PayzenResponse;
use tiFy\Core\Route\Route;
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
        require_once dirname(__FILE__) . '/Api/PayzenRequest.php';
        $this->request = new PayzenRequest();

        $this->appAddAction('tify_route_register');
    }

    /**
     * Déclaration de la route de traitement de la commande par le serveur de paiement
     *
     * @param Route $route Classe de rappel de traitement des routes.
     *
     * @return void
     */
    final public function tify_route_register($route)
    {
        $route->register(
            'tify-shop-gateway-payzen',
            [
                'path' => '/tify-shop-api/gateway-payzen',
                'cb' => function () {
                    $this->appAddAction('wp_loaded', 'checkNotifyResponse');
                }
            ]
        );
    }

    /**
     * Liste des statuts de commande associés au paiement réussi
     *
     * @return array
     */
    public function orderSuccessStatuses()
    {
        return ['order-on-hold', 'order-processing', 'order-completed'];
    }

    /**
     * Vérification de la réponse suite au paiement.
     *
     * @return void
     */
    public function checkNotifyResponse()
    {
        @ob_clean();

        $raw_response = $this->appRequest($this->get('return_mode', ''))->all([]);

        require_once dirname(__FILE__) . '/Api/PayzenResponse.php';
        $this->response = new PayzenResponse(
            $raw_response,
            $this->get('ctx_mode'),
            $this->get('key_test'),
            $this->get('key_prod')
        );

        $from_server = $this->response->get('hash') != null;

        if ($from_server) :
            $this->log(
                __('Données de réponse du processus de traitement côté serveur reçues.', 'tify'),
                'info',
                $raw_response
            );
        endif;

        if (! $this->response->isAuthentified()) :
            $this->log(
                __('La réponse reçue depuis Payzen est invalide: Authentification en échec', 'tify'),
                'error'
            );

            if ($from_server) :
                $this->log(
                    __('Fin du processus de traitement côté serveur', 'tify'),
                    'info'
                );
                die($this->response->getOutputForPlatform('auth_fail'));
            else :
                $this->log(
                    __('Fin du processus de traitement côté client', 'tify'),
                    'info'
                );
                wp_die(
                    __('La réponse reçue depuis Payzen est invalide: Authentification en échec', 'tify'),
                    __('Payzen - Echec d\'authentification', 'tify'),
                    500
                );
            endif;
        else :
            header('HTTP/1.1 200 OK');

            $this->handleNotifyResponse();
        endif;
    }

    /**
     * Traitement de la réponse suite à l'issue du paiement sur la plateforme Payzen.
     * @internal Mise à jour de la commande, expédition de mail ...
     *
     * @return void
     */
    public function handleNotifyResponse()
    {
        $this->notices()->clear();

        $order_id = (int) $this->response->get('order_id');
        $from_server = $this->response->get('hash') != null;

        $order = $this->orders()->getItem($order_id);

        if ($order->getOrderKey() !== $this->response->get('order_info')) :
            $this->log(
                sprintf(
                    __('ERREUR: La commande n°%s n\'a pas été trouvée ou la clé ne correspond pas à l\'identifiant reçu par le paiement.', 'tify'),
                    $order->getId()
                ),
                'error'
            );

            if ($from_server) :
                $this->log(
                    __('Fin du processus de traitement côté serveur', 'tify'),
                    'info'
                );

                die($this->response->getOutputForPlatform('order_not_found'));
            else :
                $this->log(
                    __('Fin du processus de traitement côté client', 'tify'),
                    'info'
                );

                wp_die(
                    sprintf(
                        __('ERREUR: La commande n°%s n\'a pas été trouvée.', 'tify'),
                        $order->getId()
                    ),
                    __('Payzen - Commande non trouvée', 'tify'),
                    500
                );
            endif;
        endif;

        if ($this->get('ctx_mode') === 'TEST') :
            $msg  = __('<p><u>PASSAGE EN PRODUCTION</u></p>Si vous souhaitez obtenir des informations pour régler votre boutique en mode production, veuillez consulter l\'url suivante: ', 'tify');
            $msg .= '<a href="https://secure.payzen.eu/html/faq/prod" target="_blank">https://secure.payzen.eu/html/faq/prod</a>';

            $this->notices()->add($msg);
        endif;

        // Url de commande pour permettre, en cas d'échec, de soumettre à nouveau le paiement.
        $error_url = $this->functions()->url()->checkoutPage();

        if ($this->isNewOrder($order, $this->response->get('trans_id'))) :
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

            if ($this->response->isAcceptedPayment()) :
                $this->log(
                    sprintf(
                        __('Paiement réussi, la commande n°%d va être enregistrée', 'tify'),
                        $order_id
                    ),
                    'info'
                );

                $note .= "\n";
                $note .= sprintf(__('Transaction %s.', 'tify'), $this->response->get('trans_id'));
                $order->addNote($note);
                $order->paymentComplete();

                if ($from_server) :
                    $this->log(
                        __('Le processus de paiement lancé par le serveur, s\'est terminée avec succès.', 'tify'),
                        'info'
                    );
                    $this->log(
                        __('Fin du processus de traitement côté serveur', 'tify'),
                        'info'
                    );

                    die ($this->response->getOutputForPlatform('payment_ok'));
                else :
                    $this->log(
                        __('Attention ! L\'appel côté serveur n\'est pas actif. Le paiement s\'est terminé avec succès, grâce à un traitement de l\'url côté client. Utilisez l\'url de notification instantanée', 'tify'),
                        'warning'
                    );

                    if ($this->get('ctx_mode') === 'TEST') :
                        $ipn_url_warn = sprintf(
                            __('La notification automatique (échange directe entre la plateforme de paiement et votre boutique) ne semble pas être opérante. Veuillez-vous assurer vous de la configuration depuis l\'url suivante %s', 'tify'),
                            'https://secure.payzen.eu/vads-merchant/'
                        );
                        $ipn_url_warn .= '<br />';
                        $ipn_url_warn .= sprintf(
                            __('Pour comprendre le problème veuillez consulter la documentation sur le site de la solution :%s', 'tify'),
                            '<a href="https://payzen.io/fr-FR/form-payment/quick-start-guide/proceder-a-la-phase-de-test.html" target="_blank">' .
                                'https://payzen.io/fr-FR/form-payment/quick-start-guide/proceder-a-la-phase-de-test.html' .
                            '</a>'
                        );

                        $this->notices()->add($ipn_url_warn, 'error');
                    endif;

                    $this->log(
                        __('Fin du processus de traitement côté client', 'tify'),
                        'info'
                    );

                    \wp_redirect($this->getReturnUrl($order));
                    die();
                endif;
            else :
                if (! $this->response->isCancelledPayment()) :
                    $note .= "\n";
                    $note .= sprintf(
                        __('Transaction %s.', 'tify'),
                        $this->response->get('trans_id')
                    );
                endif;
                $order->addNote($note);

                // @todo
                $order->update_status('failed');

                $this->log(
                    sprintf(
                        __('Paiement échoué ou annulé. %s', 'tify'),
                        $this->response->getLogMessage()
                    ),
                    'error'
                );

                if ($from_server) :
                    $this->log(
                        __('Fin du processus de traitement côté serveur', 'tify'),
                        'info'
                    );

                    die($this->response->getOutputForPlatform('payment_ko'));
                else :
                    if (! $this->response->isCancelledPayment()) {
                        $this->notices()->add(
                            __('Votre paiement n\'a pas été accepté. Veuillez essayer à nouveau.', 'tify'),
                            'error'
                        );
                    }

                    $this->log(
                        __('Fin du processus de traitement côté client', 'tify'),
                        'info'
                    );

                    \wp_redirect($error_url);
                    die();
                endif;
            endif;
        else :
            $this->log(
                sprintf(
                    __('La commande n°%s a déjà été traitée. Seul le résultat de paiement est affiché.', 'tify'),
                    $order_id
                ),
                'info'
            );
            if ($this->response->isAcceptedPayment() && $order->hasStatus($this->orderSuccessStatuses())) :
                $this->log(
                    __('Le paiement a été reconfirmé avec succès.', 'tify'),
                    'info'
                );

                if ($from_server) :
                    $this->log(
                        __('Fin du processus de traitement côté serveur', 'tify'),
                        'info'
                    );

                    die($this->response->getOutputForPlatform('payment_ok_already_done'));
                else :
                    $this->log(
                        __('Fin du processus de traitement côté client', 'tify'),
                        'info'
                    );

                    \wp_redirect($this->getReturnUrl($order));
                    die();
                endif;
            elseif (! $this->response->isAcceptedPayment() && ($order->hasStatus(['order-failed', 'order-cancelled']))) :
                $this->log(
                    __('Echec de reconfirmation de paiement.', 'tify'),
                    'error'
                );

                if ($from_server) :
                    $this->log(
                        __('Fin du processus de traitement côté serveur', 'tify'),
                        'info'
                    );

                    die($this->response->getOutputForPlatform('payment_ko_already_done'));
                else :
                    $this->log(
                        __('Fin du processus de traitement côté client', 'tify'),
                        'info'
                    );

                    if (! $this->response->isCancelledPayment()) :
                        $this->notices()->add(
                            __('Votre paiement n\'a pas été accepté. Veuillez essayer à nouveau.', 'tify'),
                            'error'
                        );
                    endif;

                    wp_redirect($error_url);
                    die();
                endif;
            else :
                $this->log(
                    sprintf(
                        __('ERREUR ! Résultat de paiement invalide pour la commande dèja traitée : %s - statut : %s', 'tify'),
                        $this->response->get('result'),
                        $order->getStatus()
                    ),
                    'error'
                );

                if ($from_server) :
                    $this->log(
                        __('Fin du processus de traitement côté serveur', 'tify'),
                        'info'
                    );

                    die($this->response->getOutputForPlatform('payment_ko_on_order_ok'));
                else :
                    $this->log(
                        __('Fin du processus de traitement côté client', 'tify'),
                        'info'
                    );

                    wp_die(
                        sprintf(
                            __('Erreur: Le code de paiement reçu ne semble pas correspondre à la commande n°%s.', 'tify'),
                            $order_id
                        ),
                        __('Payzen ', 'tify'),
                        500
                    );
                endif;
            endif;
        endif;
    }

    /**
     * Vérifie s'il s'agit du pré-traitement de la commande.
     *
     * @param OrderInterface $order Classe de rappel de la commande
     * @param string $transaction_id Identifiant de qualification de transaction
     *
     * @return bool
     */
    private function isNewOrder($order, $transaction_id)
    {
        if ($order->hasStatus('order-pending')) :
            return true;
        endif;

        if ($order->hasStatus('order-failed') || $order->hasStatus('order-cancelled')) :
            return get_post_meta((int) $order->getId(), 'Transaction ID', true) !== $transaction_id;
        endif;

        return false;
    }
}