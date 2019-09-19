<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen\Payzen;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Payzen
 *
 * @package tiFy\Plugins\ShopGatewayPayzen\Payzen
 *
 * @author Lyra Network (https://www.lyra-network.com/)
 * @author Geoffrey Crofte, Alsacréations (https://www.alsacreations.fr/)
 * @author Jordy Manner, Milkcreation <jordy@milkcreation.fr> (https://www.milkcreation.fr/)
 */
class Payzen
{
    /**
     * Préfixe de qualification des paramètres.
     *
     * @var string
     */
    protected const PARAMSPREFIX = 'vads_';
    /**
     * Algorithme de calcul de la signature.
     * @var string SHA-1|SHA-256
     */
    protected $algo;
    /**
     * Conteneur d'injection de dépendances.
     * @var ContainerInterface
     */
    protected $container;
    /**
     * Mode de traitement de la requête de paiement.
     * @var string TEST|PRODUCTION
     */
    protected $ctxMode;
    /**
     * Identifiant de qualification du site.
     * @var string 8 caractères
     */
    protected $siteId;
    /**
     * Clé de certificat de paiement en mode test.
     * @var string
     */
    protected $keyTest;
    /**
     * Clé de certificat de paiement en mode production.
     * @var string
     */
    protected $keyProd;
    /**
     * Instance du controleur de notification.
     * @var PayzenNotices
     */
    protected $notices;
    /**
     * Url de la plateforme de paiement.
     * @var string
     */
    protected $platformUrl = 'https://secure.payzen.eu/vads-payment/';
    /**
     * Instance de la requête Psr7.
     * @var ServerRequestInterface
     */
    protected $psrRequest;
    /**
     * Instance du controleur de requête soumise à la plateforme Payzen.
     * @var PayzenRequest
     */
    protected $request;
    /**
     * Instance du controleur de reponse issue de la plateforme Payzen.
     * @var PayzenResponse
     */
    protected $response;

    /**
     * CONSTRUCTEUR.
     *
     * @param array|null $config Liste des options de configuration.
     * @param ServerRequestInterface $request Requête Psr.
     * @param ContainerInterface $container Conteneur d'injection de dépendances.
     *
     * @return void
     */
    public function __construct(?array $config = [], ServerRequestInterface $request, ContainerInterface $container)
    {
        if (!is_null($config)) {
            $this->setConfig($config);
        }

        $this->container = $container;
        $this->psrRequest = $request;
    }

    /**
     * Récupération de la devise selon son identifiant alphabétique.
     *
     * @param string $currency Identifiant alphabétique de devise.
     *
     * @return PayzenCurrency|null
     */
    public function currencyGetByAlpha(string $currency): ?PayzenCurrency
    {
        return PayzenCurrency::getByAlpha($currency);
    }

    /**
     * Calcul de la signature Payzen
     *
     * @param array $params Liste des paramètres.
     * @param bool $hash Activation de récupération de la signature cryptée.
     *
     * @return string
     */
    public function generateSignature($params, $hash = true): string
    {
        ksort($params);

        $items = [];
        foreach ($params as $name => $value) {
            if (preg_match('/^' . $this->getPrefix() . '(.*)/', $name)) {
                $items[] = $value;
            }
        }
        array_push($items, $this->getKey());

        $sign = implode('+', $items);

        if (!$hash) {
            return $sign;
        }

        switch ($this->algo) {
            case 'SHA-1':
                return sha1($sign);
                break;
            case 'SHA-256':
                return base64_encode(hash_hmac('sha256', $sign, $this->getKey(), true));
                break;
            default:
                throw new InvalidArgumentException(
                    sprintf("Cet algorithme n\'est actuellement pas supporté : %s.", $this->algo)
                );
                break;
        }
    }

    /**
     * Calcul de l'identifiant de qualification de la transaction.
     * {@internal To be independent from shared/persistent counters, we use the number of 1/10 seconds since midnight
     * which has the appropriatee format (000000-899999) and has great chances to be unique.}
     *
     * @param int $timestamp
     *
     * @return string
     */
    public function generateTransId(?int $timestamp = null): string
    {
        if (!$timestamp) {
            $timestamp = time();
        }
        $parts = explode(' ', microtime());
        $id = ($timestamp + $parts[0] - strtotime('today 00:00')) * 10;
        $id = sprintf('%06d', $id);

        return $id;
    }

    /**
     * Récupération de la clé en fonction du mode de paiement TEST|PRODUCTION.
     *
     * @return string
     */
    public function getKey(): string
    {
        switch ($this->ctxMode) {
            case 'TEST' :
                return $this->keyTest;
                break;
            case 'PRODUCTION' :
                return $this->keyProd;
                break;
            default:
                throw new InvalidArgumentException(
                    sprintf("Ce type de clé n\'est actuellement pas supporté : %s.", $this->ctxMode)
                );
                break;
        }
    }

    /**
     * Récupération de la requête Psr.
     *
     * @return ServerRequestInterface
     */
    public function getPsrRequest(): ServerRequestInterface
    {
        return $this->psrRequest;
    }

    /**
     * Vérification d'existance du conteneur d'injection de dépendances.
     *
     * @return boolean
     */
    public function hasContainer(): bool
    {
        return $this->container instanceof ContainerInterface;
    }

    /**
     * Vérification si le mode de traitement des transactions est en test.
     *
     * @return bool
     */
    public function onTest()
    {
        return $this->ctxMode === 'TEST';
    }

    /**
     * Vérification si le mode de traitement des transactions est en production.
     *
     * @return bool
     */
    public function onProd()
    {
        return $this->ctxMode === 'PRODUCTION';
    }

    /**
     * Récupération du préfixe de qualification des paramètres.
     *
     * @return string
     */
    public function getPrefix()
    {
        return static::PARAMSPREFIX;
    }

    /**
     * Résolution du controleur des messages de notification ou message de notification.
     *
     * @param string|null $alias Identifiant de qualification du message.
     *
     * @return PayzenNotices|string
     */
    public function notices(?string $alias = null)
    {
        if (is_null($this->notices)) {
            $this->notices = $this->hasContainer() ? $this->resolve('notices') : new PayzenNotices($this);
        }

        return $alias ? $this->notices->get($alias) : $this->notices;
    }

    /**
     * Résolution du controleur de gestion de la requête de soumission.
     *
     * @return PayzenRequest
     */
    public function request(): ?PayzenRequest
    {
        if (is_null($this->request)) {
            $this->request = $this->hasContainer() ? $this->resolve('request') : new PayzenRequest($this);
        }

        return $this->request;
    }

    /**
     * Résolution du controleur de gestion de la réponse de paiement.
     *
     * @return PayzenResponse
     */
    public function response(): ?PayzenResponse
    {
        if (is_null($this->response)) {
            $this->response = $this->hasContainer() ? $this->resolve('response') : new PayzenResponse($this);
        }

        return $this->response;
    }

    /**
     * Définition de la configuration.
     *
     * @param array|null $config Liste des attributs de configuration.
     *
     * @return $this
     */
    public function setConfig(?array $config = []): ?Payzen
    {
        $this->algo = $config['sign_algo'] ?? 'SHA-1';
        $this->ctxMode = $config['ctx_mode'] ?? 'TEST';
        $this->keyProd = $config['key_prod'] ?? '';
        $this->keyTest = $config['key_test'] ?? '';
        $this->siteId = $config['site_id'] ?? '00000000';

        unset($config['sign_algo'], $config['ctx_mode'], $config['key_prod'], $config['key_test'], $config['site_id']);

        return $this;
    }

    /**
     * Suppression des antislashs des clés et valeurs de données.
     *
     * @param array $datas Liste des données à nettoyer.
     *
     * @return array
     */
    public function stripslashes(array $datas): array
    {
        $sane = [];
        foreach ($datas as $k => $v) {
            $sane_key = stripslashes($k);
            $sane_value = is_array($v) ? $this->stripslashes($v) : stripslashes($v);
            $sane[$sane_key] = $sane_value;
        }
        return $sane;
    }

    /**
     * Résolution de service fourni.
     *
     * @param string $alias Alias de qualification du service.
     *
     * @return mixed
     */
    public function resolve($alias)
    {
        return $this->container->get("payzen.{$alias}");
    }
}