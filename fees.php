<?php

/*
|--------------------------------------------------------------------------
| Tournament Fees
|--------------------------------------------------------------------------
| Fees are stored in one column (tournament.tournament_fee).
|
|   Local tournament:          "RM 100.00"
|   International tournament:  "Local: RM120.00, Foreign: USD 60.00"
|
| Older tournaments use other wordings, which are read as they are:
|   "Foreign Players - USD120.00  & Local Players - RM70.00"
|   "RM 100.00 per person (local)  & USD 100 per person (foreign); must be paid to ..."
|   "Local: RM100.00 Foreign: USD100.00 **  Payment should be made to ..."
|
| Whenever a fee has both an RM amount and a USD amount, the RM amount is
| the Local fee (Malaysian players) and the USD amount is the Foreign fee
| (foreign players). The saved text itself is never changed.
*/


// "RM 120.00" / "USD60" / "$60" -> "120.00" / "60" / "60"
function feeAmount($value)
{
    return trim(preg_replace('/^\s*(RM|MYR|USD|US\$|\$)\s*/i', '', (string) $value));
}


// Two amounts -> "Local: RM120.00, Foreign: USD 60.00"
function buildInternationalFee($localAmount, $foreignAmount)
{
    return 'Local: RM' . feeAmount($localAmount) . ', Foreign: USD ' . feeAmount($foreignAmount);
}


// Currency of a fee text: 'RM', 'USD', 'RM+USD' or null (SGD, INR, no amount, ...)
function feeCurrency($fee)
{
    $hasRM = preg_match('/\b(?:RM|MYR)\s*\d/i', (string) $fee);
    $hasUSD = preg_match('/\b(?:USD|US\$)\s*\$?\s*\d/i', (string) $fee);

    if ($hasRM && $hasUSD) {
        return 'RM+USD';
    }

    return $hasRM ? 'RM' : ($hasUSD ? 'USD' : null);
}


/*
| Returns:
|   'local'    => RM fee ("RM70.00"), or the whole text when there is no
|                 RM + USD pair
|   'foreign'  => USD fee ("USD120.00"), or null
|   'details'  => the whole saved text when it says more than the two
|                 amounts (e.g. payment instructions), otherwise null
|   'currency' => 'RM+USD', 'RM', 'USD' or null (see feeCurrency)
*/
function splitFee($fee)
{
    $fee = trim((string) $fee);

    $hasRM = preg_match('/\b(?:RM|MYR)\s*\d[\d,]*(?:\.\d+)?/i', $fee, $rm);
    $hasUSD = preg_match('/\b(?:USD|US\$)\s*\$?\s*\d[\d,]*(?:\.\d+)?/i', $fee, $usd);

    if (!$hasRM || !$hasUSD) {
        return ['local' => $fee, 'foreign' => null, 'details' => null, 'currency' => feeCurrency($fee)];
    }

    $local = trim($rm[0]);
    $foreign = trim($usd[0]);

    // Nothing more than the standard "Local: RM.., Foreign: USD .." text
    $isPlain = strcasecmp($fee, buildInternationalFee($local, $foreign)) === 0
        || preg_match('/^\s*Local\s*:\s*\S+(\s*\d\S*)?\s*,?\s*Foreign\s*:\s*\S+(\s*\d\S*)?\s*$/i', $fee);

    return [
        'local' => $local,
        'foreign' => $foreign,
        'details' => $isPlain ? null : $fee,
        'currency' => 'RM+USD'
    ];
}


/*
| The fee for the form fields, by currency:
|   RM + USD -> RM field + USD field
|   USD only -> USD field
|   anything else -> RM field
*/
function feeFields($fee)
{
    $parts = splitFee($fee);

    if ($parts['currency'] === 'USD') {
        return ['rm' => '', 'usd' => $parts['local'], 'details' => null];
    }

    return ['rm' => $parts['local'], 'usd' => $parts['foreign'] ?? '', 'details' => $parts['details']];
}


/*
| The two form fields -> the one saved fee:
|   both   -> "Local: RM120.00, Foreign: USD 60.00"
|   one    -> that fee as typed
*/
function combineFeeFields($rmField, $usdField)
{
    $rmField = trim((string) $rmField);
    $usdField = trim((string) $usdField);

    if ($rmField !== '' && $usdField !== '') {
        return buildInternationalFee($rmField, $usdField);
    }

    // A plain amount ("100.00") gets its currency, so it's shown in the right place
    if ($rmField !== '') {
        return preg_match('/^\d/', $rmField) ? 'RM' . $rmField : $rmField;
    }

    return preg_match('/^\d/', $usdField) ? 'USD ' . $usdField : $usdField;
}


// Which fee a player pays: ['fee' => ..., 'label' => 'Local' | 'Foreign' | '']
function feeForPlayer($fee, $nationality)
{
    $fees = splitFee($fee);

    $isMalaysian = stripos((string) $nationality, 'malaysia') === 0;

    if ($fees['foreign'] !== null && !$isMalaysian) {
        return ['fee' => $fees['foreign'], 'label' => 'Foreign'];
    }

    return ['fee' => $fees['local'], 'label' => $fees['foreign'] !== null ? 'Local' : ''];
}
