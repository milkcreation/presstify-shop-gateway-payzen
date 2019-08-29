<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen;

use tiFy\Container\ServiceProvider;
use tiFy\Plugins\ShopGatewayPayzen\{
    Payzen\Payzen,
    Payzen\PayzenNotices,
    Payzen\PayzenRequest,
    Payzen\PayzenResponse,
    Payzen\PayzenTransaction
};

class ShopGatewayPayzenServiceProvider extends ServiceProvider
{
    /**
     * Liste des noms de qualification des services fournis.
     * {@internal Permet le chargement différé des services qualifié.}
     * @var string[]
     */
    protected $provides = [
        'payzen',
        'payzen.notices',
        'payzen.request',
        'payzen.response',
        'payzen.transaction',
        'shop.gateway.payzen'
    ];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->getContainer()->share('payzen', function () {
            return new Payzen([], request()->convertToPsr(), $this->getContainer());
        });

        $this->getContainer()->share('payzen.notices', function () {
            return new PayzenNotices($this->getContainer()->get('payzen'));
        });

        $this->getContainer()->share('payzen.request', function () {
            return new PayzenRequest($this->getContainer()->get('payzen'));
        });

        $this->getContainer()->share('payzen.response', function () {
            return new PayzenResponse($this->getContainer()->get('payzen'));
        });

        $this->getContainer()->share('payzen.transaction', function () {
            return new PayzenTransaction($this->getContainer()->get('payzen'));
        });

        $this->getContainer()->add('shop.gateway.payzen', function (): PayzenGateway {
            return new PayzenGateway();
        });
    }
}
