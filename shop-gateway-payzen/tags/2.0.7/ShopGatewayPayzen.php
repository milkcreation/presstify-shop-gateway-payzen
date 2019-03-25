<?php

namespace tiFy\Plugins\ShopGatewayPayzen;

use tiFy\Plugins\Shop\Contracts\GatewaysInterface;

/**
 * ShopGatewayPayzen
 *
 * @desc Plateforme de paiement Payzen pour le plugin ecommerce Shop de PresstiFy.
 * @author Jordy Manner <jordy@milkcreation.fr>
 * @package tiFy\Plugins\ShopGatewayPayzen
 * @version 2.0.6
 */
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
     *
     * @return void
     */
    public function register(GatewaysInterface $gateways)
    {
        $gateways->add('payzen', PayzenGateway::class);
    }
}
