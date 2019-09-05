<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen\Payzen;

class PayzenNotices extends PayzenParamsBag
{
    /**
     * Instance du controleur d'API Payzen.
     * @var Payzen
     */
    protected $payzen;

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

        $this->parse();
    }

    /**
     * Récupération de paramètre.
     *
     * @param string $key Clé d'indice de qualification du paramètre.
     * @param mixed $default Valeur de retour par défaut.
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->map(parent::get($key, $default), $key);
    }

    /**
     * @inheritdoc
     */
    public function defaults(): array
    {
        return [
            'auth-fail'      => 'Authentification en échec: Le test de comparaison de signature est invalide.',
            'payment-fail'   => 'Paiement refusé : La commande a été annulée.',
            'payment-ok'     => 'Paiement accepté : La commande a été mise à jour.',
            'process-end'    => 'Fin du processus de traitement initié côté %s.',
            'process-start'  => 'Début du processus de traitement initié coté %s.',
        ];
    }

    /**
     * Cartographie de la valeur de retour du message de notification.
     *
     * @param string $message Valeur de retour du message de notification.
     * @param string $key Clé d'indice de qualification du message.
     *
     * @return string
     */
    public function map($message, $key)
    {
        switch($key) {
            case 'process-end' :
            case 'process-start' :
                $message = sprintf($message, $this->payzen->response()->fromServer() ? 'serveur' : 'client');
                break;
        }

        return $message;
    }
}