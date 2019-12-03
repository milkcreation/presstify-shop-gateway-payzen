<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen\Payzen;

use Illuminate\Support\Arr;

class PayzenParamsBag
{
    /**
     * Liste des paramètres.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Récupération de la liste des paramètres définis.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Liste des paramètres par défaut.
     *
     * @return array
     */
    public function defaults(): array
    {
        return [];
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
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * Cartographie de l'indice d'un paramètre.
     *
     * @param string $key Clé d'indice brute.
     *
     * @return string
     */
    public function mapKey($key): string
    {
        return $key;
    }

    /**
     * Cartographie de l'indice d'un paramètre.
     *
     * @param mixed $value valeur brute.
     * @param string $key Clé d'indice brute.
     *
     * @return mixed
     */
    public function mapValue($value, $key)
    {
        return $value;
    }

    /**
     * Traitement de la liste des paramètres.
     *
     * @return PayzenParamsBag
     */
    public function parse(): PayzenParamsBag
    {
        $this->attributes = array_merge($this->defaults(), $this->attributes);

        return $this;
    }

    /**
     * Définition d'un ou plusieurs paramètres.
     *
     * @param string|array $key Clé d'indice brute ou tableau associatif des définitions de paramètres.
     * @param mixed $value Valeur si la clé d'indice est une chaîne de caractères.
     *
     * @return $this;
     */
    public function set($key, $value = null): PayzenParamsBag
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $k => $v) {
            Arr::set($this->attributes, $this->mapKey($k), $this->mapValue($v, $k));
        }

        return $this;
    }
}