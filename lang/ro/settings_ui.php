<?php

return [
    'layout' => [
        'title' => 'Setări',
        'description' => 'Administrează profilul și securitatea contului.',
        'navigationLabel' => 'Secțiunile setărilor',
        'profile' => 'Profil',
        'security' => 'Securitate',
    ],
    'shared' => [
        'save' => 'Salvează modificările',
        'cancel' => 'Anulează',
        'password' => 'Parolă',
        'passwordPlaceholder' => 'Parolă',
        'showPassword' => 'Afișează parola',
        'hidePassword' => 'Ascunde parola',
    ],
    'pages' => [
        'profile' => [
            'headTitle' => 'Setările profilului',
            'title' => 'Profil',
            'description' => 'Actualizează numele și adresa de e-mail.',
            'name' => 'Nume',
            'namePlaceholder' => 'Nume complet',
            'email' => 'Adresă de e-mail',
            'emailPlaceholder' => 'Adresă de e-mail',
            'unverified' => 'Adresa de e-mail nu este verificată.',
            'resend' => 'Retrimite e-mailul de verificare',
            'verificationSent' => 'Un nou link de verificare a fost trimis la adresa ta de e-mail.',
            'deleteTitle' => 'Șterge contul',
            'deleteDescription' => 'Șterge definitiv contul și resursele sale.',
            'warningTitle' => 'Acțiune permanentă',
            'warningDescription' => 'Această acțiune nu poate fi anulată.',
            'deleteTrigger' => 'Șterge contul',
            'deleteDialogTitle' => 'Ștergi contul?',
            'deleteDialogDescription' => 'Contul și datele sale vor fi șterse definitiv. Introdu parola pentru confirmare.',
            'deleteConfirm' => 'Șterge contul',
            'closeDialog' => 'Închide fereastra',
        ],
        'security' => [
            'headTitle' => 'Setările de securitate',
            'title' => 'Actualizează parola',
            'description' => 'Folosește o parolă lungă și unică pentru a proteja contul.',
            'currentPassword' => 'Parola curentă',
            'newPassword' => 'Parolă nouă',
            'confirmPassword' => 'Confirmă parola',
        ],
    ],
    'flash' => [
        'profileUpdated' => 'Profil actualizat.',
        'passwordUpdated' => 'Parolă actualizată.',
    ],
];
