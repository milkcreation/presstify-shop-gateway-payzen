<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen\Payzen;

class PayzenResponse extends PayzenParamsBag
{
    /**
     * Instance du controleur d'API Payzen.
     * @var Payzen
     */
    protected $payzen;

    /**
     * Instance du controleur de statut de .
     * @var PayzenTransaction
     */
    protected $transaction;

    /**
     * CONSTRUCTEUR.
     *
     * @param Payzen $payzen Instance du controleur d'API Payzen.
     *
     * @return void
     */
    public function __construct(Payzen $payzen)
    {
        $this->payzen = $payzen;
    }

    /**
     * Vérification de la validité de la signature provenant du serveur.
     *
     * @return bool
     */
    public function checkSignature(): bool
    {
        return $this->payzen->generateSignature($this->attributes) === $this->get('signature');
    }

    /**
     * @inheritdoc
     */
    public function get($key, $default = null)
    {
        switch ($key) {
            default:
                $key = $this->payzen->getPrefix() . $key;
                break;
            case 'signature' :
                break;
        }

        return parent::get($key, $default);
    }

    /**
     * Vérifie si la réponse provient du serveur Payzen.
     *
     * @return boolean
     */
    public function fromServer(): bool
    {
        return !!$this->get('hash');
    }

    /**
     * Traitement des données portés par la requête.
     *
     * @return $this
     */
    public function parseRequest(): PayzenResponse
    {
        $psr = $this->payzen->getPsrRequest();
        $datas = $psr->getParsedBody() ? : [];

        $this->set($this->payzen->stripslashes($datas));

        return $this;
    }

    /**
     * Récupération de l'instance de la transaction.
     *
     * @return PayzenTransaction
     */
    public function transaction(): ?PayzenTransaction
    {
        if (is_null($this->transaction)) {
            $this->transaction = $this->payzen->hasContainer()
                ? $this->payzen->resolve('transaction')
                : new PayzenTransaction($this->payzen);
        }
        return $this->transaction;
    }
}