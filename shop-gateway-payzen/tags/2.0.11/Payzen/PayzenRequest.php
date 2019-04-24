<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen\Payzen;

class PayzenRequest extends PayzenParamsBag
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
    }

    /**
     * @inheritdoc
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach([
            // OBLIGATOIRES
            /**
             * Mode d’acquisition des données de la carte.
             *
             * @var string INTERACTIVE
             */
            'action_mode' => 'INTERACTIVE',
            /**
             * Montant du paiement dans sa plus petite unité monétaire (le centime pour l'euro).
             * {@internal ex. 3000 pour 30,00 EUR.}
             *
             * @var int 12 caractères maximum
             */
            'amount' => 0,
            /**
             * Mode de communication avec la plateforme de paiement.
             *
             * @var string TEST|PRODUCTION
             */
            'ctx_mode' => 'TEST',
            /**
             * Code numérique de la monnaie à utiliser pour le paiement, selon la norme ISO 4217 (code numérique).
             * {@internal ex. 978 pour l'euro (EUR).}
             *
             * @var int
             */
            'currency' => '',
            /**
             * Action à réaliser.
             *
             * @var string PAYMENT
             */
            'page_action' => 'PAYMENT',
            /**
             * Type de paiement.
             * {@internal SINGLE pour un paiement en 1 fois|MULTI pour un paiement en plusieurs fois.}
             *
             * @var string SINGLE|MULTI
             */
            'payment_config' => 'SINGLE',
            /**
             * Identifiant de la boutique (requis).
             *
             * @var int 8 caractères obligatoire.
             */
            'site_id' => '',
            /**
             * Date et heure du formulaire de paiement dans le fuseau horaire UTC.
             *
             * @var int Timestamp 14 caractères
             */
            'trans_date' => time(),
            /**
             * Numéro de transaction.
             *
             * @var int 6 caractères obligatoire.
             */
            'trans_id' => '',
            /**
             * Version du protocole d’échange avec la plateforme de paiement.
             *
             * @var string V2
             */
            'version' => 'V2',

            // RECOMMANDÉS
            // Données de commande.
            /**
             * Numéro de commande
             * {@internal 64 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'order_id' => '',
            /**
             * Informations supplémentaires sur la commande.
             * {@internal 255 caractères alphanumériques.}
             *
             * @var string
             */
            'order_info' => '',
            /**
             * Informations supplémentaires sur la commande.
             * {@internal 255 caractères alphanumériques.}
             *
             * @var string
             */
            'order_info2' => '',
            /**
             * Informations supplémentaires sur la commande.
             * {@internal 255 caractères alphanumériques.}
             *
             * @var string
             */
            'order_info3' => '',
            /**
             * Nombre d’articles présents dans le panier.
             * {@internal 12 caractères numériques max.}
             *
             * @var int
             */
            'nb_products' => 0,
            /**
             * @todo données dynamiques de commandes
             * product_ext_idN & product_labelN & product_amountN & product_typeN & product_refN & product_qtyN
             */
            // Données de l'acheteur.
            /**
             * Adresse e-mail de l’acheteur.
             *
             * @var string
             */
            'cust_email ' => '',
            /**
             * Référence de l’acheteur sur le site marchand.
             *
             * @var int
             */
            'cust_id' => 0,
            /**
             * Civilité de l’acheteur.
             *
             * @var string
             */
            'cust_title' => '',
            /**
             * Statut de l'acheteur.
             * {@internal PRIVATE: pour particulier|COMPANY pour une entreprise.}
             *
             * @var string PRIVATE|COMPANY
             */
            'cust_status' => '',
            /**
             * Prénom.
             * {@internal 63 caractères alphanumérique max.}
             *
             * @var string
             */
            'cust_first_name' => '',
            /**
             * Nom.
             * {@internal 63 caractères alphanumérique max.}
             *
             * @var string
             */
            'cust_last_name' => '',
            /**
             * Raison sociale.
             * {@internal 100 caractères alphanumérique max.}
             *
             * @var string
             */
            'cust_legal_name' => '',
            /**
             * Numéro de téléphone mobile.
             * {@internal 32 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'cust_cell_phone' => '',
            /**
             * Numéro de téléphone.
             * {@internal 32 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'cust_phone' => '',
            /**
             * Numéro de rue.
             * {@internal 64 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'cust_address_number' => '',
            /**
             * Adresse postale.
             * {@internal 255 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'cust_address' => '',
            /**
             * Quartier.
             * {@internal 127 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'cust_district' => '',
            /**
             * Code postal.
             * {@internal 64 caractères alphanumérique max.}
             *
             * @var string
             */
            'cust_zip' => '',
            /**
             * Ville.
             * {@internal 128 caractères alphanumérique. ex. Bordeaux.}
             *
             * @var string
             */
            'cust_city' => '',
            /**
             * Etat/Région.
             * {@internal 127 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'cust_state' => '',
            /**
             * Code pays suivant la norme ISO 3166.
             *
             * @var
             */
            'cust_country' => '',

            // Données de livraison.
            /**
             * Ville.
             * {@internal 128 caractères alphanumérique. ex. Bordeaux.}
             *
             * @var string
             */
            'ship_to_city' => '',
            /**
             * Code pays suivant la norme ISO 3166.
             * {@internal 2 caractères alphabétique. ex. FR}
             *
             * @var string
             */
            'ship_to_country' => '',
            /**
             * Quartier.
             * {@internal 127 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'ship_to_district' => '',
            /**
             * Prénom.
             * {@internal 63 caractères alphanumérique max.}
             *
             * @var string
             */
            'ship_to_first_name' => '',
            /**
             * Nom.
             * {@internal 63 caractères alphanumérique max.}
             *
             * @var string
             */
            'ship_to_last_name' => '',
            /**
             * Raison sociale.
             * {@internal 100 caractères alphanumérique max.}
             *
             * @var string
             */
            'ship_to_legal_name' => '',
            /**
             * Quartier.
             * {@internal 32 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'ship_to_phone_num' => '',
            /**
             * Etat/Région.
             * {@internal 127 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'ship_to_state' => '',
            /**
             * Type d'adresse de livraison.
             * {@internal PRIVATE: pour particulier|COMPANY pour une entreprise.}
             *
             * @var string PRIVATE|COMPANY
             */
            'ship_to_status' => '',
            /**
             * Numéro de rue.
             * {@internal 64 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'ship_to_street_number' => '',
            /**
             * Adresse postale.
             * {@internal 255 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'ship_to_street' => '',
            /**
             * Deuxième ligne d'adresse.
             * {@internal 255 caractères max alphanumériques et spéciaux, hormis "<" et ">".}
             *
             * @var string
             */
            'ship_to_street2' => '',
            /**
             * Code postal.
             * {@internal 64 caractères alphanumérique max.}
             *
             * @var string
             */
            'ship_to_zip' => ''
        ] as $k => $v) {
            $defaults[$this->mapKey($k)] = $v;
        }

        return $defaults;
    }

    /**
     * Récupération de la liste des champs HTML du formulaire de requête de soumission de paiement.
     *
     * @return array
     */
    public function getHtmlFields(): array
    {
        $fields = [];
        foreach($this->attributes as $name => $value) {
            $fields[] = sprintf(
                "<input name=\"%s\" value=\"%s\" type=\"hidden\"/>",
                $name,
                htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
            );
        }
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function mapKey($key): string
    {
        switch ($key) {
            default:
                return $this->payzen->getPrefix() . $key;
                break;
            case 'signature' :
                return $key;
                break;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return PayzenRequest
     */
    public function parse(): PayzenParamsBag
    {
        return parent::parse();
    }

    /**
     * Définition de la signature.
     *
     * @return $this
     */
    public function setSignature(): PayzenRequest
    {
        $this->set('signature', $this->payzen->generateSignature($this->attributes));

        return $this;
    }
}