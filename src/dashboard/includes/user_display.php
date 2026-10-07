<?php

function userRoleLabel(array $user): string
{
    return $user['role_name'] ?? 'Non renseigné';
}

function userCreationLabel(array $user): string
{
    if (empty($user['date_creation'])) return 'Non renseignée';
    $date = new DateTimeImmutable($user['date_creation'], new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i');
}
