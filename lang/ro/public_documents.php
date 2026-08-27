<?php

return [
    'management' => [
        'title' => 'Link public securizat',
        'description' => 'Partajează documentul curent fără a expune identificatorii companiei sau ai documentului.',
        'statuses' => [
            'ACTIVE' => 'Activ',
            'DISABLED' => 'Dezactivat',
            'EXPIRED' => 'Expirat',
            'NOT_CREATED' => 'Necreat',
        ],
        'expires' => 'Expiră: :date',
        'copy' => 'Copiază linkul',
        'copied' => 'Linkul securizat a fost copiat.',
        'copy_failed' => 'Linkul securizat nu a putut fi copiat.',
        'create' => 'Creează link securizat',
        're_enable' => 'Creează un link securizat nou',
        'regenerate' => 'Regenerează linkul',
        'revoke' => 'Revocă linkul',
    ],
    'page' => [
        'head_title' => 'Document partajat',
        'description' => 'Acesta este documentul partajat în forma sa curentă.',
        'download_pdf' => 'Descarcă PDF-ul',
        'provided_by' => 'Partajat securizat cu Invumo',
    ],
    'decision' => [
        'title' => 'Răspunde la această ofertă',
        'description' => 'Completează datele tale, apoi acceptă sau respinge oferta curentă.',
        'customer_name' => 'Numele tău',
        'customer_email' => 'Adresa ta de e-mail',
        'accept' => 'Acceptă oferta',
        'reject' => 'Respinge oferta',
        'accepted_title' => 'Ofertă acceptată',
        'accepted_description' => 'Acceptarea ta a fost înregistrată.',
        'rejected_title' => 'Ofertă respinsă',
        'rejected_description' => 'Respingerea ta a fost înregistrată.',
        'unavailable_title' => 'Răspunsul nu este disponibil',
        'unavailable_description' => 'Această ofertă nu poate fi acceptată sau respinsă în acest moment.',
    ],
    'feedback' => [
        'created' => 'Linkul public securizat a fost creat.',
        'regenerated' => 'Linkul public securizat a fost regenerat.',
        'revoked' => 'Linkul public securizat a fost revocat.',
    ],
    'errors' => [
        'unavailable' => 'Linkul public securizat nu mai este disponibil. Creează mai întâi un link nou.',
        'decision_unavailable' => 'Această ofertă nu mai poate primi un răspuns public.',
        'decision_conflict' => 'A fost deja înregistrat un răspuns diferit. Contactează Compania dacă trebuie corectat.',
        'idempotency_conflict' => 'Răspunsul nu a putut fi reîncercat în siguranță. Reîncarcă pagina și încearcă din nou.',
    ],
];
