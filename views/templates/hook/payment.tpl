{*
 * 2008 - 2021 Wasa Kredit B2B
 *
 * MODULE Wasa Kredit
 *
 * @version   1.0.0
 * @author    Wasa Kredit AB
 * @link      http://www.wasakredit.se
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
*}

<p class="payment_module">
	<a href="{$link->getModuleLink('ps_checkpayment', 'leasingpayment', [], true)|escape:'html'}">
		<img src="{$this_path_ps_checkpayment}logo.png" alt="{l s='Pay by check' d='Modules.Checkpayment.Shop'}" />
		{l s='Pay by check' d='Modules.Checkpayment.Shop'} {l s='(order processing will be longer)' d='Modules.Checkpayment.Shop'}
	</a>
</p>
