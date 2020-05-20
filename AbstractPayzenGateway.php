<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen;

use tiFy\Contracts\Http\Response as HttpResponse;
use tiFy\Contracts\Routing\RouteGroup;
use tiFy\Plugins\Shop\{Contracts\Order, Gateways\AbstractGateway};
use tiFy\Plugins\ShopGatewayPayzen\Payzen\Payzen;
use tiFy\Support\Proxy\{Response, Redirect, Router};

abstract class AbstractPayzenGateway extends AbstractGateway
{
    /**
     * Identifiant de qualification de la plateforme.
     * @var string
     */
    protected $id = 'payzen';

    /**
     * Instance du controleur d'Api Payzen.
     * @var Payzen
     */
    protected $payzen;

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        Router::group('/tify-shop-api/gateway-payzen', function (RouteGroup $router) {
            $router->get('/', [$this, 'checkNotifyResponse'])->setName('shop.gateway.payzen.notify');
            $router->post('/', [$this, 'checkNotifyResponse']);
        });
    }

    /**
     * Vérifie s'il s'agit du pré-traitement de la commande.
     *
     * @param Order $order Classe de rappel de la commande
     * @param string|int $transaction_id Identifiant de qualification de transaction
     *
     * @return boolean
     */
    private function _isNewOrder(Order $order, $transaction_id): bool
    {
        if ($order->hasStatus('order-pending')) {
            return true;
        } elseif ($order->hasStatus('order-failed') || $order->hasStatus('order-cancelled')) {
            return get_post_meta((int)$order->getId(), 'Transaction ID', true) !== $transaction_id;
        }

        return false;
    }

    /**
     * Vérification de la réponse suite au paiement.
     * {@internal La requête est initié par le serveur (plateforme Payzen) ou par le client (navigateur)}
     *
     * @return HttpResponse
     */
    public function checkNotifyResponse(): HttpResponse
    {
        $r = $this->payzen()->response()->parseRequest();

        $this->logger('info', $this->payzen()->notices('process-start'), $r->all());

        if (!$r->checkSignature()) {
            $this->logger('error', $this->payzen()->notices('unchecked-sign'));
            $this->logger('info', $this->payzen()->notices('process-end'));

            $mess = __('Echec de paiement.', 'tify');

            if (!$r->fromServer()) {
                $this->shop->notices()->add($mess, 'error');

                return Redirect::to($this->shop->functions()->url()->checkoutPage());
            } else {
                return Response::instance(json_encode(['success' => false, 'data' => $mess]));
            }
        } else {
            return $this->handleNotifyResponse();
        }
    }

    /**
     * Traitement de la réponse suite à l'issue du paiement sur la plateforme Payzen.
     * {@internal Mise à jour de la commande, expédition de mail ...}
     *
     * @return HttpResponse
     */
    public function handleNotifyResponse(): HttpResponse
    {
        $this->shop->notices()->clear();

        $r = $this->payzen()->response();

        if (!$order = $this->shop->order((int)$r->get('order_id', 0))) {
            $mess = sprintf(__('ERREUR: La commande [%s] n\'existe pas', 'tify'), $r->get('order_id'));

            $this->logger('error', $mess);

            $this->logger('info', $this->payzen()->notices('process-end'));

            if (!$r->fromServer()) {
                $this->shop->notices()->add($mess, 'error');

                return Redirect::to($this->shop->functions()->url()->checkoutPage());
            } else {
                return Response::instance(json_encode(['success' => false, 'data' => $mess]));
            }
        } elseif ($order->getOrderKey() !== $r->get('order_info')) {
            $mess = sprintf(
                __(
                    'ERREUR: La commande [%s] n\'a pas été trouvée ou la clé ne correspond pas à l\'identifiant ' .
                    'retourné par la plateforme de paiement.',
                    'tify'
                ), $order->getId());

            $this->logger('error', $mess);

            $this->logger('info', $this->payzen()->notices('process-end'));

            if (!$r->fromServer()) {
                $this->shop->notices()->add($mess, 'error');

                return Redirect::to($this->shop->functions()->url()->checkoutPage());
            } else {
                return Response::instance(json_encode(['success' => false, 'data' => $mess]));
            }
        }

        if (!$r->fromServer() && $this->payzen()->onTest()) {
            $msg = __(
                '<p><u>PASSAGE EN PRODUCTION</u></p>' .
                'Si vous souhaitez obtenir des informations pour régler votre boutique en mode production,' .
                ' veuillez consulter l\'url suivante: ',
                'tify'
            );
            $msg .= '<a href="https://secure.payzen.eu/html/faq/prod" target="_blank">' .
                'https://secure.payzen.eu/html/faq/prod' .
                '</a>';

            $this->shop->notices()->add($msg);
        }

        if ($this->_isNewOrder($order, $r->transaction()->id())) {
            return $this->handleNewOrder($order);
        } else {
            $this->logger('info', sprintf(
                __('Retour boutique de la commande [%s], déjà été traitée.', 'tify'),
                $order->getId()
            ));

            if ($r->transaction()->isAccepted() && $order->hasStatus($this->orderSuccessStatuses())) {
                $mess = __('Le paiement a été reconfirmé avec succès.', 'tify');

                $this->logger('info', $mess);
                $this->logger('info', $this->payzen()->notices('process-end'));

                if (!$r->fromServer()) {
                    return Redirect::to($this->getReturnUrl($order));
                } else {
                    return Response::instance(json_encode(['success' => true, 'data' => $mess]));
                }
            } elseif (!$r->transaction()->isAccepted() && ($order->hasStatus(['order-failed', 'order-cancelled']))) {
                $mess = (!$r->transaction()->isCancelled())
                    ? __('ERREUR: Le paiement n\'a pas été accepté. Veuillez essayer à nouveau.', 'tify')
                    : __('ERREUR: Echec de reconfirmation de paiement.', 'tify');

                $this->logger('error', $mess);
                $this->logger('info', $this->payzen()->notices('process-end'));

                if (!$r->fromServer()) {
                    if (!$r->transaction()->isCancelled()) {
                        $this->shop->notices()->add($mess, 'error');
                    }

                    return Redirect::to($this->shop->functions()->url()->checkoutPage());
                } else {
                    return Response::instance(json_encode(['success' => false, 'data' => $mess]));
                }
            } else {
                $mess = sprintf(
                    __('ERREUR: Le code de paiement reçu ne semble pas correspondre à la commande n°%s.',
                        'tify'
                    ), $order->getId()
                );

                $this->logger('error', sprintf(
                    __(
                        'Résultat de paiement invalide pour la commande [%s] déjà traitée >> statut : [%s]',
                        'tify'
                    ), $r->get('result'), $order->getStatus()
                ));
                $this->logger('info', $this->payzen()->notices('process-end'));

                if (!$r->fromServer()) {
                    $this->shop->notices()->add($mess, 'error');

                    return Redirect::to($this->shop->functions()->url()->checkoutPage());
                } else {
                    return Response::instance(json_encode(['success' => false, 'data' => $mess]));
                }
            }
        }
    }

    /**
     * Traitement d'une commande non traité.
     *
     * @param Order $order Commande
     *
     * @return HttpResponse
     */
    public function handleNewOrder(Order $order): HttpResponse
    {
        $r = $this->payzen()->response();

        delete_post_meta($order->getId(), 'Transaction ID');
        delete_post_meta($order->getId(), 'Card number');
        delete_post_meta($order->getId(), 'Payment mean');
        delete_post_meta($order->getId(), 'Card expiry');

        update_post_meta($order->getId(), 'Transaction ID', $r->transaction()->id());
        update_post_meta($order->getId(), 'Card number', $r->get('card_number'));
        update_post_meta($order->getId(), 'Payment mean', $r->get('card_brand'));

        $expiry = str_pad((string)$r->get('expiry_month'), 2, '0', STR_PAD_LEFT) . '/' . $r->get('expiry_year');
        if (!$r->get('expiry_month')) {
            $expiry = '';
        }
        update_post_meta($order->getId(), 'Card expiry', $expiry);

        if ($r->transaction()->isAccepted()) {
            $mess = sprintf(
                __('Paiement réussi, la commande n°%d va être enregistrée', 'tify'),
                $order->getId()
            );

            $this->logger('info', $mess);

            $order->addNote(sprintf(__('Transaction %s.', 'tify'), $r->transaction()->id()));
            $order->paymentComplete($r->transaction()->id());

            $this->logger('info', $this->payzen()->notices('payment-ok'));

            if (!$r->fromServer()) {
                $this->logger('warning', sprintf(
                    __(
                        'Attention ! L\'appel côté serveur n\'est pas actif. Le paiement s\'est terminé ' .
                        'avec succès, grâce à un traitement de l\'url côté client.' .
                        'Utilisez plutôt l\'url de notification instantanée %s',
                        'tify'
                    ),
                    Router::url('shop.gateway.payzen.notify')
                ));

                if ($this->payzen()->onTest()) {
                    $warning = sprintf(
                        __(
                            'La notification automatique (échange directe entre la plateforme de paiement ' .
                            'et votre boutique) ne semble pas être opérante. Veuillez-vous assurer vous de la ' .
                            'configuration depuis l\'url suivante %s',
                            'tify'
                        ),
                        'https://secure.payzen.eu/vads-merchant/'
                    );
                    $warning .= '<br />';
                    $warning .= sprintf(
                        __(
                            'Pour comprendre le problème veuillez consulter la documentation sur' .
                            'le site de la solution :%s',
                            'tify'
                        ),
                        '<a href="https://payzen.io/fr-FR/form-payment/quick-start-guide/' .
                        'proceder-a-la-phase-de-test.html" target="_blank"' .
                        '>' .
                        'https://payzen.io/fr-FR/form-payment/quick-start-guide/proceder-a-la-phase-de-test.html' .
                        '</a>'
                    );
                    $this->shop->notices()->add($warning, 'error');
                }
            }

            $this->logger('info', $this->payzen()->notices('process-end'));

            if (!$r->fromServer()) {
                return Redirect::to($this->getReturnUrl($order));
            } else {
                return Response::instance(json_encode(['success' => true, 'data' => $mess]));
            }
        } else {
            if (!$r->transaction()->isCancelled()) {
                $order->addNote(sprintf(__('Transaction %s.', 'tify'), $r->transaction()->id()));
            }

            $order->updateStatus('order-failed');

            $this->logger('error', $this->payzen()->notices('payment-fail'));
            $this->logger('info', $this->payzen()->notices('process-end'));

            $mess = $r->transaction()->isAbandonned()
                ? __('Le réglement de votre commande a bien été annulée.', 'tify')
                : __('Votre paiement n\'a pas pu être validé. Veuillez essayer à nouveau.', 'tify');

            if (!$r->fromServer()) {
                $this->shop->notices()->clear();

                $this->shop->notices()->add($mess, $r->transaction()->isAbandonned() ? 'warning' : 'error');

                return Redirect::to($this->shop->functions()->url()->checkoutPage());
            } else {
                return Response::instance(json_encode(['success' => false, 'data' => $mess]));
            }
        }
    }

    /**
     * Liste des statuts de commande associés au paiement réussi.
     *
     * @return array
     */
    public function orderSuccessStatuses()
    {
        return ['order-on-hold', 'order-processing', 'order-completed'];
    }

    /**
     * Récupération de l'instance du controleur d'API Payzen.
     *
     * @return Payzen
     */
    public function payzen(): ?Payzen
    {
        if (is_null($this->payzen)) {
            $this->payzen = app()->get('payzen')->setConfig($this->all());
        }

        return $this->payzen;
    }
}