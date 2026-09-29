<?php

/*
|--------------------------------------------------------------------------
| Age / Category Check
|--------------------------------------------------------------------------
| A player in BU13 / GU13 ("Boys Under 13 Open Championship" too) must be
| under 13 on the tournament's age cut-off date. Playing up is allowed.
|
| Returns null when the registration is fine or can't be checked:
|   - the tournament has no age cut-off date
|   - the category has no age (Men / Women)
|   - the player has no date of birth
| Otherwise returns ['age' => age on cut-off date, 'max' => category age].
*/

function wrongAgeCategory($category, $dob, $cutoff)
{
    if (empty($cutoff) || strpos((string) $cutoff, '0000') === 0) {
        return null;
    }

    if (empty($dob) || strpos((string) $dob, '0000') === 0) {
        return null;
    }

    $category = trim((string) $category);

    if (preg_match('/^[BG]U(\d+)$/i', $category, $matches)) {
        $maxAge = (int) $matches[1];
    } elseif (preg_match('/\bunder\s*(\d+)/i', $category, $matches)) {
        $maxAge = (int) $matches[1];
    } else {
        return null;
    }

    try {
        $age = (new DateTime(date('Y-m-d', strtotime($dob))))
            ->diff(new DateTime(date('Y-m-d', strtotime($cutoff))))
            ->y;
    } catch (Exception $e) {
        return null;
    }

    return $age < $maxAge ? null : ['age' => $age, 'max' => $maxAge];
}


/*
|--------------------------------------------------------------------------
| Correct Category
|--------------------------------------------------------------------------
| The youngest category of the same group (Boys = BU, Girls = GU) in the
| tournament that the player is still under the age of.
| e.g. a 16-year-old registered in BU13 -> BU17 (if the tournament has it).
| Returns null when the tournament has no category for that age.
*/

function correctAgeCategory($category, $age, $tournamentCategories)
{
    $category = trim((string) $category);

    if (preg_match('/^([BG])U\d+$/i', $category, $matches)) {
        $prefix = strtoupper($matches[1]);
    } elseif (preg_match('/\b(boys?|girls?)\b/i', $category, $matches)) {
        $prefix = strtolower($matches[1][0]) === 'b' ? 'B' : 'G';
    } else {
        return null;
    }

    $best = null;

    foreach ($tournamentCategories as $option) {

        $option = trim((string) $option);

        if (preg_match('/^([BG])U(\d+)$/i', $option, $matches)) {
            $optionPrefix = strtoupper($matches[1]);
            $optionAge = (int) $matches[2];
        } elseif (preg_match('/\b(boys?|girls?)\b.*?\bunder\s*(\d+)/i', $option, $matches)) {
            $optionPrefix = strtolower($matches[1][0]) === 'b' ? 'B' : 'G';
            $optionAge = (int) $matches[2];
        } else {
            continue;
        }

        if ($optionPrefix !== $prefix || $age >= $optionAge) {
            continue;
        }

        if ($best === null || $optionAge < $best['age']) {
            $best = ['code' => $prefix . 'U' . str_pad($optionAge, 2, '0', STR_PAD_LEFT), 'age' => $optionAge];
        }
    }

    return $best === null ? null : $best['code'];
}


/*
|--------------------------------------------------------------------------
| Wrong Category Message
|--------------------------------------------------------------------------
| "Connor Haberecht is currently registered in BU13, but the correct
|  category is BU17."
*/

function wrongCategoryMessage($name, $category, $correctCategory, $age)
{
    $message = $name . ' is currently registered in ' . $category . ', but ';

    return $correctCategory !== null
        ? $message . 'the correct category is ' . $correctCategory . '.'
        : $message . 'there is no category in this tournament for age ' . $age . '.';
}
