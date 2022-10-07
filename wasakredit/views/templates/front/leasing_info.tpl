{*
 * 2008 - 2022 Wasa Kredit B2B
 *
 * MODULE Wasa Kredit
 *
 * @version   1.0.0
 * @author    Wasa Kredit AB
 * @link      http://www.wasakredit.se
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
*}

<img class="wasa-kredit-payment-logo" src="{$logo nofilter}" width="150" />
<h5 class="wasa-kredit-payment-header">
    Finansiera ditt köp med Wasa Kredit leasing
</h5>
<p class="wasa-kredit-payment-features">
    {foreach from=$options item=option}
        - <span>{$option.monthly_cost.amount}</span> kr/mån i {$option.contract_length} månader<br>
    {/foreach}
</p>
