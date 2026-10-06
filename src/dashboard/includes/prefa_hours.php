<?php

function prefaFormatDuration(int $hours, int $minutes): string
{
    if ($hours <= 0 && $minutes <= 0) return '0 min';
    if ($hours <= 0) return $minutes . ' min';
    if ($minutes <= 0) return $hours . ' h';
    return sprintf('%d h %02d min', $hours, $minutes);
}

function prefaFormatDays(float $days): string
{
    $formatted = number_format($days, 2, ',', ' ');
    if (strpos($formatted, ',') !== false) {
        $formatted = rtrim(rtrim($formatted, '0'), ',');
    }
    return $formatted . ' j';
}

function prefaHoursSettings(PDO $db): array
{
    try {
        $row = $db->query('SELECT heures_par_pouce, duree_heures, duree_minutes FROM parametrage_prefabrication WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException) {
        $row = $db->query('SELECT heures_par_pouce FROM parametrage_prefabrication WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row) throw new RuntimeException('Le temps par pouce ISO n’est pas configuré.');

    $rate = (float) $row['heures_par_pouce'];
    $hours = isset($row['duree_heures']) && $row['duree_heures'] !== null ? (int) $row['duree_heures'] : (int) floor($rate);
    $minutes = isset($row['duree_minutes']) && $row['duree_minutes'] !== null ? (int) $row['duree_minutes'] : (int) round(($rate - floor($rate)) * 60);
    $days = $rate / 24;
    $daysVal = rtrim(rtrim(number_format($days, 4, '.', ''), '0'), '.');

    return [
        'rate' => (string) $row['heures_par_pouce'],
        'hours' => $hours,
        'minutes' => $minutes,
        'days' => $daysVal === '' ? '0' : $daysVal,
        'formatted' => prefaFormatDays($days),
    ];
}

function prefaHoursPerInch(PDO $db): string
{
    $settings = prefaHoursSettings($db);
    return $settings['rate'];
}

function prefaCalculatedHours(int $inches, string $rate): string
{
    return number_format(round($inches * (float) $rate, 2), 2, '.', '');
}
