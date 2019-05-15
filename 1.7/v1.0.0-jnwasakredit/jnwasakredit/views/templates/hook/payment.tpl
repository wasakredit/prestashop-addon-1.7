{*
 * 2008 - 2017 Wasa Kredit B2B
 *
 * MODULE Wasa Kredit
 *
 * @version   1.0.0
 * @author    Jarda Nalezny <jaroslav@nalezny.cz>
 * @link      http://www.presto-changeo.com
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
*}

<p class="payment_module">
	<a href="{$link->getModuleLink('ps_checkpayment', 'payment', [], true)|escape:'html'}">
		<img src="{$this_path_ps_checkpayment}logo.png" alt="{l s='Pay by check' d='Modules.Checkpayment.Shop'}" />
		{l s='Pay by check' d='Modules.Checkpayment.Shop'} {l s='(order processing will be longer)' d='Modules.Checkpayment.Shop'}
	</a>
</p>
