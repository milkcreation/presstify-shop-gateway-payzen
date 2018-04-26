<?php

/**
 * @name ShopGatewayPayzen
 * @desc Plateforme de paiement Payzen pour le plugin ecommerce Shop de PresstiFy
 * @author Jordy Manner <jordy@milkcreation.fr>
 * @package presstify-plugins/shop-gateway-payzen
 * @namespace \tiFy\Plugins\ShopGatewayPayzen
 * @version 1.1.0
 */

namespace tiFy\Plugins\ShopGatewayPayzen;

use tiFy\App\Plugin;
use League\Event\Event;
use tiFy\Plugins\Shop\Gateways\GatewaysInterface;

final class ShopGatewayPayzen extends Plugin
{
    /**
     * CONSTRUCTEUR.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->appListen('tify.plugins.shop.gateways.register', [$this, 'register']);
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
