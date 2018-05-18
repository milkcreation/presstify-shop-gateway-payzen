<?php

/**
 * @name ShopGatewayPayzen
 * @desc Plateforme de paiement Payzen pour le plugin ecommerce Shop de PresstiFy
 * @author Jordy Manner <jordy@milkcreation.fr>
 * @package presstify-plugins/shop-gateway-payzen
 * @namespace \tiFy\Plugins\ShopGatewayPayzen
 * @version 2.0.0
 */

namespace tiFy\Plugins\ShopGatewayPayzen;

use League\Event\Event;
use tiFy\Apps\AppController;
use tiFy\Plugins\Shop\Gateways\GatewaysInterface;

final class ShopGatewayPayzen extends AppController
{
    /**
     * CONSTRUCTEUR.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->appEventListen('tify.plugins.shop.gateways.register', [$this, 'register']);
    }

    /**
     * Déclaration de la plateforme de paiement.
     * @see http://event.thephpleague.com/2.0/listeners/callables/
     *
     * @param Event $event
     * @param GatewaysInterface $gateways
     *
     * @return void
     */
    public function register(Event $event, GatewaysInterface $gateways)
    {
        $gateways->add('payzen', PayzenGateway::class);
    }
}
