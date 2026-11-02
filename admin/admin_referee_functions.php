<?php

/*
|--------------------------------------------------------------------------
| Referees (appointed officials of a tournament)
|--------------------------------------------------------------------------
| Managed on admin_referees.php, chosen per match in the score dialog on
| admin_draw.php (matches.refereeID). A referee who already has matches is
| made inactive instead of deleted, so old matches keep the name.
*/

// Saved in capitals, the same as players.player_nationality
function refereeNationalities()
{
    return [
        'AFGHAN' => 'Afghan',
        'ALBANIAN' => 'Albanian',
        'ALGERIAN' => 'Algerian',
        'AMERICAN' => 'American',
        'ANDORRAN' => 'Andorran',
        'ANGOLAN' => 'Angolan',
        'ARGENTINEAN' => 'Argentinean',
        'ARMENIAN' => 'Armenian',
        'AUSTRALIAN' => 'Australian',
        'AUSTRIAN' => 'Austrian',
        'AZERBAIJANI' => 'Azerbaijani',
        'BANGLADESHI' => 'Bangladeshi',
        'BELARUSIAN' => 'Belarusian',
        'BELGIAN' => 'Belgian',
        'BRAZILIAN' => 'Brazilian',
        'BRITISH' => 'British',
        'BRUNEIAN' => 'Bruneian',
        'BULGARIAN' => 'Bulgarian',
        'BURMESE' => 'Burmese',
        'CAMBODIAN' => 'Cambodian',
        'CANADIAN' => 'Canadian',
        'CHINESE' => 'Chinese',
        'COLOMBIAN' => 'Colombian',
        'CROATIAN' => 'Croatian',
        'CZECH' => 'Czech',
        'DANISH' => 'Danish',
        'DUTCH' => 'Dutch',
        'EGYPTIAN' => 'Egyptian',
        'FIJIAN' => 'Fijian',
        'FILIPINO' => 'Filipino',
        'FINNISH' => 'Finnish',
        'FRENCH' => 'French',
        'GERMAN' => 'German',
        'GHANAIAN' => 'Ghanaian',
        'GREEK' => 'Greek',
        'HONG KONG' => 'Hong Kong',
        'HUNGARIAN' => 'Hungarian',
        'ICELANDER' => 'Icelander',
        'INDIAN' => 'Indian',
        'INDONESIAN' => 'Indonesian',
        'IRANIAN' => 'Iranian',
        'IRAQI' => 'Iraqi',
        'IRISH' => 'Irish',
        'ISRAELI' => 'Israeli',
        'ITALIAN' => 'Italian',
        'JAMAICAN' => 'Jamaican',
        'JAPANESE' => 'Japanese',
        'JORDANIAN' => 'Jordanian',
        'KAZAKHSTANI' => 'Kazakhstani',
        'KENYAN' => 'Kenyan',
        'KOREAN' => 'Korean',
        'KUWAITI' => 'Kuwaiti',
        'LAOTIAN' => 'Laotian',
        'LATVIAN' => 'Latvian',
        'LEBANESE' => 'Lebanese',
        'LIBYAN' => 'Libyan',
        'LITHUANIAN' => 'Lithuanian',
        'MALAYSIAN' => 'Malaysian',
        'MEXICAN' => 'Mexican',
        'MONGOLIAN' => 'Mongolian',
        'MOROCCAN' => 'Moroccan',
        'MYANMAR' => 'Myanmar',
        'NEPALESE' => 'Nepalese',
        'NEW ZEALANDER' => 'New Zealander',
        'NIGERIAN' => 'Nigerian',
        'NORWEGIAN' => 'Norwegian',
        'PAKISTANI' => 'Pakistani',
        'PERUVIAN' => 'Peruvian',
        'POLISH' => 'Polish',
        'PORTUGUESE' => 'Portuguese',
        'QATARIAN' => 'Qatari',
        'ROMANIAN' => 'Romanian',
        'RUSSIAN' => 'Russian',
        'SAUDI' => 'Saudi Arabian',
        'SERBIAN' => 'Serbian',
        'SINGAPOREAN' => 'Singaporean',
        'SLOVAK' => 'Slovak',
        'SLOVENIAN' => 'Slovenian',
        'SOUTH AFRICAN' => 'South African',
        'SPANISH' => 'Spanish',
        'SRI LANKAN' => 'Sri Lankan',
        'SWEDISH' => 'Swedish',
        'SWISS' => 'Swiss',
        'TAIWANESE' => 'Taiwanese',
        'THAI' => 'Thai',
        'TUNISIAN' => 'Tunisian',
        'TURKISH' => 'Turkish',
        'UKRAINIAN' => 'Ukrainian',
        'UNITED KINGDOM' => 'United Kingdom',
        'UNITED STATES' => 'United States',
        'UZBEKISTANI' => 'Uzbekistani',
        'VIETNAMESE' => 'Vietnamese',
        'YEMENI' => 'Yemeni',
        'ZAMBIAN' => 'Zambian',
        'ZIMBABWEAN' => 'Zimbabwean',
    ];
}


function refereeLevels()
{
    return ['Level 1', 'Level 2', 'Level 3', 'National', 'Regional', 'International'];
}


// All referees of a tournament with the number of matches each has, active first
function loadReferees($conn, $tournamentID)
{
    $stmt = $conn->prepare("
        SELECT r.*, COUNT(m.matchID) AS match_count
        FROM referees r
        LEFT JOIN matches m
            ON m.refereeID = r.refereeID
        WHERE r.tournamentID = ?
        GROUP BY r.refereeID
        ORDER BY r.is_active DESC, r.referee_name ASC
    ");
    $stmt->bind_param("i", $tournamentID);
    $stmt->execute();

    $referees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $referees;
}
