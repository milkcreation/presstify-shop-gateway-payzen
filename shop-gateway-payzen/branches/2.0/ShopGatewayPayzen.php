<?php

/**
 * @name ShopGatewayPayzen
 * @desc Plateforme de paiement Payzen pour le plugin ecommerce Shop de PresstiFy.
 * @author Jordy Manner <jordy@milkcreation.fr>
 * @package presstify-plugins/shop-gateway-payzen
 * @namespace \tiFy\Plugins\ShopGatewayPayzen
 * @version 2.0.2
 */

namespace tiFy\Plugins\ShopGatewayPayzen;

use tiFy\Plugins\Shop\Contracts\GatewaysInterface;

final class ShopGatewayPayzen
{
    /**
     * CONSTRUCTEUR.
     *
     * @return void
     */
    public function __construct()
    {
        events()->listen('tify.plugins.shop.gateways.register', [$this, 'register']);
    }

    /**
     * Déclaration de la plateforme de paiement.
     * @see http://event.thephpleague.com/2.0/listeners/callables/
     *
     * @param GatewaysInterface $gateways
     * @param EventsItem $event
     *
     *
     * @return void
     */
    public function register(GatewaysInterface $gateways)
    {
        $gateways->add('payzen', PayzenGateway::class);
    }
}
