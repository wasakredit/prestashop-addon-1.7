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
<img class="wasa_checkout_logo" src="{$logo}" />
<h5>Finansiera ditt köp med Wasa Kredit leasing</h5>
<ul>
	{foreach $payments as $payment}
		<li><span class="checkout_line_wasa">{$payment->monthly_cost->amount} kr</span>/mån i {$payment->contract_length} månader.</li>
	{/foreach}
</ul>
