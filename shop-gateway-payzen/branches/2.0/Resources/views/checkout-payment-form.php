<?php
/**
 * @var tiFy\Plugins\Shop\Contracts\OrderInterface $order
 * @var tiFy\Plugins\ShopGatewayPayzen\Payzen\PayzenRequest $request
 */
?>

<div>
    <p><?php _e('Merci de bien vouloir patienter, vous allez être redirigé vers la plateforme de paiement', 'tify'); ?></p>
    <p><?php _e('Si la redirection ne s\'est pas effectuée au bout de 10 secondes, cliquer sur le bouton "valider".', 'tify'); ?></p>
    <form method="POST" action="https://secure.payzen.eu/vads-payment/"  name="payzen_payment_form" target="_top">
        <?php foreach($request->getHtmlFields() as $field) : ?>
            <?php echo $field; ?>
        <?php endforeach; ?>
        <input type="submit" class="button-alt" id="submit_payzen_payment_form" value="<?php _e('Envoyer', 'tify'); ?>" />
    </form>
</div>

<script type="text/javascript">
    function payzen_submit_form() {
        document.getElementById('submit_payzen_payment_form').click();
    }

    if (window.addEventListener) {
        window.addEventListener('load', payzen_submit_form, false);
    } else if (window.attachEvent) {
        window.attachEvent('onload', payzen_submit_form);
    }
</script>