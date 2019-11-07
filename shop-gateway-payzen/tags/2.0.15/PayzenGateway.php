<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen;

use tiFy\Plugins\Shop\Contracts\OrderInterface;

class PayzenGateway extends AbstractPayzenGateway
{
    /**
     * Traitement des arguments de requête du formulaire de paiement.
     *
     * @return void
     */
    public function checkoutPaymentFillRequest(): void
    {
        $order = $this->shop->orders()->getItem();

        if (!$currency = $this->payzen()->currencyGetByAlpha($this->shop->settings()->currency())) {
            $this->shop->notices()->add(
                sprintf(
                    __('La devise de paiement (%s) n\'est pour actuellement pas supportée par %s.', 'tify'),
                    $this->shop->settings()->currency(),
                    'PayZen'
                ),
                'error'
            );
        } else {
            $time = time();

            $r = $this->payzen()->request();

            $r->set([
                'action_mode'        => 'INTERACTIVE',
                'amount'             => $currency->amountToInt($order->getTotal()),
                'ctx_mode'           => $this->get('ctx_mode'),
                'currency'           => $currency->getNum(),
                'page_action'        => 'PAYMENT',
                'payment_config'     => 'SINGLE',
                'site_id'            => $this->get('site_id'),
                'trans_date'         => gmdate('YmdHis', $time),
                'trans_id'           => $this->payzen()->generateTransId($time),
                'version'            => 'V2',

                // Données de commande.
                'order_id'           => $order->getId(),
                'order_info'         => $order->getOrderKey(),
                'order_info2'        => 'blog_id=' . get_current_blog_id(),
                //'order_info3',
                //'nb_products',

                // Données de l'acheteur.
                'cust_email'         => $order->getAddressAttr('email', 'billing'),
                'cust_id'            => $order->getCustomerId(),
                //'cust_title'
                //'cust_status'
                'cust_first_name'    => $order->getAddressAttr('first_name', 'billing'),
                'cust_last_name'     => $order->getAddressAttr('last_name', 'billing'),
                //'cust_legal_name'
                //'cust_cell_phone'
                'cust_phone'         => str_replace(
                    ['(', '-', ' ', ')'],
                    '',
                    $order->getAddressAttr('phone', 'billing')
                ),
                //'cust_address_number'
                'cust_address'       => $order->getAddressAttr('address_1', 'billing') .
                    ' ' .
                    $order->getAddressAttr('address_2', 'billing'),
                //'cust_district'
                'cust_zip'           => $order->getAddressAttr('postcode', 'billing'),
                'cust_city'          => $order->getAddressAttr('city', 'billing'),
                'cust_state'         => $order->getAddressAttr('state', 'billing'),
                'cust_country'       => $order->getAddressAttr('country', 'billing'),

                // Données de livraison.
                'ship_to_city'       => $order->getAddressAttr('city', 'shipping'),
                'ship_to_country'    => $order->getAddressAttr('country', 'shipping'),
                //'ship_to_district'
                'ship_to_first_name' => $order->getAddressAttr('first_name', 'shipping'),
                'ship_to_last_name'  => $order->getAddressAttr('last_name', 'shipping'),
                //'ship_to_last_name'
                //'ship_to_legal_name'
                'ship_to_phone_num'  => str_replace(
                    ['(', '-', ' ', ')'],
                    '',
                    $order->getAddressAttr('phone', 'shipping')
                ),
                'ship_to_state'      => $order->getAddressAttr('state', 'shipping'),
                //'ship_to_street_number'
                'ship_to_street'     => $order->getAddressAttr('address_1', 'shipping'),
                'ship_to_street2'    => $order->getAddressAttr('address_2', 'shipping'),
                'ship_to_zip'        => $order->getAddressAttr('postcode', 'shipping')
            ]);
            foreach ([
                'capture_delay',
                'redirect_enable',
                'redirect_success_timeout',
                'redirect_error_timeout',
                'return_mode',
                'platform_url',
                //'validation_mode'
            ] as $key) {
                if ($this->has($key)) {
                    $r->set($key, $this->get($key));
                }
            };
            if (in_array($this->get('return_mode'), ['GET', 'POST'])) {
                $r->set('url_return', route('shop.gateway.payzen.notify'));
            }
            $r->parse()->setSignature();
        }
    }

    /**
     * Formulaire de paiement de la commande.
     *
     * @internal Avant d'accéder à la page de paiement de la banque par ex.
     *
     * @return void
     *
     * @throws \Throwable
     * @throws \Exception
     */
    public function checkoutPaymentForm(): void
    {
        echo view()
            ->setDirectory(__DIR__ . '/Resources/views')
            ->render('checkout-payment-form', [
                'order'   => $this->shop->orders()->getItem(),
                'request' => $this->payzen()->request()
            ]);
    }

    /**
     * Récupération des attributs de configuration par défaut
     *
     * @return array {
     *      Liste des attributs de configuration
     *
     *      {@inheritdoc}
     * @var bool $debug Activation du mode de déboguage. Enregistrement du journal des actions.
     * @var string $site_id Identifiant de quaalification de la boutique.
     * @var string $key_test Certificat en mode TEST.
     * @var string $key_prod Certificat en mode PROD.
     * @var string $ctx_mode Mode de fonctionnement du module. TEST|PROD.
     * @var string $platform_url Url de la page de paiement. Par défaut https://secure.payzen.eu/vads-payment/.
     * @var string $language Langue par défaut utilisée sur le site de paiement.
     * @var string[] $languages Liste des langues proposées sur la page de paiement.
     * @var int $capture_delay Nombre de jours avant la remise en banque.
     * @var int $validation_mode Mode de validation des paiement. Le mode manuel 1, impose la modération des
     *                                paiements depuis le Back Office Payzen.
     * @var int $3ds_min_amount Montant minimum requis pour activer le 3D Secure. La souscription à l'option doit
     *                               être active pour être opérante.
     * @var bool $redirect_enabled Activation de la redirection automatique. L'acheteur est redirigé
     *                                  automatiquement vers le site à l'issue du paiement.
     * @var int $redirect_success_timeout Temps écoulé avant que l'acheteur ne soit redirigé vers le site lorque
     *                                         que le paiement a réussi.
     * @var array $redirect_success_message {
     *          Liste des attributs du message lorque le paiement à réussi.
     *
     * @var string $text Message affiché.
     * @var string $lang Langue du message.
     *      }
     * @var int $redirect_error_timeout Temps écoulé avant que l'acheteur ne soit redirigé vers le site lorque que
     *                                       le paiement est en échec.
     * @var array $redirect_error_message {
     *          Liste des attributs du message lorque le paiement est en échec.
     *
     * @var string $text Message affiché.
     * @var string $lang Langue du message.
     *      }
     * @var string $return_mode Méthode de transmission des informations de résultat de paiement. GET|POST.
     * @var string $order_status_on_success Statut de commande payée à l'issue d'un paiement réussi.
     * }
     */
    public function defaults(): array
    {
        return array_merge(parent::defaults(), [
            'description'              => __('Carte bancaire Visa ou Mastercard', 'theme'),
            'method_description'       => __('Permet le paiement par carte bancaire', 'theme'),
            'method_title'             => __('Paiement par carte bancaire', 'theme'),
            'title'                    => __('Carte bancaire', 'theme'),

            // Spécifique à la plateforme de paiement.
            '3ds_min_amount'           => 0,
            'capture_delay'            => 0,
            /**
             * Mode de traitement du paiement. (requis).
             * @var string TEST|PRODUCTION
             */
            'ctx_mode'                 => 'TEST',
            /**
             * Clé de certificat de paiement en mode test. (requis).
             * @var string
             */
            'key_test'                 => '',
            /**
             * Clé de certificat de paiement en mode production. (requis).
             * @var string
             */
            'key_prod'                 => '',
            'language'                 => 'fr',
            'languages'                => ['fr'],
            'platform_url'             => 'https://secure.payzen.eu/vads-payment/',
            'order_status_on_success'  => 'default',
            'redirect_enabled'         => true,
            'redirect_success_timeout' => 5,
            'redirect_success_message' => [
                'fr_FR' => __('Redirection vers la boutique dans quelques instants...', 'tify')
            ],
            'redirect_error_timeout'   => 5,
            'redirect_error_message'   => [
                'fr_FR' => __('Redirection vers la boutique dans quelques instants...', 'tify')
            ],
            'return_mode'              => 'POST',
            /**
             * Identifiant de la boutique.
             * @var int 8 caractères attendus.
             */
            'site_id'                  => '',
            'validation_mode'          => 0,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function processPayment(OrderInterface $order): array
    {
        return [
            'result'   => 'success',
            'redirect' => $order->getCheckoutPaymentUrl()
        ];
    }
}