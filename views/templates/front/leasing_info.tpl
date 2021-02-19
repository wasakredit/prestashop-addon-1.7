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
<img class="wasa_checkout_logo" src="{$logo}"/>
<h5 class="wasa-header">Finansiera ditt köp med Wasa Kredit leasing</h5>
<ul class="checkout_options_wasa_wrapper">
	{foreach from=$options item=option}
		<li><span class="checkout_option_wasa">{$option.monthly_cost.amount}</span> kr/mån i {$option.contract_length} månader.</li>
	{/foreach}
</ul>
