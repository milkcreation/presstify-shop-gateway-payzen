<?php

namespace tiFy\Plugins\ShopGatewayPayzen;

use tiFy\App\Container\AppServiceProvider;
use tiFy\Plugins\Shop\Shop;

class ShopGatewayPayzenServiceProvider extends AppServiceProvider
{
    /**
     * Liste des noms de qualification des services fournis.
     * {@internal Permet le chargement différé des services qualifié.}
     * @var string[]
     */
    protected $provides = [
        'payzen.api',
        'payzen.api.request',
        'payzen.api.response',
        'shop.gateway.payzen'
    ];

    /**
     * @inheritdoc
     */
    public function register()
    {
        $this->getContainer()->add('shop.gateway.payzen', function ($id, $attributes = [], Shop $shop) {
            return new PayzenGateway($id, $attributes, $shop);
        });
    }
}
