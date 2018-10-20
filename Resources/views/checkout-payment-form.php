<?php
/**
 * @var \tiFy\Plugins\Shop\Orders\OrderInterface $order
 * @var \PayzenRequest $request
 */
?>

<div>
    <p><?php _e('Merci de bien vouloir patienter, vous allez être redirigé vers la plateforme de paiement', 'tify'); ?></p>
    <p><?php _e('Si la redirection ne s\'est pas effectuée au bout de 10 secondes, cliquer sur le bouton "valider".', 'tify'); ?></p>
    <form action="<?php echo esc_url($request->get('platform_url')); ?>" method="post" name="payzen_payment_form" target="_top">
        <?php echo $request->getRequestHtmlFields();?>

        <input type="submit" class="button-alt" id="submit_payzen_payment_form" value="<?php _e('Envoyer', 'tify'); ?>" />
    </form>
</div>

<?php
/*
<script type="text/javascript">
    function payzen_submit_form() { document.getElementById('submit_payzen_payment_form').click(); }
    if (window.addEventListener) { // for all major browsers, except IE 8 and earlier
        window.addEventListener('load', payzen_submit_form, false);
    } else if (window.attachEvent) { // for IE 8 and earlier versions
        window.attachEvent('onload', payzen_submit_form);
    }
</script>
 */
?>