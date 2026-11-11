<?php
/**
 * Customer-facing English / Sinhala UI.
 * The choice is stored in the ui_lang cookie so it survives login
 * and applies on every donor page. Admin pages do not use this file.
 */

function ui_lang(): string {
    $lang = $_COOKIE['ui_lang'] ?? 'en';
    return $lang === 'si' ? 'si' : 'en';
}

function ui_capture_language(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !isset($_GET['lang'])) {
        return;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/api/') !== false || strpos($script, '/admin/') !== false) {
        return;
    }

    $lang = $_GET['lang'] === 'si' ? 'si' : ($_GET['lang'] === 'en' ? 'en' : '');
    if ($lang === '') {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('ui_lang', $lang, [
        'expires' => time() + 365 * 24 * 60 * 60,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['ui_lang'] = $lang;

    $params = $_GET;
    unset($params['lang']);
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!is_string($path) || $path === '' || strpos($path, '//') !== false) {
        $path = '/';
    }
    $query = http_build_query($params);
    header('Location: ' . $path . ($query !== '' ? '?' . $query : ''));
    exit;
}

function t(string $en, string $si): string {
    return ui_lang() === 'si' ? $si : $en;
}

function te(string $en, string $si): string {
    return htmlspecialchars(t($en, $si), ENT_QUOTES, 'UTF-8');
}

function ui_html_open(): string {
    $lang = ui_lang();
    return '<html lang="' . $lang . '" data-lang="' . $lang . '">';
}

function ui_lang_href(string $lang): string {
    $parts = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    $path = is_array($parts) ? ($parts['path'] ?? '/') : '/';
    $query = [];
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['lang'] = $lang === 'si' ? 'si' : 'en';
    return htmlspecialchars($path . '?' . http_build_query($query), ENT_QUOTES, 'UTF-8');
}

function ui_lang_switch(string $class = 'ui-lang-switch'): void {
    $lang = ui_lang();
    echo '<nav class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-label="' . te('Language', 'භාෂාව') . '">';
    echo '<a class="' . ($lang === 'en' ? 'is-active' : '') . '" lang="en" hreflang="en" href="' . ui_lang_href('en') . '">English</a>';
    echo '<a class="' . ($lang === 'si' ? 'is-active' : '') . '" lang="si" hreflang="si" href="' . ui_lang_href('si') . '">සිංහල</a>';
    echo '</nav>';
    static $scriptSent = false;
    if (!$scriptSent) {
        $scriptSent = true;
        // ui_lang is HttpOnly, so script cannot see it. Clear the old
        // dashboard preference before redirecting, or the next page loops.
        echo '<script>(function(){try{var saved=localStorage.getItem("preferredLanguage");localStorage.removeItem("preferredLanguage");if(saved!=="si"&&saved!=="en")return;var url=new URL(location.href);if(url.searchParams.get("lang")===saved)return;url.searchParams.set("lang",saved);location.replace(url.toString());}catch(e){}})();</script>';
    }
    ui_client_boot();
}

function ui_month_name(int $month): string {
    $en = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $si = [1 => 'ජනවාරි', 'පෙබරවාරි', 'මාර්තු', 'අප්‍රේල්', 'මැයි', 'ජූනි', 'ජූලි', 'අගෝස්තු', 'සැප්තැම්බර්', 'ඔක්තෝබර්', 'නොවැම්බර්', 'දෙසැම්බර්'];
    $names = ui_lang() === 'si' ? $si : $en;
    return $names[$month] ?? '';
}

function ui_weekday_short(int $weekday): string {
    $en = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $si = ['ඉරි', 'සඳු', 'අඟ', 'බදා', 'බ්‍රහ', 'සිකු', 'සෙන'];
    $names = ui_lang() === 'si' ? $si : $en;
    return $names[$weekday] ?? '';
}

function ui_format_date($value, string $style = 'medium'): string {
    $timestamp = is_numeric($value) ? (int)$value : strtotime((string)$value);
    if (!$timestamp) {
        return '';
    }
    if (ui_lang() !== 'si') {
        if ($style === 'long') {
            return date('l, F j, Y', $timestamp);
        }
        if ($style === 'datetime') {
            return date('F j, Y g:i A', $timestamp);
        }
        return date('M j, Y', $timestamp);
    }
    $weekdays = ['ඉරිදා', 'සඳුදා', 'අඟහරුවාදා', 'බදාදා', 'බ්‍රහස්පතින්දා', 'සිකුරාදා', 'සෙනසුරාදා'];
    $month = ui_month_name((int)date('n', $timestamp));
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
    if ($style === 'long') {
        return $weekdays[(int)date('w', $timestamp)] . ', ' . $year . ' ' . $month . ' ' . $day;
    }
    if ($style === 'datetime') {
        return $year . ' ' . $month . ' ' . $day . ' ' . date('g:i A', $timestamp);
    }
    return $year . ' ' . $month . ' ' . $day;
}

function ui_date_keep($value, string $englishFormat, string $style = 'medium'): string {
    $timestamp = is_numeric($value) ? (int)$value : strtotime((string)$value);
    if (!$timestamp) {
        return '';
    }
    if (ui_lang() !== 'si') {
        return date($englishFormat, $timestamp);
    }
    return ui_format_date($timestamp, $style);
}

function ui_status(string $status): string {
    $labels = [
        'pending' => t('Pending', 'පොරොත්තුවෙන්'),
        'receipt_submitted' => t('Receipt submitted', 'රිසිට්පත යවා ඇත'),
        'payment_pending' => t('Payment pending', 'ගෙවීම පොරොත්තුවෙන්'),
        'confirmed' => t('Confirmed', 'තහවුරු කළ'),
        'completed' => t('Completed', 'සම්පූර්ණයි'),
        'cancelled' => t('Cancelled', 'අවලංගුයි'),
        'expired' => t('Expired', 'කල් ඉකුත් විය'),
    ];
    $key = strtolower(trim($status));
    return $labels[$key] ?? ucfirst(str_replace('_', ' ', $status));
}

function ui_dhana_name(string $name): string {
    if (ui_lang() !== 'si') {
        return $name;
    }
    $map = [
        'Whole Day' => 'සම්පූර්ණ දිනය',
        'Lunch Dāna' => 'දිවා දානය',
        'Lunch Dana' => 'දිවා දානය',
        'Morning Dāna' => 'උදෑසන දානය',
        'Morning Dana' => 'උදෑසන දානය',
        'Extra Item' => 'අමතර අයිතම',
    ];
    return $map[$name] ?? $name;
}

function ui_time_slot(string $slot): string {
    $labels = [
        'morning' => t('Morning Dāna', 'උදෑසන දානය'),
        'lunch' => t('Lunch Dāna', 'දිවා දානය'),
        'whole_day' => t('Whole Day', 'සම්පූර්ණ දිනය'),
    ];
    return $labels[$slot] ?? ucfirst(str_replace('_', ' ', $slot));
}

function ui_payment_method(string $method): string {
    $labels = [
        'bank_transfer' => t('Bank Transfer', 'බැංකු මාරුව'),
        'online_banking' => t('Online Banking', 'ඔන්ලයින් බැංකුකරණය'),
        'mobile_banking' => t('Mobile Banking', 'ජංගම බැංකුකරණය'),
        'cash_deposit' => t('Cash Deposit', 'මුදල් තැන්පතුව'),
    ];
    return $labels[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

function ui_js_strings(): array {
    return [
        'Date Not Available' => 'දිනය ලබා ගත නොහැක',
        'This date is blocked and not available for booking.' => 'මෙම දිනය අවහිර කර ඇති නිසා වෙන්කිරීමට ලබා ගත නොහැක.',
        'Booked (Whole Day)' => 'වෙන් වී ඇත (සම්පූර්ණ දිනය)',
        'Available' => 'ලබා ගත හැක',
        'Not Available' => 'ලබා ගත නොහැක',
        'Blocked by whole day booking' => 'සම්පූර්ණ දින වෙන්කිරීමක් නිසා අවහිරයි',
        'Blocked by existing bookings' => 'පවතින වෙන්කිරීම් නිසා අවහිරයි',
        'Booked' => 'වෙන් වී ඇත',
        'Great! This date has available slots.' => 'මෙම දිනයේ වෙන්කිරීම් සඳහා ඉඩ ඇත.',
        'Only extra items are available (whole day is booked).' => 'අමතර අයිතම පමණක් ලබා ගත හැක (සම්පූර්ණ දිනය වෙන් වී ඇත).',
        'Sorry, this date is fully booked.' => 'කණගාටුයි, මෙම දිනය සම්පූර්ණයෙන් වෙන් වී ඇත.',
        'This date is blocked due to a whole day booking.' => 'සම්පූර්ණ දින වෙන්කිරීමක් නිසා මෙම දිනය අවහිරයි.',
        'This date has existing bookings that prevent whole day booking.' => 'පවතින වෙන්කිරීම් නිසා සම්පූර්ණ දිනය වෙන්කළ නොහැක.',
        'Coming Soon' => 'ඉක්මනින්',
        'Choose a month and year' => 'මාසයක් සහ වසරක් තෝරන්න',
        'Click to jump to a specific month' => 'මාසයකට යාමට ඔබන්න',
        'Select Reservation Date' => 'වෙන්කිරීමේ දිනය තෝරන්න',
        'Close date picker' => 'දින තේරීම වසන්න',
        'Year:' => 'වසර:',
        'Month:' => 'මාසය:',
        'Day:' => 'දිනය:',
        'Cancel' => 'අවලංගු කරන්න',
        'Select Date' => 'දිනය තෝරන්න',
        'No dāna types available. Please contact the administrator.' => 'දාන වර්ග ලබා ගත නොහැක. කරුණාකර පරිපාලකයා අමතන්න.',
        'Price will be shown after selecting date' => 'දිනය තේරූ පසු මිල පෙන්වයි',
        'Price may change' => 'මිල වෙනස් විය හැක',
        'Whole Day' => 'සම්පූර්ණ දිනය',
        'Morning Dāna' => 'උදෑසන දානය',
        'Lunch Dāna' => 'දිවා දානය',
        'Evening Dāna' => 'සවස දානය',
        'Flexible timing' => 'නම්‍යශීලී වේලාව',
        'Please enter the reservation recipient’s first name.' => 'වෙන්කිරීම ලබන්නාගේ මුල් නම ඇතුළත් කරන්න.',
        'Please enter the reservation recipient’s last name.' => 'වෙන්කිරීම ලබන්නාගේ අවසන් නම ඇතුළත් කරන්න.',
        'Please enter a valid primary mobile number for the reservation recipient.' => 'වෙන්කිරීම ලබන්නාගේ වලංගු ප්‍රධාන ජංගම අංකයක් ඇතුළත් කරන්න.',
        'Please enter a valid second mobile number, or leave it blank.' => 'වලංගු දෙවන ජංගම අංකයක් ඇතුළත් කරන්න, නැතහොත් හිස්ව තබන්න.',
        'Select time slot' => 'වේලාව තෝරන්න',
        'Please select dāna type, date, and time slot first.' => 'පළමුව දාන වර්ගය, දිනය සහ වේලාව තෝරන්න.',
        'Checking availability...' => 'ලබා ගත හැකිදැයි පරීක්ෂා කරමින්...',
        'Availability check failed' => 'ලබා ගැනීම පරීක්ෂා කිරීම අසාර්ථකයි',
        'Great! This slot is available for reservation.' => 'මෙම වේලාව වෙන්කිරීමට ලබා ගත හැක.',
        'Sorry, this slot is already booked. Please choose a different date or time.' => 'කණගාටුයි, මෙම වේලාව දැනටමත් වෙන් වී ඇත. වෙනත් දිනයක් හෝ වේලාවක් තෝරන්න.',
        'Unable to check availability. Please try again.' => 'ලබා ගැනීම පරීක්ෂා කළ නොහැක. නැවත උත්සාහ කරන්න.',
        'Checking availability for your selected date and time...' => 'තෝරාගත් දිනය සහ වේලාව පරීක්ෂා කරමින්...',
        'Unknown' => 'නොදනී',
        'Calculating…' => 'ගණනය කරමින්…',
        'Yes' => 'ඔව්',
        'No' => 'නැත',
        'Not provided' => 'ලබා දී නැත',
        'Not selected' => 'තෝරා නැත',
        'Please select a dāna type' => 'දාන වර්ගයක් තෝරන්න',
        'Please select a reservation date' => 'වෙන්කිරීමේ දිනයක් තෝරන්න',
        'Please select a time slot' => 'වේලාවක් තෝරන්න',
        'Reservation date cannot be in the past' => 'වෙන්කිරීමේ දිනය අතීතයේ විය නොහැක',
        'Selected slot is not available. Please choose a different date or time.' => 'තෝරාගත් වේලාව ලබා ගත නොහැක. වෙනත් දිනයක් හෝ වේලාවක් තෝරන්න.',
        'Please return to the date step and verify your selection.' => 'දින පියවරට ආපසු ගොස් තේරීම පරීක්ෂා කරන්න.',
        'Please complete the recipient details and verify the selected date and time.' => 'ලබන්නාගේ විස්තර සම්පූර්ණ කර දිනය සහ වේලාව පරීක්ෂා කරන්න.',
        'This reservation can no longer be submitted. Please verify the date and time.' => 'මෙම වෙන්කිරීම ඉදිරිපත් කළ නොහැක. දිනය සහ වේලාව පරීක්ෂා කරන්න.',
        'Please select a dāna type first' => 'පළමුව දාන වර්ගයක් තෝරන්න',
        'Please select a date first' => 'පළමුව දිනයක් තෝරන්න',
        'Loading price breakdown...' => 'මිල විස්තරය පූරණය වෙමින්...',
        'The pricing service returned an invalid response.' => 'මිල සේවාවෙන් වලංගු නොවන ප්‍රතිචාරයක් ලැබුණි.',
        'Unable to load prices.' => 'මිල ලබා ගත නොහැක.',
        'Failed to load prices. Please try again.' => 'මිල පූරණය අසාර්ථකයි. නැවත උත්සාහ කරන්න.',
        'To be determined' => 'තීරණය වේ',
        'Show detailed breakdown' => 'විස්තර බලන්න',
        'Hide detailed breakdown' => 'විස්තර සඟවන්න',
        'Year' => 'වසර',
        'Date' => 'දිනය',
        'Price' => 'මිල',
        'Not set yet' => 'තවම නියම කර නැත',
        'Click to select date' => 'දිනය තේරීමට ඔබන්න',
        'Follow these steps to complete your booking' => 'වෙන්කිරීම සම්පූර්ණ කිරීමට මෙම පියවර අනුගමනය කරන්න',
        'Quick Stats' => 'කෙටි සාරාංශය',
        'Vinaya guidance accepted. You can continue creating the account.' => 'විනය මගපෙන්වීම පිළිගත්තා. ගිණුම සෑදීම දිගටම කළ හැක.',
        'Please read and accept the Vinaya guidance first.' => 'පළමුව විනය මගපෙන්වීම කියවා පිළිගන්න.',
        'Please wait…' => 'කරුණාකර රැඳෙන්න…',
        'Please enter a valid email address.' => 'වලංගු විද්‍යුත් තැපැල් ලිපිනයක් ඇතුළත් කරන්න.',
        'Passwords do not match.' => 'මුරපද නොගැලපේ.',
        'Error:' => 'දෝෂය:',
        'Uploading...' => 'උඩුගත කරමින්...',
        'Ready to upload' => 'උඩුගත කිරීමට සූදානම්',
        'Click to select receipt file or drag and drop' => 'රිසිට්පත් ගොනුව තේරීමට ඔබන්න හෝ ඇද දමන්න',
        'Supported formats: JPG, PNG, PDF (Max 5MB)' => 'JPG, PNG, PDF (උපරිම 5MB)',
        'Please select a valid file' => 'වලංගු ගොනුවක් තෝරන්න',
        'Failed to copy bank details. Please copy manually.' => 'බැංකු විස්තර පිටපත් කළ නොහැකි විය. අතින් පිටපත් කරන්න.',
        'Copied!' => 'පිටපත් විය!',
        'Size' => 'ප්‍රමාණය',
        'File error' => 'ගොනු දෝෂය',
        'with confirmed prices:' => 'තහවුරු මිල සහිත වසර',
        'with prices not set yet:' => 'මිල තවම නියම නොකළ වසර',
        'Years' => 'වසර',
    ];
}

function ui_client_boot(): void {
    static $sent = false;
    if ($sent) {
        return;
    }
    $sent = true;
    $strings = [];
    foreach (ui_js_strings() as $en => $si) {
        $strings[$en] = t($en, $si);
    }
    $months = [];
    for ($month = 1; $month <= 12; $month++) {
        $months[] = ui_month_name($month);
    }
    $weekdays = ui_lang() === 'si'
        ? ['ඉරිදා', 'සඳුදා', 'අඟහරුවාදා', 'බදාදා', 'බ්‍රහස්පතින්දා', 'සිකුරාදා', 'සෙනසුරාදා']
        : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $dhanaNames = ['Whole Day', 'Lunch Dāna', 'Lunch Dana', 'Morning Dāna', 'Morning Dana', 'Extra Item'];
    $dhana = [];
    foreach ($dhanaNames as $name) {
        $dhana[$name] = ui_dhana_name($name);
    }
    $descriptions = [];
    foreach ([
        'Complete day dāna offering' => 'සම්පූර්ණ දින දාන පූජාව',
        'Lunch time dāna offering' => 'දිවා දාන පූජාව',
        'Morning time dāna offering' => 'උදෑසන දාන පූජාව',
        'Additional items - Coming Soon' => 'අමතර අයිතම - ඉක්මනින්',
    ] as $en => $si) {
        $descriptions[$en] = t($en, $si);
    }
    $payload = [
        'lang' => ui_lang(),
        'months' => $months,
        'weekdaysLong' => $weekdays,
        'dhana' => $dhana,
        'descriptions' => $descriptions,
        's' => $strings,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script>window.UI=' . $json . ';'
        . 'window.uiText=function(k){var s=window.UI&&window.UI.s;return s&&Object.prototype.hasOwnProperty.call(s,k)?s[k]:k;};'
        . 'window.uiDhana=function(n){var m=window.UI&&window.UI.dhana;return (m&&m[n])||n;};'
        . 'window.uiDesc=function(n){var m=window.UI&&window.UI.descriptions;return (m&&m[n])||n;};'
        . 'window.uiFormatLongDate=function(d){if(!(d instanceof Date)||isNaN(d.getTime()))return "";if(!window.UI||window.UI.lang!=="si"){return d.toLocaleDateString("en-US",{weekday:"long",year:"numeric",month:"long",day:"numeric"});}return window.UI.weekdaysLong[d.getDay()]+", "+d.getFullYear()+" "+window.UI.months[d.getMonth()]+" "+d.getDate();};'
        . 'window.uiFormatMonthDate=function(d){if(!(d instanceof Date)||isNaN(d.getTime()))return "";if(!window.UI||window.UI.lang!=="si"){return d.toLocaleDateString("en-US",{year:"numeric",month:"long",day:"numeric"});}return d.getFullYear()+" "+window.UI.months[d.getMonth()]+" "+d.getDate();};'
        . 'window.uiFormatDateTime=function(d){if(!(d instanceof Date)||isNaN(d.getTime()))return "";var time=d.toLocaleTimeString("en-US",{hour:"numeric",minute:"2-digit"});if(!window.UI||window.UI.lang!=="si"){return d.toLocaleDateString("en-US",{year:"numeric",month:"long",day:"numeric",hour:"2-digit",minute:"2-digit",hour12:true});}return d.getFullYear()+" "+window.UI.months[d.getMonth()]+" "+d.getDate()+" "+time;};'
        . '</script>';
}

function t_msg(string $message): string {
    if (ui_lang() !== 'si') {
        return $message;
    }
    $map = [
        'Your session expired. Please refresh and try again.' => 'ඔබගේ සැසිය කල් ඉකුත් විය. පිටුව නැවුම් කර නැවත උත්සාහ කරන්න.',
        'Invalid email or password' => 'විද්‍යුත් තැපෑල හෝ මුරපදය වැරදිය.',
        'Account is deactivated' => 'මෙම ගිණුම අක්‍රිය කර ඇත.',
        'Login failed. Please try again.' => 'පිවිසීම අසාර්ථකයි. කරුණාකර නැවත උත්සාහ කරන්න.',
        'Too many sign-in attempts. Please wait five minutes and try again.' => 'පිවිසීමේ උත්සාහ බොහෝය. මිනිත්තු පහක් රැඳී නැවත උත්සාහ කරන්න.',
        'This email address is already registered. Please use a different email or try logging in.' => 'මෙම විද්‍යුත් තැපෑල දැනටමත් ලියාපදිංචි වී ඇත. වෙනත් ලිපිනයක් භාවිතා කරන්න, නැතහොත් පිවිසෙන්න.',
        'Registration failed. Please try again.' => 'ලියාපදිංචිය අසාර්ථකයි. කරුණාකර නැවත උත්සාහ කරන්න.',
        'First name must be between 2 and 50 characters' => 'මුල් නම අකුරු 2 සිට 50 දක්වා විය යුතුය.',
        'Last name must be between 2 and 50 characters' => 'අවසන් නම අකුරු 2 සිට 50 දක්වා විය යුතුය.',
        'Valid email is required' => 'වලංගු විද්‍යුත් තැපැල් ලිපිනයක් අවශ්‍යයි.',
        'Valid contact number is required' => 'වලංගු දුරකථන අංකයක් අවශ්‍යයි.',
        'Please enter a valid email address.' => 'වලංගු විද්‍යුත් තැපැල් ලිපිනයක් ඇතුළත් කරන්න.',
        'Please fill in all fields.' => 'සියලු ක්ෂේත්‍ර පුරවන්න.',
        'Passwords do not match.' => 'මුරපද නොගැලපේ.',
        'Invalid reset link. Please request a new password reset.' => 'යළි පිහිටුවීමේ සබැඳිය වලංගු නැත. නව සබැඳියක් ඉල්ලන්න.',
        'Invalid or expired reset token' => 'යළි පිහිටුවීමේ සබැඳිය වලංගු නැත හෝ කල් ඉකුත් වී ඇත.',
        'Failed to verify token' => 'සබැඳිය තහවුරු කළ නොහැක.',
        'Failed to reset password. Please try again.' => 'මුරපදය යළි පිහිටුවීම අසාර්ථකයි. කරුණාකර නැවත උත්සාහ කරන්න.',
        'Failed to process password reset request. Please try again.' => 'මුරපද ඉල්ලීම සැකසීම අසාර්ථකයි. කරුණාකර නැවත උත්සාහ කරන්න.',
        'If an account exists with this email, a password reset link has been sent.' => 'මෙම විද්‍යුත් තැපෑලට ගිණුමක් තිබේ නම්, මුරපදය යළි පිහිටුවීමේ සබැඳිය යවා ඇත.',
        'Password reset successfully. You can now login with your new password.' => 'මුරපදය යළි පිහිටුවන ලදි. නව මුරපදයෙන් පිවිසෙන්න.',
        'Account created successfully! Welcome to Dāna Reservation System.' => 'ගිණුම සාර්ථකව සාදන ලදි. දාන වෙන්කිරීමේ පද්ධතියට සාදරයෙන් පිළිගනිමු.',
        'All fields are required.' => 'සියලු ක්ෂේත්‍ර අවශ්‍යයි.',
        'One or more fields are too long.' => 'එක් ක්ෂේත්‍රයක් හෝ කිහිපයක් දිග වැඩිය.',
        'Email address already exists.' => 'මෙම විද්‍යුත් තැපෑල දැනටමත් ඇත.',
        'Profile updated successfully!' => 'පැතිකඩ සාර්ථකව යාවත්කාලීන කළා.',
        'Unable to update your profile right now. Please try again.' => 'දැන් පැතිකඩ යාවත්කාලීන කළ නොහැක. කරුණාකර නැවත උත්සාහ කරන්න.',
        'All password fields are required.' => 'මුරපද ක්ෂේත්‍ර සියල්ල අවශ්‍යයි.',
        'Current password is incorrect.' => 'වත්මන් මුරපදය වැරදිය.',
        'New passwords do not match.' => 'නව මුරපද නොගැලපේ.',
        'New password must be at least 6 characters long.' => 'නව මුරපදය අවම වශයෙන් අකුරු 6 ක් විය යුතුය.',
        'Password changed successfully!' => 'මුරපදය සාර්ථකව වෙනස් කළා.',
        'Unable to change your password right now. Please try again.' => 'දැන් මුරපදය වෙනස් කළ නොහැක. කරුණාකර නැවත උත්සාහ කරන්න.',
        'Please select an available dāna type.' => 'ලබා ගත හැකි දාන වර්ගයක් තෝරන්න.',
        'Please select a valid reservation date.' => 'වලංගු වෙන්කිරීමේ දිනයක් තෝරන්න.',
        'The selected time slot does not match the dāna type.' => 'තෝරාගත් වේලාව දාන වර්ගයට නොගැලපේ.',
        'Special requests must be 2,000 characters or fewer.' => 'විශේෂ ඉල්ලීම් අකුරු 2,000 ක් හෝ ඊට අඩු විය යුතුය.',
        'The reservation recipient’s first name must be between 2 and 50 characters.' => 'වෙන්කිරීම ලබන්නාගේ මුල් නම අකුරු 2 සිට 50 දක්වා විය යුතුය.',
        'The reservation recipient’s last name must be between 2 and 50 characters.' => 'වෙන්කිරීම ලබන්නාගේ අවසන් නම අකුරු 2 සිට 50 දක්වා විය යුතුය.',
        'Please enter a valid primary mobile number for the reservation recipient.' => 'වෙන්කිරීම ලබන්නාගේ වලංගු ප්‍රධාන ජංගම අංකයක් ඇතුළත් කරන්න.',
        'Please enter a valid secondary mobile number for the reservation recipient.' => 'වෙන්කිරීම ලබන්නාගේ වලංගු දෙවන ජංගම අංකයක් ඇතුළත් කරන්න.',
        'Annual reservations cannot start on February 29. Please choose another date.' => 'වාර්ෂික වෙන්කිරීම් පෙබරවාරි 29 දින ආරම්භ කළ නොහැක. වෙනත් දිනයක් තෝරන්න.',
        'Annual reservations cannot start on February 29' => 'වාර්ෂික වෙන්කිරීම් පෙබරවාරි 29 දින ආරම්භ කළ නොහැක',
        'The selected date is busy. Please try again.' => 'තෝරාගත් දිනය කාර්යබහුලයි. නැවත උත්සාහ කරන්න.',
        'Pricing is not available for this reservation.' => 'මෙම වෙන්කිරීම සඳහා මිල ලබා ගත නොහැක.',
        'Unable to create the reservation. Please try again.' => 'වෙන්කිරීම සෑදිය නොහැක. නැවත උත්සාහ කරන්න.',
        'Reservation date cannot be in the past.' => 'වෙන්කිරීමේ දිනය අතීතයේ විය නොහැක.',
        'This date is blocked and cannot be reserved.' => 'මෙම දිනය අවහිර කර ඇති නිසා වෙන්කළ නොහැක.',
        'Cannot reserve the whole day because another reservation exists.' => 'වෙනත් වෙන්කිරීමක් ඇති නිසා සම්පූර්ණ දිනය වෙන්කළ නොහැක.',
        'This time slot is already reserved.' => 'මෙම වේලාව දැනටමත් වෙන් වී ඇත.',
        'This slot is available for reservation.' => 'මෙම වේලාව වෙන්කිරීමට ලබා ගත හැක.',
        'Invalid date' => 'වලංගු නොවන දිනයකි',
        'Invalid dāna type or time slot' => 'වලංගු නොවන දාන වර්ගයක් හෝ වේලාවකි',
        'Unable to check availability right now' => 'දැන් ලබා ගැනීම පරීක්ෂා කළ නොහැක',
        'This offering is already reserved for the selected date.' => 'තෝරාගත් දිනය සඳහා මෙම පූජාව දැනටමත් වෙන් වී ඇත.',
        'Invalid date or dāna type' => 'වලංගු නොවන දිනයක් හෝ දාන වර්ගයකි',
        'Dāna type is not available' => 'දාන වර්ගය ලබා ගත නොහැක',
        'Unable to load annual pricing right now' => 'දැන් වාර්ෂික මිල ලබා ගත නොහැක',
        'Unable to load pricing right now' => 'දැන් මිල ලබා ගත නොහැක',
        'This reservation is no longer eligible for a receipt upload.' => 'මෙම වෙන්කිරීමට තවදුරටත් රිසිට්පතක් උඩුගත කළ නොහැක.',
        'Please select a payment method' => 'ගෙවීමේ ක්‍රමයක් තෝරන්න',
        'Please enter payment reference/transaction ID' => 'ගනුදෙනු අංකය හෝ යොමුව ඇතුළත් කරන්න',
        'Please select a receipt file to upload' => 'උඩුගත කිරීමට රිසිට්පත් ගොනුවක් තෝරන්න',
        'File size must be less than 5MB' => 'ගොනුව 5MB ට වඩා කුඩා විය යුතුය',
        'Only JPG, JPEG, PNG, and PDF files are allowed' => 'JPG, JPEG, PNG සහ PDF ගොනු පමණක් භාවිතා කළ හැක',
        'Only JPG, PNG, and PDF files are allowed' => 'JPG, PNG සහ PDF ගොනු පමණක් භාවිතා කළ හැක',
        'Failed to upload receipt. Please try again.' => 'රිසිට්පත උඩුගත කිරීම අසාර්ථකයි. නැවත උත්සාහ කරන්න.',
        'An error occurred while uploading the receipt. Please try again.' => 'රිසිට්පත උඩුගත කිරීමේදී දෝෂයක් ඇති විය. නැවත උත්සාහ කරන්න.',
        'Receipt uploaded successfully! Your dāna reservation is now pending payment verification.' => 'රිසිට්පත සාර්ථකව උඩුගත විය. ඔබගේ දාන වෙන්කිරීම ගෙවීම තහවුරු කිරීම පොරොත්තුවෙන් ඇත.',
        'Receipt not found' => 'රිසිට්පත හමු නොවීය',
        'Receipt file not found' => 'රිසිට්පත් ගොනුව හමු නොවීය',
        'Unsupported receipt type' => 'මෙම රිසිට්පත් වර්ගයට සහාය නැත',
    ];
    if (isset($map[$message])) {
        return $map[$message];
    }
    if (preg_match('/^Password must be at least (\d+) characters\.?$/', $message, $match)) {
        return 'මුරපදය අවම වශයෙන් අකුරු ' . $match[1] . ' ක් විය යුතුය.';
    }
    if (preg_match('/^Reservations can only be made up to (\d+) days in advance\.$/', $message, $match)) {
        return 'වෙන්කිරීම් කළ හැක්කේ දින ' . $match[1] . ' කට පෙර පමණි.';
    }
    if (preg_match('/^([A-Za-z]+ \d{1,2}, \d{4}): (.+)$/', $message, $match)) {
        $stamp = strtotime($match[1]);
        $when = $stamp ? ui_format_date($stamp) : $match[1];
        return $when . ': ' . t_msg($match[2]);
    }
    if (preg_match('/^([A-Za-z]+ \d{1,2}, \d{4}) is no longer available\.$/', $message, $match)) {
        $stamp = strtotime($match[1]);
        $when = $stamp ? ui_format_date($stamp) : $match[1];
        return $when . ' තවදුරටත් ලබා ගත නොහැක.';
    }
    return $message;
}

function te_msg(string $message): string {
    return htmlspecialchars(t_msg($message), ENT_QUOTES, 'UTF-8');
}
