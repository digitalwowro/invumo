<?php

return [
    'pages' => [
        403 => [
            'headTitle' => 'Acces interzis',
            'title' => 'Nu poți accesa această pagină',
            'description' => 'Accesul tău curent în Invumo nu include această acțiune sau pagină.',
            'action' => 'Înapoi în Invumo',
        ],
        404 => [
            'headTitle' => 'Pagina nu a fost găsită',
            'title' => 'Nu am găsit această pagină',
            'description' => 'Adresa poate fi incorectă sau pagina nu mai este disponibilă.',
            'action' => 'Înapoi în Invumo',
        ],
        500 => [
            'headTitle' => 'Ceva nu a funcționat',
            'title' => 'Invumo nu a putut finaliza cererea',
            'description' => 'Încearcă din nou. Dacă problema continuă, contactează echipa de suport.',
            'action' => 'Înapoi în Invumo',
        ],
        503 => [
            'headTitle' => 'Indisponibil temporar',
            'title' => 'Invumo este indisponibil temporar',
            'description' => 'Așteaptă puțin și încearcă din nou.',
            'action' => 'Înapoi în Invumo',
        ],
    ],
];
