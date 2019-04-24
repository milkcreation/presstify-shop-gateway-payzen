<?php declare(strict_types=1);

namespace tiFy\Plugins\ShopGatewayPayzen\Payzen;

class PayzenTransaction extends PayzenParamsBag
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
     * @inheritdoc
     */
    public function defaults(): array
    {
        return [
            'cancelled' => [
                /**
                 * Abandonné
                 * Paiement abandonné par l’acheteur.
                 * La transaction n’est pas créée et n’est donc pas visible dans le Back Office Marchand.
                 */
                'ABANDONED',
                /**
                 * Annulée
                 * La transaction est annulée par le marchand.
                 */
                'CANCELLED',
                /**
                 * Transaction non créée
                 * La transaction n'est pas créée et n'est pas visible dans le Back Office Marchand.
                 */
                'NOT_CREATED',
                /**
                 * Expirée
                 * La date d'expiration de la demande d'autorisation est atteinte et le marchand n’a pas validé la
                 * transaction. Le porteur ne sera donc pas débité.
                 */
                'EXPIRED',
                /**
                 * Refusée
                 * La transaction est refusée.
                 */
                'REFUSED',
                /**
                 * Suspendue
                 * La remise de la transaction est temporairement bloquée par l'acquéreur (AMEX GLOBAL ou SECURE
                 * TRADING). Une fois la remise traitée correctement, le statut de la transaction deviendra CAPTURED.
                 */
                'SUSPENDED'
            ],
            'accepted' => [
                /**
                 * Accepté.
                 * Statut d'une transaction de type VERIFICATION dont l'autorisation ou la demande de renseignement à
                 * été acceptée.
                 * Ce statut ne peut évoluer. Les transactions dont le statut est "ACCEPTED" ne sont jamais remises en
                 * banque.
                 */
                'ACCEPTED',
                /**
                 * En attente de remise
                 * La transaction est acceptée et sera remise en banque automatiquement à la date prévue.
                 */
                'AUTHORISED',
                /**
                 * A valider
                 * La transaction, créée en validation manuelle, est autorisée.
                 * Le marchand doit valider manuellement la transaction afin qu'elle soit remise en banque.
                 * La transaction peut être validée tant que la date d'expiration de la demande d'autorisation n’est pas
                 * dépassée. Si cette date est dépassée alors le paiement prend le statut EXPIRED. Le statut Expiré est
                 * définitif.
                 */
                'AUTHORISED_TO_VALIDATE',
                /**
                 * Remisée
                 * La transaction est remise en banque.
                 */
                'CAPTURED',
                /**
                 * La remise de la transaction a échoué.
                 * Contactez le Support. La tentative de capture va être refaite.
                 */
                'CAPTURE_FAILED',
            ],
            'pending' => [
                /**
                 * En attente
                 * Ce statut est spécifique à tous les moyens de paiement nécessitant une intégration par formulaire de
                 * paiement en redirection.
                 * Ce statut est retourné lorsque :
                 * • aucune réponse n'est renvoyée par l'acquéreur
                 * ou
                 * • le délai de réponse de la part de l'acquéreur est supérieur à la durée de session du paiement sur
                 * la plateforme de paiement.
                 * Ce statut est temporaire. Le statut définitif sera affiché dans le Back Office Marchand aussitôt la
                 * synchronisation réalisée.
                 */
                'INITIAL',
                /**
                 * Pour les transactions PayPal, cette valeur signifie que PayPal retient la transaction pour suspicion
                 * de fraude. Le paiement restera dans l’onglet Paiement en cours jusqu'à ce que les vérifications
                 * soient achevées. La transaction prendra alors l'un des statuts suivants: AUTHORISED ou CANCELED.
                 * Une notification sera envoyée au marchand pour l'avertir du changement de statut (Notification sur
                 * modification par batch).
                 */
                'UNDER_VERIFICATION',
                /**
                 * En attente d’autorisation
                 * Le délai de remise en banque est supérieur à la durée de validité de l'autorisation.
                 */
                'WAITING_AUTHORISATION',
                /**
                 * A valider et autoriser
                 * Le délai de remise en banque est supérieur à la durée de validité de l'autorisation.
                 * Une autorisation 1 EUR (ou demande de renseignement sur le réseau CB si l'acquéreur le supporte) a
                 * été acceptée. Le marchand doit valider manuellement la transaction afin que la demande
                 * d’autorisation et la remise aient lieu.
                 */
                'WAITING_AUTHORISATION_TO_VALIDATE'
            ]
        ];
    }

    /**
     * Récupération de l'identifiant de qualification de la transaction.
     *
     * @return int
     */
    public function id(): int
    {
        return (int)$this->payzen->response()->get('trans_id', 0);
    }

    /**
     * Vérifie si le paiement a été effectué avec succés.
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return in_array($this->status(), $this->get('accepted', [])) || $this->isPending();
    }

    /**
     * Vérifie si le paiement a été abandonné par le client.
     *
     * @return bool
     */
    public function isAbandonned(): bool
    {
        return $this->status() === 'ABANDONED';
    }

    /**
     * Check if the payment process was interrupted by the client.
     * @return bool
     */
    public function isCancelled(): bool
    {
        return in_array($this->status(), $this->get('cancelled', []));
    }

    /**
     * Check if the payment is waiting confirmation (successful but the amount has not been
     * transfered and is not yet guaranteed).
     * @return bool
     */
    public function isPending(): bool
    {
        return in_array($this->status(), $this->get('pending', []));
    }

    /**
     * Récupération du statut de transaction.
     *
     * @return string
     */
    public function status(): string
    {
        return $this->payzen->response()->get('trans_status', '');
    }
}