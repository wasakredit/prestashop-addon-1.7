<?php
/**
 * @author    Wasa Kredit B2B <ehandel@wasakredit.se>
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
 */

function wasa_config($key = '')
{
    $wasa_configuration = array(
        'base_url' => 'https://b2b.services.wasakredit.se',
        'access_token_url' => 'https://b2b.services.wasakredit.se/auth/connect/token'
    );

    return isset($wasa_configuration[$key]) ? $wasa_configuration[$key] : null;
}
