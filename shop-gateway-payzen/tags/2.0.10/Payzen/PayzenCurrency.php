<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen\Payzen;

class PayzenCurrency
{
    /**
     * Identifiant de qualification alphabétique à 3 caractères.
     * @var string
     */
    private $alpha3 = '';

    /**
     * Identifiant de qualification numérique.
     * @var int
     */
    private $num = 0;

    /**
     * Nombre de décimales autorisées.
     * @var int
     */
    private $decimals;

    /**
     * Récupération d'une instance de devise selon son identifiant de qualification alphabétique.
     *
     * @param string Identifiant de qualification alphabétique à 3 caractères
     *
     * @return static|null
     */
    public static function getByAlpha(string $alpha3): ?PayzenCurrency
    {
        foreach (self::getSupported() as $currency) {
            /** @var static $currency */
            if ($currency->getAlpha3() == $alpha3) {
                return $currency;
            }
        }
        return null;
    }

    /**
     * Récupération de la liste des devises supportées.
     *
     * @return PayzenCurrency[]
     */
    public static function getSupported(): array
    {
        $currencies = [
            ['AUD', 036, 2],
            ['KHR', 116, 0],
            ['CAD', 124, 2],
            ['CNY', 156, 1],
            ['CZK', 203, 2],
            ['DKK', 208, 2],
            ['HKD', 344, 2],
            ['HUF', 348, 2],
            ['INR', 356, 2],
            ['IDR', 360, 2],
            ['JPY', 392, 0],
            ['KRW', 410, 0],
            ['KWD', 414, 3],
            ['MYR', 458, 2],
            ['MXN', 484, 2],
            ['MAD', 504, 2],
            ['NZD', 554, 2],
            ['NOK', 578, 2],
            ['PHP', 608, 2],
            ['RUB', 643, 2],
            ['SGD', 702, 2],
            ['ZAR', 710, 2],
            ['SEK', 752, 2],
            ['CHF', 756, 2],
            ['THB', 764, 2],
            ['TND', 788, 3],
            ['GBP', 826, 2],
            ['USD', 840, 2],
            ['TWD', 901, 2],
            ['TRY', 949, 2],
            ['EUR', 978, 2],
            ['PLN', 985, 2],
            ['BRL', 986, 2]
        ];
        $supported  = [];
        foreach ($currencies as $currency) {
            $supported [] = new static(...$currency);
        }

        return $supported;
    }

    /**
     * CONSTRUCTEUR.
     *
     * @param string $alpha3 Identifiant de qualification alphabétique à 3 caractères.
     * @param int $num Identifiant de qualification numérique.
     * @param int $decimals Nombre de décimales autorisées.
     *
     * @return void
     */
    public function __construct(string $alpha3, int $num, int $decimals = 2)
    {
        $this->alpha3   = $alpha3;
        $this->num      = $num;
        $this->decimals = $decimals;
    }

    /**
     * Convertion d'un tarif numerique en flottant.
     *
     * @param $integer
     *
     * @return float
     */
    public function amountToFloat($integer): float
    {
        $coef = pow(10, $this->decimals);

        return ((float)$integer) / $coef;
    }

    /**
     * Convertion d'un tarif flottant vers sa valeur numérique.
     *
     * @param float $float
     *
     * @return int
     */
    public function amountToInt(float $float): int
    {
        $coef   = pow(10, $this->decimals);
        $amount = $float * $coef;

        return (int)(string)$amount;
    }

    /**
     * Récupération de l'identifiant de qualification alphabétique à 3 caractères.
     *
     * @return string
     */
    public function getAlpha3(): string
    {
        return $this->alpha3;
    }

    /**
     * Récupération du nombre de décimales autorisées.
     *
     * @return int
     */
    public function getDecimals(): int
    {
        return $this->decimals;
    }

    /**
     * Récupération de l'identifiant de qualification numérique.
     *
     * @return int
     */
    public function getNum(): int
    {
        return $this->num;
    }
}